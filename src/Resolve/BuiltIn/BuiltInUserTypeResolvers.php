<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve\BuiltIn;

use IblockElementReader\Enum\PropertyUserTypeEnum;
use IblockElementReader\Resolve\PropertyResolveContext;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;

final class BuiltInUserTypeResolvers
{
    private static ?DateTimeValueParser $dateTimeParser = null;

    public static function supports(IblockPropertyDefinitionRecord $definition): bool
    {
        return match ($definition->userType) {
            PropertyUserTypeEnum::DATE->value,
            PropertyUserTypeEnum::DATETIME->value => true,
            default => false,
        };
    }

    /**
     * @return callable(PropertyResolveContext): array<int|string, mixed>
     */
    public static function resolver(IblockPropertyDefinitionRecord $definition): callable
    {
        return static function (PropertyResolveContext $context) use ($definition): array {
            if (!self::supports($definition)) {
                return [];
            }

            return self::parser()->map($context->uniqueValues);
        };
    }

    private static function parser(): DateTimeValueParser
    {
        return self::$dateTimeParser ??= new DateTimeValueParser();
    }
}
