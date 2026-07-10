<?php

declare(strict_types=1);

namespace IblockElementReader\Model;

/** Значение свойства типа L (список). */
final readonly class Enumerate
{
    public function __construct(
        public int $id,
        public string $value,
        public ?string $xmlId = null,
    ) {
    }
}
