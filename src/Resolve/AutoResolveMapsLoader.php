<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve;

use IblockElementReader\Enum\PropertyTypeEnum;
use IblockElementReader\Resolve\Contract\AutoResolveMapsLoaderInterface;
use IblockElementReader\Load\FieldSpec;
use IblockElementReader\Resolve\BuiltIn\ElementValueResolver;
use IblockElementReader\Resolve\BuiltIn\EnumerateResolver;
use IblockElementReader\Resolve\BuiltIn\FileValueResolver;
use IblockElementReader\Model\Record\IblockElementRecordCollection;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;

final class AutoResolveMapsLoader implements AutoResolveMapsLoaderInterface
{
    public function __construct(
        private readonly RawValueAggregator $aggregator = new RawValueAggregator(),
        private readonly FileValueResolver $fileValueResolver = new FileValueResolver(),
        private readonly EnumerateResolver $enumerateResolver = new EnumerateResolver(),
        private readonly ElementValueResolver $elementValueResolver = new ElementValueResolver(),
    ) {
    }

    /**
     * @param PropertySpec[] $propSpecs
     * @param array<int, array<string, IblockPropertyDefinitionRecord>> $definitionsByIblock
     * @param FieldSpec[] $fieldSpecs
     */
    public function load(
        IblockElementRecordCollection $elements,
        array $propSpecs,
        array $definitionsByIblock,
        array $fieldSpecs,
        PropertyResolverRegistry $resolverRegistry = new PropertyResolverRegistry(),
    ): AutoResolveMaps {
        $fileIds = [];
        $enumIds = [];
        $elementIds = [];

        foreach ($elements as $element) {
            foreach ($fieldSpecs as $spec) {
                if (!$spec->autoResolve) {
                    continue;
                }
                $raw = $element->fields[$spec->name] ?? null;
                if ($raw !== null && $raw !== '' && (int)$raw > 0) {
                    $fileIds[] = (int)$raw;
                }
            }
        }

        foreach ($elements->iblockIds() as $iblockId) {
            $definitionsByCode = $definitionsByIblock[$iblockId] ?? [];
            $iblockElements = $elements->filterByIblock($iblockId);

            foreach ($propSpecs as $spec) {
                if (!$spec->autoResolve) {
                    continue;
                }

                $definition = $definitionsByCode[$spec->code] ?? null;
                if (
                    $definition === null
                    || $resolverRegistry->has($definition)
                    || $spec->hasNested()
                    || $definition->type === PropertyTypeEnum::ELEMENT
                ) {
                    continue;
                }

                $context = $this->aggregator->context($iblockElements, $spec->code, $definition);
                foreach ($context->uniqueValues as $value) {
                    $id = (int)$value;
                    if ($id <= 0) {
                        continue;
                    }

                    match ($definition->type) {
                        PropertyTypeEnum::LIST    => $enumIds[] = $id,
                        PropertyTypeEnum::FILE    => $fileIds[] = $id,
                        PropertyTypeEnum::ELEMENT => $elementIds[] = $id,
                        default => null,
                    };
                }
            }
        }

        return new AutoResolveMaps(
            files: $this->fileValueResolver->resolve($fileIds),
            enums: $this->enumerateResolver->resolve($enumIds),
            elements: $this->elementValueResolver->resolve($elementIds, $fieldSpecs),
        );
    }
}
