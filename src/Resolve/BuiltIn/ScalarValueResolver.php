<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve\BuiltIn;

use IblockElementReader\Enum\PropertyTypeEnum;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;

/**
 * resolved для простых свойств без USER_TYPE — всегда, независимо от суффикса .VALUE:
 * S → raw как есть, N → int|float|null через NumberValueParser.
 */
final class ScalarValueResolver
{
    public function __construct(
        private readonly NumberValueParser $numberParser = new NumberValueParser(),
    ) {
    }

    public function supports(IblockPropertyDefinitionRecord $definition): bool
    {
        if ($definition->userType !== null && $definition->userType !== '') {
            return false;
        }

        return $definition->type === PropertyTypeEnum::STRING
            || $definition->type === PropertyTypeEnum::NUMBER;
    }

    public function resolve(IblockPropertyDefinitionRecord $definition, mixed $raw): mixed
    {
        return $definition->type === PropertyTypeEnum::NUMBER
            ? $this->numberParser->cast($raw)
            : $raw;
    }
}
