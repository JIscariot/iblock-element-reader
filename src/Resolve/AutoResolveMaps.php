<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve;

use IblockElementReader\Model\Enumerate;
use IblockElementReader\Model\File;
use IblockElementReader\Model\Section;

/** Предзагруженные карты для batch-резолва за один проход. */
final readonly class AutoResolveMaps
{
    /**
     * @param array<int, File>                          $files
     * @param array<int, Enumerate>                     $enums
     * @param array<int, array<string, mixed>>          $elements
     * @param array<int, Section>                       $sections
     */
    public function __construct(
        public array $files = [],
        public array $enums = [],
        public array $elements = [],
        public array $sections = [],
    ) {
    }
}
