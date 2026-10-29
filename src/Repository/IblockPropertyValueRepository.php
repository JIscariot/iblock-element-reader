<?php

declare(strict_types=1);

namespace IblockElementReader\Repository;

use Bitrix\Iblock\ElementPropertyTable;
use Bitrix\Main\Application;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;
use IblockElementReader\Model\Record\IblockPropertyValueRecord;

final class IblockPropertyValueRepository
{
    /**
     * @param int[] $elementIds
     * @param array<int, IblockPropertyDefinitionRecord> $definitions
     * @return array<int, IblockPropertyValueRecord[]>
     */
    public function findByElements(int $iblockId, array $elementIds, array $definitions): array
    {
        if ($definitions === [] || $elementIds === []) {
            return [];
        }

        return $this->isSeparateStorage($definitions)
            ? $this->findSeparate($iblockId, $elementIds, $definitions)
            : $this->findCommon($elementIds, $definitions);
    }

    /**
     * @param array<int, IblockPropertyDefinitionRecord> $definitions
     */
    private function isSeparateStorage(array $definitions): bool
    {
        foreach ($definitions as $definition) {
            return $definition->version === 2;
        }

        return true;
    }

    /**
     * @param int[] $elementIds
     * @param array<int, IblockPropertyDefinitionRecord> $definitions
     * @return array<int, IblockPropertyValueRecord[]>
     */
    private function findCommon(array $elementIds, array $definitions): array
    {
        $rows = ElementPropertyTable::getList([
            'select' => ['IBLOCK_ELEMENT_ID', 'IBLOCK_PROPERTY_ID', 'VALUE', 'DESCRIPTION'],
            'filter' => [
                '=IBLOCK_ELEMENT_ID'  => $elementIds,
                '=IBLOCK_PROPERTY_ID' => array_keys($definitions),
            ],
            // порядок значений множественного свойства — как в БД (по ID значения)
            'order' => ['ID' => 'ASC'],
        ])->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $propId = (int)$row['IBLOCK_PROPERTY_ID'];
            $definition = $definitions[$propId] ?? null;
            if ($definition === null) {
                continue;
            }

            $map[(int)$row['IBLOCK_ELEMENT_ID']][] = new IblockPropertyValueRecord(
                definition: $definition,
                value: $row['VALUE'],
                description: $definition->withDescription ? $row['DESCRIPTION'] : null,
            );
        }

        return $map;
    }

    /**
     * @param int[] $elementIds
     * @param array<int, IblockPropertyDefinitionRecord> $definitions
     * @return array<int, IblockPropertyValueRecord[]>
     */
    private function findSeparate(int $iblockId, array $elementIds, array $definitions): array
    {
        $map = [];
        $this->appendSingleValues($iblockId, $elementIds, $definitions, $map);
        $this->appendMultipleValues($iblockId, $elementIds, $definitions, $map);

        return $map;
    }

    /**
     * @param int[] $elementIds
     * @param array<int, IblockPropertyDefinitionRecord> $definitions
     * @param array<int, IblockPropertyValueRecord[]> $map
     */
    private function appendSingleValues(int $iblockId, array $elementIds, array $definitions, array &$map): void
    {
        $single = array_filter(
            $definitions,
            static fn(IblockPropertyDefinitionRecord $d): bool => !$d->multiple,
        );
        if ($single === []) {
            return;
        }

        $columns = $this->separateStorageSingleColumns($single);

        $connection = Application::getConnection();
        $table = 'b_iblock_element_prop_s' . $iblockId;

        $idsSql = implode(',', $elementIds);
        $select = implode(', ', $columns);
        $rows = $connection->query(
            "SELECT {$select} FROM {$table} WHERE IBLOCK_ELEMENT_ID IN ({$idsSql})"
        )->fetchAll();

        foreach ($rows as $row) {
            $elementId = (int)$row['IBLOCK_ELEMENT_ID'];

            foreach ($single as $propId => $definition) {
                $column = 'PROPERTY_' . $propId;
                if (!array_key_exists($column, $row)) {
                    continue;
                }

                $value = $row[$column];
                if ($value === null || $value === '') {
                    continue;
                }

                $map[$elementId][] = new IblockPropertyValueRecord(
                    definition: $definition,
                    value: $value,
                    description: $definition->withDescription
                        ? ($row['DESCRIPTION_' . $propId] ?? null)
                        : null,
                );
            }
        }
    }

    /**
     * @param int[] $elementIds
     * @param array<int, IblockPropertyDefinitionRecord> $definitions
     * @param array<int, IblockPropertyValueRecord[]> $map
     */
    private function appendMultipleValues(int $iblockId, array $elementIds, array $definitions, array &$map): void
    {
        $multiple = array_filter(
            $definitions,
            static fn(IblockPropertyDefinitionRecord $d): bool => $d->multiple,
        );
        if ($multiple === []) {
            return;
        }

        $connection = Application::getConnection();
        $table = 'b_iblock_element_prop_m' . $iblockId;

        $idsSql = implode(',', $elementIds);
        $propIdsSql = implode(',', array_keys($multiple));
        $rows = $connection->query(
            "SELECT IBLOCK_ELEMENT_ID, IBLOCK_PROPERTY_ID, VALUE, DESCRIPTION
             FROM {$table}
             WHERE IBLOCK_ELEMENT_ID IN ({$idsSql})
               AND IBLOCK_PROPERTY_ID IN ({$propIdsSql})
             ORDER BY ID"
        )->fetchAll();

        foreach ($rows as $row) {
            $propId = (int)$row['IBLOCK_PROPERTY_ID'];
            $definition = $definitions[$propId] ?? null;
            if ($definition === null) {
                continue;
            }

            $map[(int)$row['IBLOCK_ELEMENT_ID']][] = new IblockPropertyValueRecord(
                definition: $definition,
                value: $row['VALUE'],
                description: $definition->withDescription ? $row['DESCRIPTION'] : null,
            );
        }
    }

    /**
     * @param array<int, IblockPropertyDefinitionRecord> $single
     * @return list<string>
     */
    private function separateStorageSingleColumns(array $single): array
    {
        $columns = ['IBLOCK_ELEMENT_ID'];

        foreach ($single as $propId => $definition) {
            $columns[] = 'PROPERTY_' . (int)$propId;

            if ($definition->withDescription) {
                $columns[] = 'DESCRIPTION_' . (int)$propId;
            }
        }

        return $columns;
    }
}
