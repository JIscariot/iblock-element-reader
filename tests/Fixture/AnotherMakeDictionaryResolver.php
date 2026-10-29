<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Fixture;

use IblockElementReader\Resolve\Contract\UserTypePropertyResolver;
use IblockElementReader\Resolve\PropertyResolveContext;

final class AnotherMakeDictionaryResolver implements UserTypePropertyResolver
{
    public function userType(): string
    {
        return 'makeDictionary';
    }

    public function resolve(PropertyResolveContext $context): array
    {
        return [];
    }
}
