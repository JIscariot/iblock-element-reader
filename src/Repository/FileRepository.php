<?php

declare(strict_types=1);

namespace IblockElementReader\Repository;

use Bitrix\Main\FileTable;
use IblockElementReader\Normalizer\IdsNormalizer;

final class FileRepository
{
    /**
     * @param list<mixed> $fileIds
     * @return array<int, array<string, mixed>>
     */
    public function findByIds(array $fileIds): array
    {
        $ids = IdsNormalizer::unique($fileIds);
        if ($ids === []) {
            return [];
        }

        $rows = FileTable::getList([
            'select' => ['ID', 'SUBDIR', 'FILE_NAME', 'FILE_SIZE', 'WIDTH', 'HEIGHT'],
            'filter' => ['=ID' => $ids],
        ])->fetchAll();

        $byId = [];
        foreach ($rows as $row) {
            $byId[(int)$row['ID']] = $row;
        }

        return $byId;
    }
}
