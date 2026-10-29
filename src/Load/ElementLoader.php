<?php

declare(strict_types=1);

namespace IblockElementReader\Load;

use IblockElementReader\Model\Record\IblockElementRecord;
use IblockElementReader\Model\Record\IblockElementRecordCollection;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;
use IblockElementReader\Model\Record\IblockPropertyValueRecord;
use IblockElementReader\Model\Record\IblockPropertyValueRecordCollection;
use IblockElementReader\Normalizer\IdsNormalizer;
use IblockElementReader\Repository\IblockElementRepository;
use IblockElementReader\Repository\IblockPropertyDefinitionRepository;
use IblockElementReader\Repository\IblockPropertyValueRepository;
use IblockElementReader\Resolve\PropertySpecParser;

final class ElementLoader implements ElementLoaderInterface
{
    public function __construct(
        private readonly FieldSpecParser $fieldSpecParser = new FieldSpecParser(),
        private readonly PropertySpecParser $propertySpecParser = new PropertySpecParser(),
        private readonly IblockElementRepository $elementRepository = new IblockElementRepository(),
        private readonly IblockPropertyValueRepository $propertyValueRepository = new IblockPropertyValueRepository(),
        private readonly IblockPropertyDefinitionRepository $propertyDefinitionRepository = new IblockPropertyDefinitionRepository(),
    ) {
    }

    /**
     * @param int[] $iblockIds  пустой = без фильтра IBLOCK_ID (scope из filter(id: ...))
     * @param int[] $ids        пустой = все элементы в scope
     * @param string[]|null $fields  null = только ID
     * @param string[]|null $props  null = свойства не запрашивались
     */
    public function load(array $iblockIds, array $ids, ?array $fields, ?array $props): IblockElementRecordCollection
    {
        $ids = IdsNormalizer::unique($ids);

        $fieldSpecs = $this->fieldSpecParser->parse($fields);
        $select = $this->fieldSpecParser->selectNames($fieldSpecs);
        // служебные поля: выбираются всегда, тем же запросом
        foreach (['IBLOCK_ID', 'IBLOCK_SECTION_ID'] as $serviceField) {
            if (!in_array($serviceField, $select, true)) {
                $select[] = $serviceField;
            }
        }

        $baseRows = $this->elementRepository->findByFilter($iblockIds, $ids, $select);
        if ($baseRows === []) {
            return new IblockElementRecordCollection();
        }

        if ($ids === []) {
            $ids = array_keys($baseRows);
        }

        $valuesByElement = $props !== null
            ? $this->loadAllPropertyValues($baseRows, $ids, $props)
            : [];

        $elements = [];
        foreach ($ids as $id) {
            if (!isset($baseRows[$id])) {
                continue;
            }

            $row = $baseRows[$id];
            $iblockId = (int)$row['IBLOCK_ID'];
            $fieldData = [];
            foreach ($fieldSpecs as $spec) {
                if (array_key_exists($spec->name, $row)) {
                    $fieldData[$spec->name] = $row[$spec->name];
                }
            }

            $propertyValues = [];
            foreach ($valuesByElement[$id] ?? [] as $propertyValue) {
                $propertyValues[] = $propertyValue;
            }

            $elements[] = new IblockElementRecord(
                id: $id,
                iblockId: $iblockId,
                fields: $fieldData,
                properties: new IblockPropertyValueRecordCollection(...$propertyValues),
                iblockSectionId: self::sectionId($row['IBLOCK_SECTION_ID'] ?? null),
            );
        }

        return new IblockElementRecordCollection(...$elements);
    }

    private static function sectionId(mixed $value): ?int
    {
        $id = is_numeric($value) ? (int)$value : 0;

        return $id > 0 ? $id : null;
    }

    /**
     * @param array<int, array<string, mixed>> $baseRows
     * @param int[] $ids
     * @param string[] $props
     * @return array<int, IblockPropertyValueRecord[]>
     */
    private function loadAllPropertyValues(array $baseRows, array $ids, array $props): array
    {
        /** @var array<int, int[]> $idsByIblock */
        $idsByIblock = [];
        foreach ($ids as $id) {
            if (!isset($baseRows[$id])) {
                continue;
            }

            $iblockId = (int)$baseRows[$id]['IBLOCK_ID'];
            $idsByIblock[$iblockId][] = $id;
        }

        $valuesByElement = [];
        foreach ($idsByIblock as $iblockId => $iblockElementIds) {
            $definitionsById = $this->loadDefinitionsForIblock($iblockId, $props);
            if ($definitionsById === []) {
                continue;
            }

            $iblockValues = $this->propertyValueRepository->findByElements(
                $iblockId,
                $iblockElementIds,
                $definitionsById,
            );
            foreach ($iblockValues as $elementId => $values) {
                $valuesByElement[$elementId] = $values;
            }
        }

        return $valuesByElement;
    }

    /**
     * @param string[] $props
     * @return array<int, IblockPropertyDefinitionRecord>
     */
    private function loadDefinitionsForIblock(int $iblockId, array $props): array
    {
        $definitionsById = null;
        $iblockProps = $props;

        if ($this->propertySpecParser->needsExpansion($props)) {
            $definitionsById = $this->propertyDefinitionRepository->findByCodes($iblockId, []);
            $allCodes = array_values(array_map(
                static fn(IblockPropertyDefinitionRecord $d): string => $d->code,
                $definitionsById,
            ));
            $iblockProps = $this->propertySpecParser->expandWildcard($props, $allCodes);
        }

        $propSpecs = $this->propertySpecParser->parse($iblockProps);
        if ($propSpecs === []) {
            return [];
        }

        $codes = array_map(static fn($s) => $s->code, $propSpecs);

        if ($definitionsById !== null) {
            return $this->propertyDefinitionRepository->filterByCodes($definitionsById, $codes);
        }

        return $this->propertyDefinitionRepository->findByCodes($iblockId, $codes);
    }
}
