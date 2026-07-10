<?php

declare(strict_types=1);

namespace IblockElementReader\Model;

final readonly class IblockPropertyValue
{
    public function __construct(
        public mixed $raw = null,
        public mixed $resolved = null,
        public ?string $description = null,
    ) {
    }
}
