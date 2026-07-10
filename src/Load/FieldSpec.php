<?php

declare(strict_types=1);

namespace IblockElementReader\Load;

final readonly class FieldSpec
{
    /** Без .VALUE — как есть; с .VALUE — авто-резолв (File для картинок). */
    public function __construct(
        public string $name,
        public bool $autoResolve,
    ) {
    }
}
