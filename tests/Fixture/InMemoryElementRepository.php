<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Fixture;

use IblockElementReader\Repository\IblockElementRepository;

/** Строки b_iblock_element без ElementTable; запоминает запросы. */
final class InMemoryElementRepository extends IblockElementRepository
{
    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    /** @var list<array{iblockIds: int[], ids: int[], select: string[]}> */
    public array $queries = [];

    /**
     * @param array<string, mixed> ...$rows  строки с ключами как у ElementTable (ID, IBLOCK_ID, IBLOCK_SECTION_ID, …)
     */
    public function __construct(array ...$rows)
    {
        foreach ($rows as $row) {
            $this->rows[(int)$row['ID']] = $row;
        }
    }

    public function findByFilter(array $iblockIds, array $ids, array $select): array
    {
        $this->queries[] = ['iblockIds' => $iblockIds, 'ids' => $ids, 'select' => $select];

        $result = [];
        foreach ($this->rows as $id => $row) {
            if ($ids !== [] && !in_array($id, $ids, true)) {
                continue;
            }
            if ($iblockIds !== [] && !in_array((int)$row['IBLOCK_ID'], $iblockIds, true)) {
                continue;
            }

            $result[$id] = array_intersect_key($row, array_flip($select));
        }

        ksort($result);

        return $result;
    }
}
