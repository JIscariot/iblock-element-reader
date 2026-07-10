<?php

declare(strict_types=1);

namespace IblockElementReader\Load;

use IblockElementReader\Enum\PropertyTypeEnum;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;
use IblockElementReader\Repository\IblockPropertyDefinitionRepository;
use IblockElementReader\Resolve\PropertyPath;
use IblockElementReader\Resolve\PropertySpec;

/** Собирает IblockPropertyDefinitionRecord для nested dot-path (связанные инфоблоки). */
final class NestedDefinitionLoader
{
    public function __construct(
        private readonly IblockPropertyDefinitionRepository $propertyDefinitionRepository = new IblockPropertyDefinitionRepository(),
    ) {
    }

    /**
     * @param PropertySpec[] $propSpecs
     * @param array<string, IblockPropertyDefinitionRecord> $rootDefinitionsByCode
     * @return array<int, array<string, IblockPropertyDefinitionRecord>>
     */
    public function load(array $propSpecs, array $rootDefinitionsByCode): array
    {
        $codesByIblock = [];

        foreach ($propSpecs as $spec) {
            if (!$spec->hasNested()) {
                continue;
            }

            $rootDefinition = $rootDefinitionsByCode[$spec->code] ?? null;
            if ($rootDefinition === null) {
                continue;
            }

            $this->collectNestedCodes($spec->nested, $rootDefinition, $codesByIblock);
        }

        if ($codesByIblock === []) {
            return [];
        }

        $definitionsByIblock = [];
        foreach ($codesByIblock as $iblockId => $codes) {
            $codes = array_values(array_unique($codes));
            if ($codes === []) {
                continue;
            }

            $definitionsByIblock[$iblockId] = $this->propertyDefinitionRepository->indexByCode(
                $this->propertyDefinitionRepository->findByCodes($iblockId, $codes),
            );
        }

        return $definitionsByIblock;
    }

    /**
     * @param PropertyPath[] $paths
     * @param array<int, list<string>> $codesByIblock
     */
    private function collectNestedCodes(array $paths, IblockPropertyDefinitionRecord $parentDefinition, array &$codesByIblock): void
    {
        if ($parentDefinition->type !== PropertyTypeEnum::ELEMENT) {
            return;
        }

        $linkIblockId = $parentDefinition->linkIblockId;
        if ($linkIblockId === null || $linkIblockId <= 0) {
            return;
        }

        $codes = array_map(static fn(PropertyPath $path): string => $path->code, $paths);
        $codesByIblock[$linkIblockId] = array_values(array_unique([
            ...($codesByIblock[$linkIblockId] ?? []),
            ...$codes,
        ]));

        $nestedPaths = array_values(array_filter(
            $paths,
            static fn(PropertyPath $path): bool => $path->hasNested(),
        ));
        if ($nestedPaths === []) {
            return;
        }

        $definitionsByCode = $this->propertyDefinitionRepository->indexByCode(
            $this->propertyDefinitionRepository->findByCodes($linkIblockId, $codes),
        );

        foreach ($nestedPaths as $path) {
            $childDefinition = $definitionsByCode[$path->code] ?? null;
            if ($childDefinition === null) {
                continue;
            }

            $this->collectNestedCodes($path->nested, $childDefinition, $codesByIblock);
        }
    }
}
