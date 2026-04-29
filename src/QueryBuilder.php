<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient;

use Wtsvk\EvitaDbClient\Protocol\GrpcBigDecimal;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryParam;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;

use function array_fill;
use function count;
use function implode;

final class QueryBuilder
{
    private ?string $locale = null;

    /**
     * @var list<string>
     */
    private array $filterParts = [];

    /**
     * @var list<GrpcQueryParam>
     */
    private array $params = [];

    private int $pageNumber = 1;

    private int $pageSize = 20;

    public function __construct(private readonly string $entityType)
    {
    }

    public function withLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function filterByAttribute(string $name, string|int|float $value): static
    {
        $this->filterParts[] = 'attributeEquals(\'' . $name . '\', ?)';
        $this->params[] = $this->paramString((string) $value);

        return $this;
    }

    public function filterByAttributeContains(string $name, string $value): static
    {
        $this->filterParts[] = 'attributeContains(\'' . $name . '\', ?)';
        $this->params[] = $this->paramString($value);

        return $this;
    }

    public function filterPriceBetween(float $from, float $to): static
    {
        $this->filterParts[] = 'priceBetween(?, ?)';
        $this->params[] = $this->paramBigDecimal((string) $from);
        $this->params[] = $this->paramBigDecimal((string) $to);

        return $this;
    }

    public function filterPriceInCurrency(string $currencyCode): static
    {
        $this->filterParts[] = 'priceInCurrency(?)';
        $this->params[] = $this->paramString($currencyCode);

        return $this;
    }

    /**
     * @param  list<string>  $priceLists
     */
    public function filterPriceInPriceLists(array $priceLists): static
    {
        $placeholders = implode(', ', array_fill(0, count($priceLists), '?'));
        $this->filterParts[] = 'priceInPriceLists(' . $placeholders . ')';
        foreach ($priceLists as $pl) {
            $this->params[] = $this->paramString($pl);
        }

        return $this;
    }

    public function filterByCategoryId(int $categoryEvitaId): static
    {
        $this->filterParts[] = "referenceHaving('Category', entityPrimaryKeyInSet(?))";
        $this->params[] = $this->paramInt($categoryEvitaId);

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

        $queryParts[] = 'require('
            . 'entityFetch(attributeContentAll(), associatedDataContentAll(), priceContentAll(), referenceContentAll())'
            . ', page(' . $this->pageNumber . ', ' . $this->pageSize . ')'
            . ')';

        $request = new GrpcQueryRequest();
        $request->setQuery('query(' . implode(', ', $queryParts) . ')');

        if ($this->params !== []) {
            $request->setPositionalQueryParams($this->params);
        }

        return $request;
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

    private function paramBigDecimal(string $value): GrpcQueryParam
    {
        $bigDecimal = new GrpcBigDecimal();
        $bigDecimal->setValueString($value);

        $param = new GrpcQueryParam();
        $param->setBigDecimalValue($bigDecimal);

        return $param;
    }
}
