<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Resolve;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Resolve\PropertySpecParser;
use IblockElementReader\Resolve\Contract\UserTypePropertyResolver;
use IblockElementReader\Resolve\PropertyResolverRegistry;
use IblockElementReader\Resolve\ResolvedValueDistributor;

final class PropertySpecParserTest extends TestCase
{
    public function testParsesValueSuffixAsAutoResolve(): void
    {
        $specs = (new PropertySpecParser())->parse(['PHOTO.VALUE', 'STORE.VALUE']);

        self::assertSame('PHOTO', $specs[0]->code);
        self::assertTrue($specs[0]->autoResolve);

        self::assertSame('STORE', $specs[1]->code);
        self::assertTrue($specs[1]->autoResolve);
    }

    public function testParsesBareKeyWithoutAutoResolve(): void
    {
        $specs = (new PropertySpecParser())->parse(['VIN', 'MAKE_ID']);

        self::assertSame('VIN', $specs[0]->code);
        self::assertFalse($specs[0]->autoResolve);

        self::assertSame('MAKE_ID', $specs[1]->code);
        self::assertFalse($specs[1]->autoResolve);
    }

    public function testExpandsWildcardToAllCodes(): void
    {
        $parser = new PropertySpecParser();

        $expanded = $parser->expandWildcard(['*'], ['VIN', 'PRICE', 'PHOTO']);

        self::assertSame(['VIN', 'PRICE', 'PHOTO'], $expanded);
    }

    public function testNullPropsDoNotNeedExpansion(): void
    {
        self::assertFalse((new PropertySpecParser())->needsExpansion(null));
    }

    public function testExpandsEmptyPropsWithValueWildcard(): void
    {
        $parser = new PropertySpecParser();

        self::assertTrue($parser->needsExpansion([]));

        $expanded = $parser->expandWildcard([], ['VIN', 'PRICE']);

        self::assertSame(['VIN.VALUE', 'PRICE.VALUE'], $expanded);
    }

    public function testExpandsValueWildcardWithAutoResolveSuffix(): void
    {
        $parser = new PropertySpecParser();

        $expanded = $parser->expandWildcard(['*.VALUE'], ['PHOTO', 'STORE']);

        self::assertSame(['PHOTO.VALUE', 'STORE.VALUE'], $expanded);

        $specs = $parser->parse($expanded);

        self::assertTrue($specs[0]->autoResolve);
        self::assertTrue($specs[1]->autoResolve);
    }

    public function testValueWildcardSkipsExplicitCodes(): void
    {
        $parser = new PropertySpecParser();

        $expanded = $parser->expandWildcard(
            ['*.VALUE', 'MAKE_ID'],
            ['PHOTO', 'MAKE_ID', 'STORE'],
        );

        self::assertSame(['PHOTO.VALUE', 'STORE.VALUE', 'MAKE_ID'], $expanded);
    }

    public function testWildcardSkipsExplicitCodes(): void
    {
        $parser = new PropertySpecParser();

        $expanded = $parser->expandWildcard(
            ['*', 'MAKE_ID'],
            ['VIN', 'MAKE_ID', 'PRICE'],
        );

        self::assertSame(['VIN', 'PRICE', 'MAKE_ID'], $expanded);
    }
}

final class PropertyResolverRegistryTest extends TestCase
{
    public function testMatchesResolverByUserType(): void
    {
        $definition = $this->definition(userType: 'makeDictionary');
        $resolver = new class implements UserTypePropertyResolver {
            public function userType(): string
            {
                return 'makeDictionary';
            }

            public function resolve(\IblockElementReader\Resolve\PropertyResolveContext $context): array
            {
                return [];
            }
        };
        $registry = new PropertyResolverRegistry([$resolver]);

        self::assertTrue($registry->has($definition));
        self::assertNotNull($registry->get($definition));
    }

    public function testReturnsNullWhenUserTypeIsMissing(): void
    {
        $definition = $this->definition(userType: null);
        $registry = new PropertyResolverRegistry([
            new class implements UserTypePropertyResolver {
                public function userType(): string
                {
                    return 'makeDictionary';
                }

                public function resolve(\IblockElementReader\Resolve\PropertyResolveContext $context): array
                {
                    return [];
                }
            },
        ]);

        self::assertFalse($registry->has($definition));
        self::assertNull($registry->get($definition));
    }

    private function definition(?string $userType): \IblockElementReader\Model\Record\IblockPropertyDefinitionRecord
    {
        return new \IblockElementReader\Model\Record\IblockPropertyDefinitionRecord(
            id: 1,
            code: 'MAKE_ID',
            name: 'Make',
            type: \IblockElementReader\Enum\PropertyTypeEnum::NUMBER,
            multiple: false,
            userType: $userType,
        );
    }
}
