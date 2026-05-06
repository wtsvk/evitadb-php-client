<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Wtsvk\EvitaDbClient\EntityFetch;
use Wtsvk\EvitaDbClient\QueryBuilder;
use Wtsvk\EvitaDbClient\SortDirection;

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
        $this->assertStringContainsString('page(1, 20)', $query);
    }

    public function testDefaultBuildOmitsEntityFetchSoServerReturnsIdentityOnly(): void
    {
        $query = (new QueryBuilder('Product'))->page(1, 20)->build()->getQuery();

        $this->assertStringNotContainsString('entityFetch(', $query);
    }

    public function testWithEntityFetchAddsEntityFetchToRequire(): void
    {
        $query = (new QueryBuilder('Product'))
            ->withEntityFetch(EntityFetch::all())
            ->page(1, 20)
            ->build()
            ->getQuery();

        $this->assertStringContainsString('entityFetch(', $query);
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

    public function testFilterByReferencePrimaryKeyInSetUsesReferenceHaving(): void
    {
        $request = (new QueryBuilder('Product'))
            ->filterByReferencePrimaryKeyInSet('Category', [42])
            ->page(1, 20)
            ->build();

        $this->assertStringContainsString("referenceHaving('Category', entityHaving(entityPrimaryKeyInSet(?)))", $request->getQuery());
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
        $this->assertSame($builder, $builder->filterByReferencePrimaryKeyInSet('Category', [1]));
        $this->assertSame($builder, $builder->page(1, 10));
        $this->assertSame($builder, $builder->orderByAttributeNatural('name'));
    }

    public function testInvalidAttributeNameThrowsException(): void
    {
        $builder = new QueryBuilder('Product');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid EvitaQL identifier/');

        $builder->filterByAttribute("name'); DROP TABLE --", 'x');
    }

    public function testInvalidEntityTypeInConstructorThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid EvitaQL identifier/');

        new QueryBuilder('123invalid');
    }

    public function testFilterByAttributeGreaterThan(): void
    {
        $request = (new QueryBuilder('Product'))
            ->filterByAttributeGreaterThan('price', 100)
            ->build();

        $this->assertStringContainsString("attributeGreaterThan('price', ?)", $request->getQuery());
    }

    public function testFilterByAttributeLessThan(): void
    {
        $request = (new QueryBuilder('Product'))
            ->filterByAttributeLessThan('stock', 10)
            ->build();

        $this->assertStringContainsString("attributeLessThan('stock', ?)", $request->getQuery());
    }

    public function testFilterByAttributeBetween(): void
    {
        $request = (new QueryBuilder('Product'))
            ->filterByAttributeBetween('weight', 1.5, 10.5)
            ->build();

        $this->assertStringContainsString("attributeBetween('weight', ?, ?)", $request->getQuery());
        $this->assertCount(2, iterator_to_array($request->getPositionalQueryParams()));
    }

    public function testFilterByAttributeStartsWith(): void
    {
        $request = (new QueryBuilder('Product'))
            ->filterByAttributeStartsWith('code', 'PROD')
            ->build();

        $this->assertStringContainsString("attributeStartsWith('code', ?)", $request->getQuery());
    }

    public function testFilterByAttributeInSet(): void
    {
        $request = (new QueryBuilder('Product'))
            ->filterByAttributeInSet('status', ['active', 'pending'])
            ->build();

        $this->assertStringContainsString("attributeInSet('status', ?, ?)", $request->getQuery());
        $this->assertCount(2, iterator_to_array($request->getPositionalQueryParams()));
    }

    public function testFilterByEntityPrimaryKeyInSet(): void
    {
        $request = (new QueryBuilder('Product'))
            ->filterByEntityPrimaryKeyInSet([1, 2, 3])
            ->build();

        $this->assertStringContainsString('entityPrimaryKeyInSet(?)', $request->getQuery());
    }

    public function testFilterPriceValidIn(): void
    {
        $moment = new DateTimeImmutable('2024-06-15T12:00:00+02:00');
        $request = (new QueryBuilder('Product'))
            ->filterPriceValidIn($moment)
            ->build();

        $this->assertStringContainsString('priceValidIn(?)', $request->getQuery());
    }

    public function testOrderByAttributeNatural(): void
    {
        $request = (new QueryBuilder('Product'))
            ->orderByAttributeNatural('name', SortDirection::Desc)
            ->build();

        $this->assertStringContainsString("orderBy(attributeNatural('name', DESC))", $request->getQuery());
    }

    public function testOrderByPriceNatural(): void
    {
        $request = (new QueryBuilder('Product'))
            ->orderByPriceNatural(SortDirection::Asc)
            ->build();

        $this->assertStringContainsString('orderBy(priceNatural(ASC))', $request->getQuery());
    }

    public function testMultipleOrderClauses(): void
    {
        $request = (new QueryBuilder('Product'))
            ->orderByAttributeNatural('name')
            ->orderByPriceNatural(SortDirection::Desc)
            ->build();

        $query = $request->getQuery();
        $this->assertStringContainsString("attributeNatural('name', ASC)", $query);
        $this->assertStringContainsString('priceNatural(DESC)', $query);
    }

    public function testWithEntityFetchOverridesDefault(): void
    {
        $request = (new QueryBuilder('Product'))
            ->withEntityFetch((new EntityFetch())->attributeContent('name', 'code'))
            ->build();

        $query = $request->getQuery();
        $this->assertStringContainsString("attributeContent('name', 'code')", $query);
        $this->assertStringNotContainsString('attributeContentAll()', $query);
    }

    public function testFloatPrecisionDoesNotUseScientificNotation(): void
    {
        $request = (new QueryBuilder('Product'))
            ->filterByAttribute('tiny', 0.0000001)
            ->build();

        $this->assertCount(1, iterator_to_array($request->getPositionalQueryParams()));
    }
}
