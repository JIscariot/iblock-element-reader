<?php

declare(strict_types=1);

namespace IblockElementReader\Model\Record;

/**
 * @implements \IteratorAggregate<int, IblockElementRecord>
 */
final class IblockElementRecordCollection implements \IteratorAggregate, \Countable
{
    /** @var array<int, IblockElementRecord> */
    private array $byId;

    /** @var IblockElementRecord[] */
    private array $items;

    public function __construct(IblockElementRecord ...$elements)
    {
        $this->items = $elements;
        $this->byId = [];

        foreach ($elements as $element) {
            $this->byId[$element->id] = $element;
        }
    }

    public function get(int $id): ?IblockElementRecord
    {
        return $this->byId[$id] ?? null;
    }

    /**
     * @return int[]
     */
    public function ids(): array
    {
        return array_map(static fn(IblockElementRecord $el): int => $el->id, $this->items);
    }

    /**
     * @return int[]
     */
    public function iblockIds(): array
    {
        return array_values(array_unique(
            array_map(static fn(IblockElementRecord $el): int => $el->iblockId, $this->items),
        ));
    }

    public function filterByIblock(int $iblockId): self
    {
        return new self(...array_values(array_filter(
            $this->items,
            static fn(IblockElementRecord $el): bool => $el->iblockId === $iblockId,
        )));
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
