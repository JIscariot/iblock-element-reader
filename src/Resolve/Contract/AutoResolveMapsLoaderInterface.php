<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve\Contract;

use IblockElementReader\Load\FieldSpec;
use IblockElementReader\Model\Record\IblockElementRecordCollection;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;
use IblockElementReader\Resolve\AutoResolveMaps;
use IblockElementReader\Resolve\PropertyResolverRegistry;
use IblockElementReader\Resolve\PropertySpec;

interface AutoResolveMapsLoaderInterface
{
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
    ): AutoResolveMaps;
}
