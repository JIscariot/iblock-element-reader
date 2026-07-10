<?php

declare(strict_types=1);

namespace IblockElementReader\Model;

final readonly class IblockElementCollection implements \Countable
{
    /**
     * @param IblockElement[] $elements
     */
    public function __construct(
        public array $elements,
    ) {
    }

    public function count(): int
    {
        return count($this->elements);
    }
}
