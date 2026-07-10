<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve\BuiltIn;

use IblockElementReader\Load\FieldSpec;
use IblockElementReader\Load\FieldSpecParser;
use IblockElementReader\Normalizer\IdsNormalizer;
use IblockElementReader\Repository\IblockElementRepository;

final class ElementValueResolver
{
    public function __construct(
        private readonly FieldSpecParser $fieldSpecParser = new FieldSpecParser(),
        private readonly IblockElementRepository $elementRepository = new IblockElementRepository(),
        private readonly FileValueResolver $fileValueResolver = new FileValueResolver(),
    ) {
    }

    /**
     * @param list<mixed> $elementIds
     * @param FieldSpec[] $fieldSpecs
     * @return array<int, array<string, mixed>>  elementId => resolved fields
     */
    public function resolve(array $elementIds, array $fieldSpecs): array
    {
        $ids = IdsNormalizer::unique($elementIds);
        if ($ids === []) {
            return [];
        }

        $select = $this->fieldSpecParser->selectNames($fieldSpecs);
        $rows = $this->elementRepository->findByFilter([], $ids, $select);

        $fileIds = [];
        foreach ($rows as $row) {
            foreach ($fieldSpecs as $spec) {
                if (!$spec->autoResolve) {
                    continue;
                }
                $value = $row[$spec->name] ?? null;
                if ($value !== null && $value !== '' && (int)$value > 0) {
                    $fileIds[] = (int)$value;
                }
            }
        }

        $files = $this->fileValueResolver->resolve($fileIds);

        $map = [];
        foreach ($rows as $row) {
            $id = (int)$row['ID'];
            $fields = [];

            foreach ($fieldSpecs as $spec) {
                if (!array_key_exists($spec->name, $row)) {
                    continue;
                }

                $raw = $row[$spec->name];
                $fields[$spec->name] = $spec->autoResolve && (int)$raw > 0
                    ? ($files[(int)$raw] ?? null)
                    : $raw;
            }

            $map[$id] = $fields;
        }

        return $map;
    }
}
