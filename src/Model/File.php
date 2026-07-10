<?php

declare(strict_types=1);

namespace IblockElementReader\Model;

final readonly class File
{
    public function __construct(
        public int $id,
        public string $subdir,
        public string $filename,
        public string $src,
        public int $size,
        public ?int $width = null,
        public ?int $height = null,
    ) {
    }
}
