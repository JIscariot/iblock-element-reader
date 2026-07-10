<?php

declare(strict_types=1);

namespace IblockElementReader\Repository;

use Bitrix\Iblock\ElementTable;

final class IblockElementRepository
{
    /**
     * @param int[] $iblockIds  пустой = без фильтра IBLOCK_ID
     * @param int[] $ids        пустой = без фильтра ID
     * @param string[] $select
     * @return array<int, array<string, mixed>>
     */
    public function findByFilter(array $iblockIds, array $ids, array $select): array
    {
        $filter = [];
        if ($iblockIds !== []) {
            $filter['=IBLOCK_ID'] = count($iblockIds) === 1 ? $iblockIds[0] : $iblockIds;
        }
        if ($ids !== []) {
            $filter['=ID'] = $ids;
        }

        $rows = ElementTable::getList([
            'select' => $select,
            'filter' => $filter,
            'order'  => ['ID' => 'ASC'],
        ])->fetchAll();

        $byId = [];
        foreach ($rows as $row) {
            $byId[(int)$row['ID']] = $row;
        }

        return $byId;
    }
}
