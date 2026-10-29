<?php

declare(strict_types=1);

namespace IblockElementReader;

use IblockElementReader\Load\ElementLoader;
use IblockElementReader\Load\ElementLoaderInterface;
use IblockElementReader\Model\IblockElementCollection;
use IblockElementReader\Resolve\Contract\UserTypePropertyResolver;
use IblockElementReader\Resolve\LinkedElementCache;
use IblockElementReader\Resolve\PropertyResolverRegistry;
use IblockElementReader\Resolve\ResolvePipeline;


final class IblockElementFetcher
{
    public function __construct(
        private readonly ElementLoaderInterface $loader = new ElementLoader(),
    ) {
    }

    /**
     * @param int[] $iblockIds
     * @param int[] $elementIds
     * @param string[]|null $fields
     * @param string[]|null $props
     * @param UserTypePropertyResolver[] $resolvers
     */
    public function fetch(
        array $iblockIds,
        array $elementIds,
        ?array $fields,
        ?array $props,
        array $resolvers = [],
    ): IblockElementCollection {

        $elements = $this->loader->load($iblockIds, $elementIds, $fields, $props);

        if ($elements->count() === 0) {
            return new IblockElementCollection([]);
        }

        $order = $elementIds !== [] ? $elementIds : $elements->ids();

        return (new ResolvePipeline(
            resolverRegistry: new PropertyResolverRegistry($resolvers),
            linkedElementCache: new LinkedElementCache(),
            elementLoader: $this->loader,
        ))->resolve(
            elements: $elements,
            order: $order,
            fields: $fields,
            props: $props,
        );
    }
}

