<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Wtsvk\EvitaDbClient\EntityFetch;

#[RequiresPhpExtension('grpc')]
#[RequiresPhpExtension('protobuf')]
final class EntityFetchTest extends TestCase
{
    public function testAllProducesNoParams(): void
    {
        $fetch = EntityFetch::all();
        $ql = $fetch->toEvitaQL();
        $params = $fetch->getParams();

        $this->assertStringContainsString('attributeContentAll()', $ql);
        $this->assertStringContainsString('associatedDataContentAll()', $ql);
        $this->assertStringContainsString('priceContentAll()', $ql);
        $this->assertStringContainsString('referenceContentAll()', $ql);
        $this->assertSame([], $params);
    }

    public function testEmptyFetchProducesNoParams(): void
    {
        $fetch = new EntityFetch();
        $ql = $fetch->toEvitaQL();

        $this->assertSame('entityFetch()', $ql);
        $this->assertSame([], $fetch->getParams());
    }

    public function testAttributeContentUsesPlaceholders(): void
    {
        $fetch = (new EntityFetch())->attributeContent('name', 'code');
        $ql = $fetch->toEvitaQL();
        $params = $fetch->getParams();

        $this->assertStringContainsString('attributeContent(?, ?)', $ql);
        $this->assertStringNotContainsString("'", $ql);
        $this->assertCount(2, $params);
        $this->assertSame('name', $params[0]->getStringValue());
        $this->assertSame('code', $params[1]->getStringValue());
    }

    public function testAssociatedDataContentUsesPlaceholders(): void
    {
        $fetch = (new EntityFetch())->associatedDataContent('images', 'shopUrls');
        $ql = $fetch->toEvitaQL();
        $params = $fetch->getParams();

        $this->assertStringContainsString('associatedDataContent(?, ?)', $ql);
        $this->assertCount(2, $params);
        $this->assertSame('images', $params[0]->getStringValue());
        $this->assertSame('shopUrls', $params[1]->getStringValue());
    }

    public function testReferenceContentUsesPlaceholders(): void
    {
        $fetch = (new EntityFetch())->referenceContent('Brand', 'Category');
        $ql = $fetch->toEvitaQL();
        $params = $fetch->getParams();

        $this->assertStringContainsString('referenceContent(?, ?)', $ql);
        $this->assertCount(2, $params);
        $this->assertSame('Brand', $params[0]->getStringValue());
        $this->assertSame('Category', $params[1]->getStringValue());
    }

    public function testDataInLocalesUsesPlaceholders(): void
    {
        $fetch = (new EntityFetch())->dataInLocales('sk', 'en');
        $ql = $fetch->toEvitaQL();
        $params = $fetch->getParams();

        $this->assertStringContainsString('dataInLocales(?, ?)', $ql);
        $this->assertCount(2, $params);
        $this->assertSame('sk', $params[0]->getStringValue());
        $this->assertSame('en', $params[1]->getStringValue());
    }

    public function testDataInLocalesAllProducesNoParams(): void
    {
        $fetch = (new EntityFetch())->dataInLocalesAll();
        $ql = $fetch->toEvitaQL();

        $this->assertStringContainsString('dataInLocalesAll()', $ql);
        $this->assertSame([], $fetch->getParams());
    }

    public function testMixedContentCollectsAllParams(): void
    {
        $fetch = (new EntityFetch())
            ->attributeContent('name')
            ->associatedDataContent('images')
            ->priceContentAll()
            ->referenceContent('Brand')
            ->dataInLocales('sk');

        $ql = $fetch->toEvitaQL();
        $params = $fetch->getParams();

        $this->assertStringContainsString('attributeContent(?)', $ql);
        $this->assertStringContainsString('associatedDataContent(?)', $ql);
        $this->assertStringContainsString('priceContentAll()', $ql);
        $this->assertStringContainsString('referenceContent(?)', $ql);
        $this->assertStringContainsString('dataInLocales(?)', $ql);
        $this->assertCount(4, $params);
        $this->assertSame('name', $params[0]->getStringValue());
        $this->assertSame('images', $params[1]->getStringValue());
        $this->assertSame('Brand', $params[2]->getStringValue());
        $this->assertSame('sk', $params[3]->getStringValue());
    }

    public function testToEvitaQLResetsParamsOnRerender(): void
    {
        $fetch = (new EntityFetch())->attributeContent('name');

        $fetch->toEvitaQL();
        $firstParams = $fetch->getParams();

        $fetch->toEvitaQL();
        $secondParams = $fetch->getParams();

        $this->assertCount(1, $firstParams);
        $this->assertCount(1, $secondParams);
    }

    public function testNoInlineLiteralsInOutput(): void
    {
        $fetch = (new EntityFetch())
            ->attributeContent('name', 'code')
            ->associatedDataContent('images')
            ->referenceContent('Brand')
            ->dataInLocales('sk', 'en');

        $ql = $fetch->toEvitaQL();

        $this->assertStringNotContainsString("'", $ql);
    }

    public function testPriceContentRespectingFilter(): void
    {
        $fetch = (new EntityFetch())->priceContentRespectingFilter();
        $ql = $fetch->toEvitaQL();

        $this->assertStringContainsString('priceContentRespectingFilter()', $ql);
        $this->assertSame([], $fetch->getParams());
    }

    public function testHierarchyContent(): void
    {
        $fetch = (new EntityFetch())->hierarchyContent();
        $ql = $fetch->toEvitaQL();

        $this->assertStringContainsString('hierarchyContent()', $ql);
        $this->assertSame([], $fetch->getParams());
    }

    public function testToEvitaQLWrapsContentInEntityFetch(): void
    {
        $fetch = (new EntityFetch())
            ->attributeContent('name')
            ->priceContentAll();

        $this->assertSame(
            'entityFetch(attributeContent(?), priceContentAll())',
            $fetch->toEvitaQL(),
        );
    }

    public function testToEvitaQLContentRendersWithoutWrapper(): void
    {
        $fetch = (new EntityFetch())
            ->attributeContent('name')
            ->priceContentAll();

        $ql = $fetch->toEvitaQLContent();
        $params = $fetch->getParams();

        $this->assertSame('attributeContent(?), priceContentAll()', $ql);
        $this->assertCount(1, $params);
        $this->assertSame('name', $params[0]->getStringValue());
    }

    public function testEmptyToEvitaQLContentRendersEmptyString(): void
    {
        $fetch = new EntityFetch();

        $this->assertSame('', $fetch->toEvitaQLContent());
        $this->assertSame([], $fetch->getParams());
    }

    public function testInvalidAttributeNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new EntityFetch())->attributeContent('invalid name');
    }
}
