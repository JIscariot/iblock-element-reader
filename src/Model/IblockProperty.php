<?php

declare(strict_types=1);

namespace IblockElementReader\Model;

use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;

final readonly class IblockProperty
{
    /**
     * @param null|IblockPropertyValue|IblockPropertyValue[] $value
     */
    public function __construct(
        public IblockPropertyDefinitionRecord $definition,
        public null|IblockPropertyValue|array $value = null,
    ) {
    }
}
