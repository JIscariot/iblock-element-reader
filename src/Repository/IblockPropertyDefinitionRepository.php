<?php

declare(strict_types=1);

namespace IblockElementReader\Repository;

use Bitrix\Iblock\PropertyTable;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;

final class IblockPropertyDefinitionRepository
{
    /**
     * @param string[] $codes  пустой = все свойства инфоблока
     * @return array<int, IblockPropertyDefinitionRecord>
     */
    public function findByCodes(int $iblockId, array $codes = []): array
    {
        $filter = ['=IBLOCK_ID' => $iblockId];
        if ($codes !== []) {
            $filter['=CODE'] = array_values(array_unique($codes));
        }

        $rows = PropertyTable::getList([
            'select' => ['ID', 'CODE', 'NAME', 'PROPERTY_TYPE', 'MULTIPLE', 'USER_TYPE', 'LINK_IBLOCK_ID', 'VERSION', 'WITH_DESCRIPTION'],
            'filter' => $filter,
            'cache'  => ['ttl' => 86400],
        ])->fetchAll();

        $byId = [];
        foreach ($rows as $row) {
            $definition = IblockPropertyDefinitionRecord::fromArray($row);
            $byId[$definition->id] = $definition;
        }

        return $byId;
    }

    /**
     * @param array<int, IblockPropertyDefinitionRecord> $byId
     * @param string[] $codes
     * @return array<int, IblockPropertyDefinitionRecord>
     */
    public function filterByCodes(array $byId, array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        $flip = array_flip($codes);

        return array_filter(
            $byId,
            static fn(IblockPropertyDefinitionRecord $definition): bool => isset($flip[$definition->code]),
        );
    }

    /**
     * @param array<int, IblockPropertyDefinitionRecord> $byId
     * @return array<string, IblockPropertyDefinitionRecord>
     */
    public function indexByCode(array $byId): array
    {
        $byCode = [];
        foreach ($byId as $definition) {
            $byCode[$definition->code] = $definition;
        }

        return $byCode;
    }
}
