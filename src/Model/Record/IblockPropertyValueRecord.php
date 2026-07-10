<?php

declare(strict_types=1);

namespace IblockElementReader\Model\Record;

/** Сырое значение свойства из БД (до резолва и cast). */
final readonly class IblockPropertyValueRecord
{
    public function __construct(
        public IblockPropertyDefinitionRecord $definition,
        public mixed $value,
        public mixed $description = null,
    ) {
    }
}
