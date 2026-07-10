<?php

declare(strict_types=1);

namespace IblockElementReader\Model\Record;

/**
 * @implements \IteratorAggregate<int, IblockPropertyValueRecord>
 */
final class IblockPropertyValueRecordCollection implements \IteratorAggregate, \Countable
{
    /** @var IblockPropertyValueRecord[] */
    private array $items;

    /** @var array<string, IblockPropertyValueRecord[]> */
    private array $byCode = [];

    /** @var array<string, IblockPropertyDefinitionRecord> */
    private array $definitions = [];

    public function __construct(IblockPropertyValueRecord ...$items)
    {
        $this->items = $items;

        foreach ($items as $item) {
            $code = $item->definition->code;
            $this->byCode[$code][] = $item;
            $this->definitions[$code] ??= $item->definition;
        }
    }

    public function has(string $code): bool
    {
        return isset($this->byCode[$code]);
    }

    public function definition(string $code): ?IblockPropertyDefinitionRecord
    {
        return $this->definitions[$code] ?? null;
    }

    public function first(string $code): ?IblockPropertyValueRecord
    {
        return $this->byCode[$code][0] ?? null;
    }

    /**
     * @return IblockPropertyValueRecord[]
     */
    public function all(string $code): array
    {
        return $this->byCode[$code] ?? [];
    }

    /** Первое сырое значение (single-свойство). */
    public function rawValue(string $code): mixed
    {
        return $this->first($code)?->value;
    }

    /**
     * Все сырые значения (multiple-свойство).
     *
     * @return list<mixed>
     */
    public function rawValues(string $code): array
    {
        return array_map(
            static fn(IblockPropertyValueRecord $item): mixed => $item->value,
            $this->all($code),
        );
    }

    /**
     * @return list<mixed>
     */
    public function descriptions(string $code): array
    {
        return array_map(
            static fn(IblockPropertyValueRecord $item): mixed => $item->description,
            $this->all($code),
        );
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }
}
