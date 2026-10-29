<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve;

use IblockElementReader\Model\IblockElement;
use IblockElementReader\Load\ElementLoader;
use IblockElementReader\Load\ElementLoaderInterface;
use IblockElementReader\Repository\IblockPropertyDefinitionRepository;
use IblockElementReader\Resolve\Contract\AutoResolveMapsLoaderInterface;
use IblockElementReader\Normalizer\IdsNormalizer;

/**
 * Рекурсивная выборка связанного элемента (E) в другом инфоблоке.
 */
final class NestedPropertyResolver
{
    public function __construct(
        private readonly PropertyResolverRegistry $resolverRegistry = new PropertyResolverRegistry(),
        private readonly LinkedElementCache $linkedElementCache = new LinkedElementCache(),
        private readonly ElementLoaderInterface $elementLoader = new ElementLoader(),
        private readonly IblockPropertyDefinitionRepository $propertyDefinitionRepository = new IblockPropertyDefinitionRepository(),
        private readonly ?AutoResolveMapsLoaderInterface $mapsLoader = null,
    ) {
    }

    /**
     * @param list<mixed>      $elementIds
     * @param PropertyPath[]   $nestedPaths
     * @param string[]|null    $fields  as in withFields() родительского запроса
     * @return array<int, IblockElement>
     */
    public function resolve(
        int $linkIblockId,
        array $elementIds,
        array $nestedPaths = [],
        ?array $fields = null,
    ): array {
        $elementIds = IdsNormalizer::unique($elementIds);
        if ($elementIds === []) {
            return [];
        }

        $signature = LinkedElementCache::loadSignature($fields, $nestedPaths);

        $missing = [];
        $result = [];

        foreach ($elementIds as $id) {
            $cached = $this->linkedElementCache->get($linkIblockId, $id, $signature);
            if ($cached !== null) {
                $result[$id] = $cached;
                continue;
            }

            $missing[] = $id;
        }

        if ($missing !== []) {
            foreach ($this->loadElements($linkIblockId, $missing, $nestedPaths, $fields) as $id => $element) {
                $this->linkedElementCache->set($linkIblockId, $id, $signature, $element);
                $result[$id] = $element;
            }
        }

        return $result;
    }

    /**
     * @param int[]            $elementIds
     * @param PropertyPath[]   $nestedPaths
     * @param string[]|null    $fields
     * @return array<int, IblockElement>
     */
    private function loadElements(
        int $linkIblockId,
        array $elementIds,
        array $nestedPaths,
        ?array $fields,
    ): array {
        $propStrings = $nestedPaths === []
            ? null
            : array_map(
                static fn(PropertyPath $path): string => PropertyPathParser::toPropString($path),
                $nestedPaths,
            );

        $elements = $this->elementLoader->load([$linkIblockId], $elementIds, $fields, $propStrings);

        $collection = (new ResolvePipeline(
            resolverRegistry: $this->resolverRegistry,
            linkedElementCache: $this->linkedElementCache,
            mapsLoader: $this->mapsLoader,
            propertyDefinitionRepository: $this->propertyDefinitionRepository,
            elementLoader: $this->elementLoader,
        ))->resolve(
            elements: $elements,
            order: $elementIds,
            fields: $fields,
            props: $propStrings,
        );

        $byId = [];
        foreach ($collection->elements as $element) {
            $byId[$element->id] = $element;
        }

        return $byId;
    }
}
