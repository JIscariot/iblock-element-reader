<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Fixture;

use IblockElementReader\Model\Section;
use IblockElementReader\Normalizer\IdsNormalizer;
use IblockElementReader\Repository\SectionRepository;

/** Разделы без обращения к SectionTable; считает запросы. */
final class InMemorySectionRepository extends SectionRepository
{
    /** @var array<int, Section> */
    private array $sections = [];

    /** @var list<list<int>> ID из каждого «запроса» */
    public array $queries = [];

    public function __construct(Section ...$sections)
    {
        foreach ($sections as $section) {
            $this->sections[$section->id] = $section;
        }
    }

    public function findByIds(array $sectionIds): array
    {
        $ids = IdsNormalizer::unique($sectionIds);
        if ($ids === []) {
            return [];
        }

        $this->queries[] = $ids;

        return array_intersect_key($this->sections, array_flip($ids));
    }
}
