<?php

declare(strict_types=1);

namespace IblockElementReader\Repository;

use Bitrix\Iblock\SectionTable;
use IblockElementReader\Model\Section;
use IblockElementReader\Normalizer\IdsNormalizer;

class SectionRepository
{
    /**
     * @param list<mixed> $sectionIds
     * @return array<int, Section>
     */
    public function findByIds(array $sectionIds): array
    {
        $ids = IdsNormalizer::unique($sectionIds);
        if ($ids === []) {
            return [];
        }

        $rows = SectionTable::getList([
            'select' => ['ID', 'IBLOCK_ID', 'NAME', 'CODE', 'SORT', 'IBLOCK_SECTION_ID', 'ACTIVE'],
            'filter' => ['=ID' => $ids],
        ])->fetchAll();

        $byId = [];
        foreach ($rows as $row) {
            $section = Section::fromArray($row);
            $byId[$section->id] = $section;
        }

        return $byId;
    }
}
