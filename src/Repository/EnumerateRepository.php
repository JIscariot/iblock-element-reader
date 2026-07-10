<?php

declare(strict_types=1);

namespace IblockElementReader\Repository;

use Bitrix\Iblock\PropertyEnumerationTable;
use IblockElementReader\Normalizer\IdsNormalizer;

final class EnumerateRepository
{
    /**
     * @param list<mixed> $enumIds
     * @return array<int, array<string, mixed>>
     */
    public function findByIds(array $enumIds): array
    {
        $ids = IdsNormalizer::unique($enumIds);
        if ($ids === []) {
            return [];
        }

        $rows = PropertyEnumerationTable::getList([
            'select' => ['ID', 'VALUE', 'XML_ID'],
            'filter' => ['=ID' => $ids],
        ])->fetchAll();

        $byId = [];
        foreach ($rows as $row) {
            $byId[(int)$row['ID']] = $row;
        }

        return $byId;
    }
}
