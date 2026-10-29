<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Fixture;

use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;
use IblockElementReader\Repository\IblockPropertyDefinitionRepository;

/** Определения свойств без обращения к b_iblock_property. */
final class InMemoryPropertyDefinitionRepository extends IblockPropertyDefinitionRepository
{
    /** @var array<int, array<int, IblockPropertyDefinitionRecord>> */
    private array $byIblock = [];

    public function add(int $iblockId, IblockPropertyDefinitionRecord ...$definitions): self
    {
        foreach ($definitions as $definition) {
            $this->byIblock[$iblockId][$definition->id] = $definition;
        }

        return $this;
    }

    public function findByCodes(int $iblockId, array $codes = []): array
    {
        $all = $this->byIblock[$iblockId] ?? [];

        return $codes === [] ? $all : $this->filterByCodes($all, $codes);
    }
}
