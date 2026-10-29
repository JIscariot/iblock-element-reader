<?php

declare(strict_types=1);

namespace IblockElementReader\Model\Record;

/**
 * Элемент инфоблока в сыром виде: родные поля + значения свойств из БД.
 * Фаза резолва превращает IblockElementRecord → IblockElement (model).
 */
final readonly class IblockElementRecord
{
    /**
     * @param array<string, mixed> $fields  родные поля (ID, NAME, PREVIEW_PICTURE, …)
     * @param int|null $iblockSectionId  основной раздел (IBLOCK_SECTION_ID), null — без раздела
     */
    public function __construct(
        public int $id,
        public int $iblockId,
        public array $fields,
        public IblockPropertyValueRecordCollection $properties,
        public ?int $iblockSectionId = null,
    ) {
    }

    public function field(string $key): mixed
    {
        return $this->fields[$key] ?? null;
    }

    public function rawProp(string $code): mixed
    {
        return $this->properties->rawValue($code);
    }
}
