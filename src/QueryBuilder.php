<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient;

use DateTimeInterface;
use Google\Protobuf\Timestamp;
use Webmozart\Assert\Assert;
use Wtsvk\EvitaDbClient\Protocol\GrpcBigDecimal;
use Wtsvk\EvitaDbClient\Protocol\GrpcIntegerArray;
use Wtsvk\EvitaDbClient\Protocol\GrpcOffsetDateTime;
use Wtsvk\EvitaDbClient\Protocol\GrpcOrderDirection;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryParam;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;

use function array_fill;
use function count;
use function implode;
use function is_float;
use function is_int;
use function number_format;
use function rtrim;

/**
 * Mutable fluent builder for EvitaDB queries.
 *
 * Methods mutate $this and return $this. Construct a fresh instance per query
 * rather than reusing a partially-configured one across call-sites — otherwise
 * later additions will leak back to earlier consumers.
 */
final class QueryBuilder
{
    private ?string $locale = null;

    /**
     * @var list<string>
     */
    private array $filterParts = [];

    /**
     * @var list<string>
     */
    private array $orderParts = [];

    /**
     * @var list<GrpcQueryParam>
     */
    private array $orderParams = [];

    /**
     * @var list<GrpcQueryParam>
     */
    private array $params = [];

    private int $pageNumber = 1;

    private int $pageSize = 20;

    private ?EntityFetch $entityFetch = null;

    public function __construct(private readonly string $entityType)
    {
        self::assertValidName($entityType);
    }

    public function withLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function withEntityFetch(EntityFetch $fetch): static
    {
        $this->entityFetch = $fetch;

        return $this;
    }

    public function filterByAttribute(string $name, string|int|float $value): static
    {
        self::assertValidName($name);

        $this->filterParts[] = 'attributeEquals(?, ?)';
        $this->params[] = $this->paramString($name);

        $this->params[] = match (true) {
            is_int($value) => $this->paramInt($value),
            is_float($value) => $this->paramBigDecimal(self::formatFloat($value)),
            default => $this->paramString($value),
        };

        return $this;
    }

    public function filterByAttributeContains(string $name, string $value): static
    {
        self::assertValidName($name);

        $this->filterParts[] = 'attributeContains(?, ?)';
        $this->params[] = $this->paramString($name);
        $this->params[] = $this->paramString($value);

        return $this;
    }

    public function filterByAttributeGreaterThan(string $name, string|int|float $value): static
    {
        self::assertValidName($name);

        $this->filterParts[] = 'attributeGreaterThan(?, ?)';
        $this->params[] = $this->paramString($name);
        $this->params[] = $this->typedParam($value);

        return $this;
    }

    public function filterByAttributeLessThan(string $name, string|int|float $value): static
    {
        self::assertValidName($name);

        $this->filterParts[] = 'attributeLessThan(?, ?)';
        $this->params[] = $this->paramString($name);
        $this->params[] = $this->typedParam($value);

        return $this;
    }

    public function filterByAttributeBetween(string $name, string|int|float $from, string|int|float $to): static
    {
        self::assertValidName($name);

        $this->filterParts[] = 'attributeBetween(?, ?, ?)';
        $this->params[] = $this->paramString($name);
        $this->params[] = $this->typedParam($from);
        $this->params[] = $this->typedParam($to);

        return $this;
    }

    public function filterByAttributeStartsWith(string $name, string $value): static
    {
        self::assertValidName($name);

        $this->filterParts[] = 'attributeStartsWith(?, ?)';
        $this->params[] = $this->paramString($name);
        $this->params[] = $this->paramString($value);

        return $this;
    }

    /**
     * @param non-empty-list<string|int> $values
     */
    public function filterByAttributeInSet(string $name, array $values): static
    {
        self::assertValidName($name);
        Assert::notEmpty($values, 'filterByAttributeInSet requires at least one value.');

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->filterParts[] = 'attributeInSet(?, ' . $placeholders . ')';
        $this->params[] = $this->paramString($name);

        foreach ($values as $value) {
            $this->params[] = is_int($value) ? $this->paramInt($value) : $this->paramString($value);
        }

        return $this;
    }

    /**
     * @param non-empty-list<int> $pks
     */
    public function filterByEntityPrimaryKeyInSet(array $pks): static
    {
        Assert::notEmpty($pks, 'filterByEntityPrimaryKeyInSet requires at least one primary key.');

        $this->filterParts[] = 'entityPrimaryKeyInSet(?)';
        $this->params[] = $this->paramIntArray($pks);

        return $this;
    }

    /**
     * @param non-empty-list<int> $pks
     */
    public function filterByReferencePrimaryKeyInSet(string $referenceName, array $pks): static
    {
        self::assertValidName($referenceName);
        Assert::notEmpty($pks, 'filterByReferencePrimaryKeyInSet requires at least one primary key.');

        $this->filterParts[] = 'referenceHaving(?, entityHaving(entityPrimaryKeyInSet(?)))';
        $this->params[] = $this->paramString($referenceName);
        $this->params[] = $this->paramIntArray($pks);

        return $this;
    }

    public function filterPriceBetween(float $from, float $to): static
    {
        $this->filterParts[] = 'priceBetween(?, ?)';
        $this->params[] = $this->paramBigDecimal(self::formatFloat($from));
        $this->params[] = $this->paramBigDecimal(self::formatFloat($to));

        return $this;
    }

    public function filterPriceInCurrency(string $currencyCode): static
    {
        $this->filterParts[] = 'priceInCurrency(?)';
        $this->params[] = $this->paramString($currencyCode);

        return $this;
    }

    /**
     * @param non-empty-list<string> $priceLists
     */
    public function filterPriceInPriceLists(array $priceLists): static
    {
        Assert::notEmpty($priceLists, 'filterPriceInPriceLists requires at least one price list.');

        $placeholders = implode(', ', array_fill(0, count($priceLists), '?'));
        $this->filterParts[] = 'priceInPriceLists(' . $placeholders . ')';

        foreach ($priceLists as $pl) {
            $this->params[] = $this->paramString($pl);
        }

        return $this;
    }

    public function filterPriceValidIn(DateTimeInterface $moment): static
    {
        $this->filterParts[] = 'priceValidIn(?)';
        $this->params[] = $this->paramOffsetDateTime($moment);

        return $this;
    }

    public function orderByAttributeNatural(string $name, SortDirection $direction = SortDirection::Asc): static
    {
        self::assertValidName($name);

        $this->orderParts[] = 'attributeNatural(?, ?)';
        $this->orderParams[] = $this->paramString($name);
        $this->orderParams[] = $this->paramOrderDirection($direction);

        return $this;
    }

    public function orderByPriceNatural(SortDirection $direction = SortDirection::Asc): static
    {
        $this->orderParts[] = 'priceNatural(?)';
        $this->orderParams[] = $this->paramOrderDirection($direction);

        return $this;
    }

    public function page(int $number, int $size = 20): static
    {
        $this->pageNumber = $number;
        $this->pageSize = $size;

        return $this;
    }

    public function build(): GrpcQueryRequest
    {
        $headerParams = [$this->paramString($this->entityType)];
        $queryParts = ['collection(?)'];

        $filterParts = $this->filterParts;
        if ($this->locale !== null) {
            $filterParts = ['entityLocaleEquals(?)', ...$filterParts];
            $headerParams[] = $this->paramString($this->locale);
        }

        if ($filterParts !== []) {
            $queryParts[] = 'filterBy(' . implode(', ', $filterParts) . ')';
        }

        if ($this->orderParts !== []) {
            $queryParts[] = 'orderBy(' . implode(', ', $this->orderParts) . ')';
        }

        $requireParts = [];
        $entityFetchParams = [];
        if ($this->entityFetch !== null) {
            $requireParts[] = $this->entityFetch->toEvitaQL();
            $entityFetchParams = $this->entityFetch->getParams();
        }
        $requireParts[] = 'page(?, ?)';

        $queryParts[] = 'require(' . implode(', ', $requireParts) . ')';

        $request = new GrpcQueryRequest();
        $request->setQuery('query(' . implode(', ', $queryParts) . ')');
        $request->setPositionalQueryParams([
            ...$headerParams,
            ...$this->params,
            ...$this->orderParams,
            ...$entityFetchParams,
            $this->paramInt($this->pageNumber),
            $this->paramInt($this->pageSize),
        ]);

        return $request;
    }

    private function typedParam(string|int|float $value): GrpcQueryParam
    {
        return match (true) {
            is_int($value) => $this->paramInt($value),
            is_float($value) => $this->paramBigDecimal(self::formatFloat($value)),
            default => $this->paramString($value),
        };
    }

    private function paramString(string $value): GrpcQueryParam
    {
        $param = new GrpcQueryParam();
        $param->setStringValue($value);

        return $param;
    }

    private function paramInt(int $value): GrpcQueryParam
    {
        $param = new GrpcQueryParam();
        $param->setIntegerValue($value);

        return $param;
    }

    /**
     * @param list<int> $values
     */
    private function paramIntArray(array $values): GrpcQueryParam
    {
        $array = new GrpcIntegerArray();
        $array->setValue($values);

        $param = new GrpcQueryParam();
        $param->setIntegerArrayValue($array);

        return $param;
    }

    /**
     * Enum literals (ASC/DESC) are forbidden by the SAFE-mode EvitaQL parser just like
     * string literals, so the direction must travel as a typed positional param.
     */
    private function paramOrderDirection(SortDirection $direction): GrpcQueryParam
    {
        $param = new GrpcQueryParam();
        $param->setOrderDirectionValue(match ($direction) {
            SortDirection::Asc => GrpcOrderDirection::ASC,
            SortDirection::Desc => GrpcOrderDirection::DESC,
        });

        return $param;
    }

    /**
     * priceValidIn() expects an OffsetDateTime — the server does not coerce plain strings,
     * so the moment is sent as GrpcOffsetDateTime (absolute instant + zone offset).
     */
    private function paramOffsetDateTime(DateTimeInterface $moment): GrpcQueryParam
    {
        $timestamp = new Timestamp();
        $timestamp->setSeconds($moment->getTimestamp());

        $offsetDateTime = new GrpcOffsetDateTime();
        $offsetDateTime->setTimestamp($timestamp);
        $offsetDateTime->setOffset($moment->format('P'));

        $param = new GrpcQueryParam();
        $param->setOffsetDateTimeValue($offsetDateTime);

        return $param;
    }

    private function paramBigDecimal(string $value): GrpcQueryParam
    {
        $bigDecimal = new GrpcBigDecimal();
        $bigDecimal->setValueString($value);

        $param = new GrpcQueryParam();
        $param->setBigDecimalValue($bigDecimal);

        return $param;
    }

    private static function assertValidName(string $name): void
    {
        Assert::regex(
            value: $name,
            pattern: '/^[a-zA-Z][a-zA-Z0-9_]*$/',
            message: 'Invalid EvitaQL identifier: ' . $name,
        );
    }

    private static function formatFloat(float $value): string
    {
        return rtrim(rtrim(number_format(
            num: $value,
            decimals: 10,
            thousands_separator: '',
        ), '0'), '.');
    }
}
