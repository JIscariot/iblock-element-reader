<?php

declare(strict_types=1);

namespace IblockElementReader;

use IblockElementReader\Exception\IblockScopeException;
use IblockElementReader\Normalizer\IdsNormalizer;
use IblockElementReader\Model\IblockElement;
use IblockElementReader\Model\IblockElementCollection;
use IblockElementReader\Resolve\Contract\UserTypePropertyResolver;

final class IblockElementReader
{
    private ?array $fields = null;
    private ?array $props = null;
    private array $iblockIds = [];
    private array $elementIds = [];
    private array $resolvers = [];

    public function __construct(
        private readonly IblockElementFetcher $fetcher = new IblockElementFetcher(),
    ) {
    }

    public function filter(int|array|null $id = null, int|array|null $iblock = null): self
    {
        $clone = clone $this;

        if ($iblock !== null) {
            $clone->iblockIds = IdsNormalizer::normalize($iblock);
        }

        if ($id !== null) {
            $clone->elementIds = IdsNormalizer::normalize($id);
        }

        return $clone;
    }

    public function withFields(array $fields): self
    {
        $clone = clone $this;
        $clone->fields = $fields;

        return $clone;
    }

    public function withProps(array $props): self
    {
        $clone = clone $this;
        $clone->props = $props;

        return $clone;
    }

    public function withResolvers(array $resolvers): self
    {
        $clone = clone $this;
        $clone->resolvers = $resolvers;

        return $clone;
    }

    public function get(): IblockElementCollection
    {
        $this->assertScopeDefined();

        $collection = $this->fetcher->fetch(
            iblockIds: $this->iblockIds,
            elementIds: $this->elementIds,
            resolvers: $this->resolvers,
            fields: $this->fields,
            props: $this->props,
        );

        $this->assertElementsInScope($collection);

        return $collection;
    }

    public function first(): ?IblockElement
    {
        return $this->get()->elements[0] ?? null;
    }

    private function assertScopeDefined(): void
    {
        if ($this->iblockIds === [] && $this->elementIds === []) {
            throw new IblockScopeException(
                'Specify filter(id: ...) or filter(iblock: ...) before get().',
            );
        }
    }

    private function assertElementsInScope(IblockElementCollection $collection): void
    {
        if ($this->iblockIds === []) {
            return;
        }

        $allowed = array_flip($this->iblockIds);
        foreach ($collection->elements as $element) {
            if (!isset($allowed[$element->iblockId])) {
                throw new IblockScopeException(
                    sprintf('Element iblock %d is outside filter(iblock: ...) scope.', $element->iblockId),
                );
            }
        }
    }
}
