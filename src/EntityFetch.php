<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient;

use Webmozart\Assert\Assert;

use function array_map;
use function array_values;
use function implode;

/**
 * Mutable fluent builder for EvitaDB entity content requirements.
 *
 * Consumers describe what content they need via fluent methods, and the builder
 * renders the EvitaQL internally via toEvitaQL().
 *
 * Methods mutate $this and return $this. Construct a fresh instance per query
 * rather than reusing a partially-configured one across call-sites — otherwise
 * later additions will leak back to earlier consumers.
 */
final class EntityFetch
{
    private bool $attributeAll = false;

    /**
     * @var list<string>
     */
    private array $attributes = [];

    private bool $associatedDataAll = false;

    /**
     * @var list<string>
     */
    private array $associatedData = [];

    private bool $priceAll = false;

    private bool $priceRespectingFilter = false;

    private bool $referenceAll = false;

    /**
     * @var list<string>
     */
    private array $references = [];

    private bool $hierarchy = false;

    private bool $dataInLocalesAll = false;

    /**
     * @var list<string>
     */
    private array $dataInLocales = [];

    public static function all(): self
    {
        return (new self())
            ->attributeContentAll()
            ->associatedDataContentAll()
            ->priceContentAll()
            ->referenceContentAll();
    }

    public function attributeContentAll(): self
    {
        $this->attributeAll = true;
        $this->attributes = [];

        return $this;
    }

    public function attributeContent(string ...$names): self
    {
        foreach ($names as $name) {
            self::assertValidName($name);
        }

        $this->attributes = array_values([...$this->attributes, ...$names]);

        return $this;
    }

    public function associatedDataContentAll(): self
    {
        $this->associatedDataAll = true;
        $this->associatedData = [];

        return $this;
    }

    public function associatedDataContent(string ...$names): self
    {
        foreach ($names as $name) {
            self::assertValidName($name);
        }

        $this->associatedData = array_values([...$this->associatedData, ...$names]);

        return $this;
    }

    public function priceContentAll(): self
    {
        $this->priceAll = true;
        $this->priceRespectingFilter = false;

        return $this;
    }

    public function priceContentRespectingFilter(): self
    {
        $this->priceRespectingFilter = true;
        $this->priceAll = false;

        return $this;
    }

    public function referenceContentAll(): self
    {
        $this->referenceAll = true;
        $this->references = [];

        return $this;
    }

    public function referenceContent(string ...$names): self
    {
        foreach ($names as $name) {
            self::assertValidName($name);
        }

        $this->references = array_values([...$this->references, ...$names]);

        return $this;
    }

    public function hierarchyContent(): self
    {
        $this->hierarchy = true;

        return $this;
    }

    public function dataInLocalesAll(): self
    {
        $this->dataInLocalesAll = true;
        $this->dataInLocales = [];

        return $this;
    }

    public function dataInLocales(string ...$locales): self
    {
        $this->dataInLocales = array_values([...$this->dataInLocales, ...$locales]);

        return $this;
    }

    /**
     * @internal Renders to EvitaQL string for the gRPC layer.
     */
    public function toEvitaQL(): string
    {
        $parts = [];

        if ($this->attributeAll) {
            $parts[] = 'attributeContentAll()';
        } elseif ($this->attributes !== []) {
            $parts[] = 'attributeContent(' . implode(', ', $this->quoteNames($this->attributes)) . ')';
        }

        if ($this->associatedDataAll) {
            $parts[] = 'associatedDataContentAll()';
        } elseif ($this->associatedData !== []) {
            $parts[] = 'associatedDataContent(' . implode(', ', $this->quoteNames($this->associatedData)) . ')';
        }

        if ($this->priceAll) {
            $parts[] = 'priceContentAll()';
        } elseif ($this->priceRespectingFilter) {
            $parts[] = 'priceContentRespectingFilter()';
        }

        if ($this->referenceAll) {
            $parts[] = 'referenceContentAll()';
        } elseif ($this->references !== []) {
            $parts[] = 'referenceContent(' . implode(', ', $this->quoteNames($this->references)) . ')';
        }

        if ($this->hierarchy) {
            $parts[] = 'hierarchyContent()';
        }

        if ($this->dataInLocalesAll) {
            $parts[] = 'dataInLocalesAll()';
        } elseif ($this->dataInLocales !== []) {
            $parts[] = 'dataInLocales(' . implode(', ', $this->quoteNames($this->dataInLocales)) . ')';
        }

        return 'entityFetch(' . implode(', ', $parts) . ')';
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    private function quoteNames(array $names): array
    {
        return array_map(
            static fn (string $name): string => "'" . $name . "'",
            $names,
        );
    }

    private static function assertValidName(string $name): void
    {
        Assert::regex(
            value: $name,
            pattern: '/^[a-zA-Z][a-zA-Z0-9_]*$/',
            message: 'Invalid EvitaQL identifier: ' . $name,
        );
    }
}
