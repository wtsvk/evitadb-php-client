<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Tests\Unit;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Wtsvk\EvitaDbClient\QueryBuilder;

use function iterator_to_array;

#[RequiresPhpExtension('grpc')]
#[RequiresPhpExtension('protobuf')]
final class QueryBuilderTest extends TestCase
{
    public function testBasicQueryContainsCollectionAndRequire(): void
    {
        $request = (new QueryBuilder('Product'))
            ->page(1, 20)
            ->build();

        $query = $request->getQuery();
        $this->assertStringContainsString("collection('Product')", $query);
        $this->assertStringContainsString('entityFetch(', $query);
        $this->assertStringContainsString('page(1, 20)', $query);
    }

    public function testLocaleFilterIsAdded(): void
    {
        $request = (new QueryBuilder('Product'))
            ->withLocale('sk')
            ->page(1, 20)
            ->build();

        $this->assertStringContainsString("entityLocaleEquals('sk')", $request->getQuery());
    }

    public function testPaginationValuesAppearInQuery(): void
    {
        $request = (new QueryBuilder('Product'))
            ->page(3, 50)
            ->build();

        $this->assertStringContainsString('page(3, 50)', $request->getQuery());
    }

    public function testFilterByAttributeAddsAttributeEquals(): void
    {
        $request = (new QueryBuilder('Product'))
            ->filterByAttribute('code', 'PROD-001')
            ->page(1, 20)
            ->build();

        $this->assertStringContainsString("attributeEquals('code', ?)", $request->getQuery());
        $this->assertCount(1, iterator_to_array($request->getPositionalQueryParams()));
    }

    public function testFilterPriceInCurrencyAddedToFilter(): void
    {
        $request = (new QueryBuilder('Product'))
            ->filterPriceInCurrency('EUR')
            ->page(1, 20)
            ->build();

        $this->assertStringContainsString('priceInCurrency(?)', $request->getQuery());
    }

    public function testFilterPriceBetweenAddsTwoParams(): void
    {
        $request = (new QueryBuilder('Product'))
            ->filterPriceInCurrency('EUR')
            ->filterPriceBetween(10.0, 500.0)
            ->page(1, 20)
            ->build();

        $this->assertStringContainsString('priceBetween(?, ?)', $request->getQuery());
        $this->assertCount(3, iterator_to_array($request->getPositionalQueryParams()));
    }

    public function testFilterByCategoryIdUsesReferenceHaving(): void
    {
        $request = (new QueryBuilder('Product'))
            ->filterByCategoryId(42)
            ->page(1, 20)
            ->build();

        $this->assertStringContainsString("referenceHaving('Category', entityPrimaryKeyInSet(?))", $request->getQuery());
    }

    public function testFilterPriceInPriceListsAddsCorrectFragment(): void
    {
        $request = (new QueryBuilder('Product'))
            ->filterPriceInCurrency('EUR')
            ->filterPriceInPriceLists(['alza', 'mall'])
            ->page(1, 20)
            ->build();

        $this->assertStringContainsString('priceInPriceLists(?, ?)', $request->getQuery());
    }

    public function testNoFilterProducesOnlyCollectionAndRequire(): void
    {
        $request = (new QueryBuilder('Category'))
            ->page(1, 20)
            ->build();

        $query = $request->getQuery();
        $this->assertStringContainsString("collection('Category')", $query);
        $this->assertStringNotContainsString('filterBy(', $query);
    }

    public function testFilterByAttributeContainsAddsFragment(): void
    {
        $request = (new QueryBuilder('Product'))
            ->filterByAttributeContains('name', 'phone')
            ->page(1, 20)
            ->build();

        $this->assertStringContainsString("attributeContains('name', ?)", $request->getQuery());
        $this->assertCount(1, iterator_to_array($request->getPositionalQueryParams()));
    }

    public function testFilterByAttributeAcceptsIntAndFloat(): void
    {
        $request = (new QueryBuilder('Product'))
            ->filterByAttribute('quantity', 42)
            ->filterByAttribute('weight', 3.14)
            ->page(1, 20)
            ->build();

        $query = $request->getQuery();
        $this->assertStringContainsString("attributeEquals('quantity', ?)", $query);
        $this->assertStringContainsString("attributeEquals('weight', ?)", $query);
        $this->assertCount(2, iterator_to_array($request->getPositionalQueryParams()));
    }

    public function testMultipleFiltersAreCombined(): void
    {
        $request = (new QueryBuilder('Product'))
            ->withLocale('en')
            ->filterByAttribute('code', 'ABC')
            ->filterPriceInCurrency('USD')
            ->filterPriceBetween(5.0, 100.0)
            ->page(2, 10)
            ->build();

        $query = $request->getQuery();
        $this->assertStringContainsString('filterBy(', $query);
        $this->assertStringContainsString("entityLocaleEquals('en')", $query);
        $this->assertStringContainsString("attributeEquals('code', ?)", $query);
        $this->assertStringContainsString('priceInCurrency(?)', $query);
        $this->assertStringContainsString('priceBetween(?, ?)', $query);
        $this->assertStringContainsString('page(2, 10)', $query);
        $this->assertCount(4, iterator_to_array($request->getPositionalQueryParams()));
    }

    public function testEmptyPriceListsProducesEmptyPlaceholders(): void
    {
        $request = (new QueryBuilder('Product'))
            ->filterPriceInPriceLists([])
            ->page(1, 20)
            ->build();

        $this->assertStringContainsString('priceInPriceLists()', $request->getQuery());
    }

    public function testDefaultPageValues(): void
    {
        $request = (new QueryBuilder('Product'))
            ->build();

        $this->assertStringContainsString('page(1, 20)', $request->getQuery());
    }

    public function testFluentInterfaceReturnsSameInstance(): void
    {
        $builder = new QueryBuilder('Product');

        $this->assertSame($builder, $builder->withLocale('sk'));
        $this->assertSame($builder, $builder->filterByAttribute('x', 'y'));
        $this->assertSame($builder, $builder->filterByAttributeContains('x', 'y'));
        $this->assertSame($builder, $builder->filterPriceInCurrency('EUR'));
        $this->assertSame($builder, $builder->filterPriceBetween(1.0, 2.0));
        $this->assertSame($builder, $builder->filterPriceInPriceLists(['a']));
        $this->assertSame($builder, $builder->filterByCategoryId(1));
        $this->assertSame($builder, $builder->page(1, 10));
    }
}
