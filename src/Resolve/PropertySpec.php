<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve;

/**
 * Распарсенная запись from withProps.
 *
 * Без .VALUE — как есть. С .VALUE: USER_TYPE resolver > E/F/L preload > N→int|float.
 */
final readonly class PropertySpec
{
    /**
     * @param PropertyPath[] $nested  dot-path хвост: STORE.VALUE.[ADDRESS]
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
