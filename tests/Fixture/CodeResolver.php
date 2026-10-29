<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Fixture;

use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;
use IblockElementReader\Resolve\Contract\SupportsPropertyResolver;
use IblockElementReader\Resolve\PropertyResolveContext;

/** Резолвер с supports() по коду свойства: каждое значение → "<label>:<raw>". */
final class CodeResolver implements SupportsPropertyResolver
{
    public int $supportsCalls = 0;
    public int $resolveCalls = 0;

    public function __construct(
        private readonly string $code,
        private readonly string $label,
        private readonly string $userType = '',
    ) {
    }

    public function userType(): string
    {
        return $this->userType;
    }

    public function supports(IblockPropertyDefinitionRecord $definition): bool
    {
        $this->supportsCalls++;

        return $definition->code === $this->code;
    }

    public function resolve(PropertyResolveContext $context): array
    {
        $this->resolveCalls++;

        $map = [];
        foreach ($context->uniqueValues as $value) {
            $map[(string)$value] = $this->label . ':' . $value;
        }

        return $map;
    }
}
