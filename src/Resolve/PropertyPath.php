<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve;

/** Распарсенный dot-path свойства: STORE.VALUE.ADDRESS → STORE + nested [ADDRESS]. */
final readonly class PropertyPath
{
    /**
     * @param PropertyPath[] $nested
     */
    public function __construct(
        public string $code,
        public bool $autoResolve,
        public array $nested = [],
    ) {
    }

    public function hasNested(): bool
    {
        return $this->nested !== [];
    }
}
