<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve;

use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;

/**
 * Контекст batch-резолвера: один вызов на тип свойства за весь запрос.
 */
final readonly class PropertyResolveContext
{
    /**
     * @param list<mixed>              $uniqueValues        уникальные сырые значения
     * @param list<mixed>              $uniqueDescriptions  уникальные description
     * @param array<int, mixed|list>   $byElement           elementId => value | values[]
     */
    public function __construct(
        public IblockPropertyDefinitionRecord $definition,
        public array $uniqueValues,
        public array $uniqueDescriptions,
        public array $byElement,
    ) {
    }
}
