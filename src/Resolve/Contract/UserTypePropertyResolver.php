<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve\Contract;

use IblockElementReader\Resolve\PropertyResolveContext;

/**
 * Batch-резолвер свойств по USER_TYPE из b_iblock_property.
 * Вызывается один раз на тип за get(), map[rawValue] => resolved.
 */
interface UserTypePropertyResolver
{
    /** Значение b_iblock_property.USER_TYPE (например DependentSelect, booleanValue). */
    public function userType(): string;

    /**
     * @return array<int|string, mixed>  map[rawValue] => resolved
     */
    public function resolve(PropertyResolveContext $context): array;
}
