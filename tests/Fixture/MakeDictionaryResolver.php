<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Fixture;

use IblockElementReader\Resolve\Contract\UserTypePropertyResolver;
use IblockElementReader\Resolve\PropertyResolveContext;

/** Старый резолвер — только userType(). */
final class MakeDictionaryResolver implements UserTypePropertyResolver
{
    public function userType(): string
    {
        return 'makeDictionary';
    }

    public function resolve(PropertyResolveContext $context): array
    {
        $map = [];
        foreach ($context->uniqueValues as $value) {
            $map[(string)$value] = 'make:' . $value;
        }

        return $map;
    }
}
