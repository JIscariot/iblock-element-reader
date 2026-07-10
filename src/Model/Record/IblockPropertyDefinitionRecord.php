<?php

declare(strict_types=1);

namespace IblockElementReader\Model\Record;

use IblockElementReader\Enum\PropertyTypeEnum;

/** Метаданные свойства (b_iblock_property). */
final readonly class IblockPropertyDefinitionRecord
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public PropertyTypeEnum $type,
        public bool $multiple,
        public ?string $userType = null,
        public ?int $linkIblockId = null,
        public int $version = 2,
        public bool $withDescription = false,
    ) {
    }

    /**
     * @param array<string, mixed> $row  строка PropertyTable
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: (int)$row['ID'],
            code: (string)$row['CODE'],
            name: (string)$row['NAME'],
            type: PropertyTypeEnum::tryFrom((string)$row['PROPERTY_TYPE'])
                ?? PropertyTypeEnum::STRING,
            multiple: ($row['MULTIPLE'] ?? 'N') === 'Y',
            userType: isset($row['USER_TYPE']) && $row['USER_TYPE'] !== ''
                ? (string)$row['USER_TYPE']
                : null,
            linkIblockId: isset($row['LINK_IBLOCK_ID']) && $row['LINK_IBLOCK_ID'] !== null
                ? (int)$row['LINK_IBLOCK_ID']
                : null,
            version: (int)($row['VERSION'] ?? 2),
            withDescription: ($row['WITH_DESCRIPTION'] ?? 'N') === 'Y',
        );
    }
}
