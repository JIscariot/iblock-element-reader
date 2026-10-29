<?php

declare(strict_types=1);

namespace IblockElementReader\Model;

/** Раздел инфоблока — resolved для свойства типа G (привязка к разделу). */
final readonly class Section
{
    public function __construct(
        public int $id,
        public int $iblockId,
        public ?string $name = null,
        public ?string $code = null,
        public ?int $sort = null,
        public ?int $parentId = null,
        public ?bool $isActive = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row  строка SectionTable
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: (int)$row['ID'],
            iblockId: (int)$row['IBLOCK_ID'],
            name: self::nullableString($row['NAME'] ?? null),
            code: self::nullableString($row['CODE'] ?? null),
            sort: isset($row['SORT']) && $row['SORT'] !== '' ? (int)$row['SORT'] : null,
            parentId: isset($row['IBLOCK_SECTION_ID']) && (int)$row['IBLOCK_SECTION_ID'] > 0
                ? (int)$row['IBLOCK_SECTION_ID']
                : null,
            isActive: isset($row['ACTIVE']) && $row['ACTIVE'] !== ''
                ? $row['ACTIVE'] === 'Y' || $row['ACTIVE'] === true
                : null,
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string)$value;
    }
}
