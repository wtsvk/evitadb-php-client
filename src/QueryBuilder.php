<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient;

use DateTimeInterface;
use Webmozart\Assert\Assert;
use Wtsvk\EvitaDbClient\Protocol\GrpcBigDecimal;
use Wtsvk\EvitaDbClient\Protocol\GrpcIntegerArray;
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

        $this->filterParts[] = 'attributeEquals(\'' . $name . '\', ?)';

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

        $this->filterParts[] = 'attributeContains(\'' . $name . '\', ?)';
        $this->params[] = $this->paramString($value);

        return $this;
    }

    public function filterByAttributeGreaterThan(string $name, string|int|float $value): static
    {
        self::assertValidName($name);

        $this->filterParts[] = 'attributeGreaterThan(\'' . $name . '\', ?)';
        $this->params[] = $this->typedParam($value);

        return $this;
    }

    public function filterByAttributeLessThan(string $name, string|int|float $value): static
    {
        self::assertValidName($name);

        $this->filterParts[] = 'attributeLessThan(\'' . $name . '\', ?)';
        $this->params[] = $this->typedParam($value);

        return $this;
    }

    public function filterByAttributeBetween(string $name, string|int|float $from, string|int|float $to): static
    {
        self::assertValidName($name);

        $this->filterParts[] = 'attributeBetween(\'' . $name . '\', ?, ?)';
        $this->params[] = $this->typedParam($from);
        $this->params[] = $this->typedParam($to);

        return $this;
    }

    public function filterByAttributeStartsWith(string $name, string $value): static
    {
        self::assertValidName($name);

        $this->filterParts[] = 'attributeStartsWith(\'' . $name . '\', ?)';
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
        $this->filterParts[] = 'attributeInSet(\'' . $name . '\', ' . $placeholders . ')';

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

        $this->filterParts[] = "referenceHaving('" . $referenceName . "', entityHaving(entityPrimaryKeyInSet(?)))";
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
        $this->params[] = $this->paramString($moment->format('Y-m-d\TH:i:sP'));

        return $this;
    }

    public function orderByAttributeNatural(string $name, SortDirection $direction = SortDirection::Asc): static
    {
        self::assertValidName($name);

        $this->orderParts[] = 'attributeNatural(\'' . $name . '\', ' . $direction->value . ')';

        return $this;
    }

    public function orderByPriceNatural(SortDirection $direction = SortDirection::Asc): static
    {
        $this->orderParts[] = 'priceNatural(' . $direction->value . ')';

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
        $queryParts = ['collection(\'' . $this->entityType . '\')'];

        $filterParts = $this->locale !== null ? ['entityLocaleEquals(\'' . $this->locale . '\')', ...$this->filterParts] : $this->filterParts;

        if ($filterParts !== []) {
            $queryParts[] = 'filterBy(' . implode(', ', $filterParts) . ')';
        }

        if ($this->orderParts !== []) {
            $queryParts[] = 'orderBy(' . implode(', ', $this->orderParts) . ')';
        }

        $requireParts = [];
        if ($this->entityFetch !== null) {
            $requireParts[] = $this->entityFetch->toEvitaQL();
        }
        $requireParts[] = 'page(' . $this->pageNumber . ', ' . $this->pageSize . ')';

        $queryParts[] = 'require(' . implode(', ', $requireParts) . ')';

        $request = new GrpcQueryRequest();
        $request->setQuery('query(' . implode(', ', $queryParts) . ')');

        if ($this->params !== []) {
            $request->setPositionalQueryParams($this->params);
        }

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
