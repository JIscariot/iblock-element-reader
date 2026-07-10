<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve;

use IblockElementReader\Load\FieldSpec;
use IblockElementReader\Model\Record\IblockElementRecordCollection;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;
use IblockElementReader\Resolve\Contract\AutoResolveMapsLoaderInterface;

/** Фиксированные maps для тестов и точечных override. */
final class InMemoryAutoResolveMapsLoader implements AutoResolveMapsLoaderInterface
{
    public function __construct(
        private readonly AutoResolveMaps $maps = new AutoResolveMaps(),
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
        return $this->maps;
    }
}
