<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve;

use IblockElementReader\Resolve\BuiltIn\BuiltInUserTypeResolvers;
use IblockElementReader\Resolve\Contract\UserTypePropertyResolver;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;

/**
 * Резолверы по USER_TYPE: встроенные (Date, DateTime) + кастомные from withResolvers.
 * Кастомные переопределяют встроенные.
 *
 * @phpstan-type PropertyResolver callable(PropertyResolveContext): array<int|string, mixed>
 */
final readonly class PropertyResolverRegistry
{
    /** @var array<string, UserTypePropertyResolver> */
    private array $customResolvers;

    /**
     * @param UserTypePropertyResolver[] $resolvers
     */
    public function __construct(array $resolvers = [])
    {
        $customResolvers = [];

        foreach ($resolvers as $resolver) {
            $customResolvers[$resolver->userType()] = $resolver;
        }

        $this->customResolvers = $customResolvers;
    }

    public function has(IblockPropertyDefinitionRecord $definition): bool
    {
        return $this->hasCustom($definition) || BuiltInUserTypeResolvers::supports($definition);
    }

    /**
     * @return PropertyResolver|null
     */
    public function get(IblockPropertyDefinitionRecord $definition): ?callable
    {
        if ($this->hasCustom($definition)) {
            $resolver = $this->customResolvers[$definition->userType];

            return $resolver->resolve(...);
        }

        if (BuiltInUserTypeResolvers::supports($definition)) {
            return BuiltInUserTypeResolvers::resolver($definition);
        }

        return null;
    }

    private function hasCustom(IblockPropertyDefinitionRecord $definition): bool
    {
        $userType = $definition->userType;

        return $userType !== null && $userType !== '' && isset($this->customResolvers[$userType]);
    }
}
