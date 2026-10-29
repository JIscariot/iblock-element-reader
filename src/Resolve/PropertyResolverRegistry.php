<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve;

use IblockElementReader\Resolve\BuiltIn\BuiltInUserTypeResolvers;
use IblockElementReader\Resolve\Contract\SupportsPropertyResolver;
use IblockElementReader\Resolve\Contract\UserTypePropertyResolver;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;

/**
 * Резолверы свойств. Порядок выбора:
 *  1. первый SupportsPropertyResolver (в порядке регистрации), у которого supports() === true;
 *  2. резолвер по userType() — для резолверов без supports();
 *  3. встроенные (Date, DateTime).
 *
 * Результат выбора кешируется на определение свойства: supports() вызывается один раз за get().
 *
 * @phpstan-type PropertyResolver callable(PropertyResolveContext): array<int|string, mixed>
 */
final class PropertyResolverRegistry
{
    /** @var list<SupportsPropertyResolver> */
    private readonly array $supportsResolvers;

    /** @var array<string, UserTypePropertyResolver> */
    private readonly array $userTypeResolvers;

    /** @var array<string, PropertyResolver|null> ключ — {@see self::cacheKey()} */
    private array $matched = [];

    /**
     * @param UserTypePropertyResolver[] $resolvers
     * @throws \LogicException два резолвера без supports() с одинаковым userType()
     */
    public function __construct(array $resolvers = [])
    {
        $supportsResolvers = [];
        $userTypeResolvers = [];

        foreach ($resolvers as $resolver) {
            if ($resolver instanceof SupportsPropertyResolver) {
                $supportsResolvers[] = $resolver;
                continue;
            }

            $userType = $resolver->userType();
            if (isset($userTypeResolvers[$userType])) {
                throw new \LogicException(sprintf(
                    'Resolvers %s and %s are registered for the same USER_TYPE "%s". '
                    . 'Keep one of them or implement %s to choose properties explicitly.',
                    $userTypeResolvers[$userType]::class,
                    $resolver::class,
                    $userType,
                    SupportsPropertyResolver::class,
                ));
            }

            $userTypeResolvers[$userType] = $resolver;
        }

        $this->supportsResolvers = $supportsResolvers;
        $this->userTypeResolvers = $userTypeResolvers;
    }

    public function has(IblockPropertyDefinitionRecord $definition): bool
    {
        return $this->get($definition) !== null;
    }

    /**
     * @return PropertyResolver|null
     */
    public function get(IblockPropertyDefinitionRecord $definition): ?callable
    {
        $key = self::cacheKey($definition);

        if (!array_key_exists($key, $this->matched)) {
            $this->matched[$key] = $this->match($definition);
        }

        return $this->matched[$key];
    }

    /**
     * @return PropertyResolver|null
     */
    private function match(IblockPropertyDefinitionRecord $definition): ?callable
    {
        foreach ($this->supportsResolvers as $resolver) {
            if ($resolver->supports($definition)) {
                return $resolver->resolve(...);
            }
        }

        $userType = $definition->userType;
        if ($userType !== null && $userType !== '' && isset($this->userTypeResolvers[$userType])) {
            return $this->userTypeResolvers[$userType]->resolve(...);
        }

        if (BuiltInUserTypeResolvers::supports($definition)) {
            return BuiltInUserTypeResolvers::resolver($definition);
        }

        return null;
    }

    /**
     * ID свойства уникален в b_iblock_property; остальные поля — на случай определений,
     * собранных вручную (тесты) с одинаковым ID.
     */
    private static function cacheKey(IblockPropertyDefinitionRecord $definition): string
    {
        return implode("\0", [
            $definition->id,
            $definition->code,
            $definition->type->value,
            $definition->userType ?? '',
            $definition->linkIblockId ?? '',
            (int)$definition->multiple,
            (int)$definition->withDescription,
            $definition->version,
        ]);
    }
}
