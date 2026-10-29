<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Resolve\BuiltIn;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Enum\PropertyTypeEnum;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;
use IblockElementReader\Resolve\BuiltIn\ScalarValueResolver;

final class ScalarValueResolverTest extends TestCase
{
    public function testStringResolvedEqualsRaw(): void
    {
        $resolver = new ScalarValueResolver();
        $definition = $this->definition(PropertyTypeEnum::STRING);

        self::assertTrue($resolver->supports($definition));
        self::assertSame('WVWZZZ1JZXW000001', $resolver->resolve($definition, 'WVWZZZ1JZXW000001'));
        self::assertSame(' 1,6 ', $resolver->resolve($definition, ' 1,6 '));
        self::assertNull($resolver->resolve($definition, null));
    }

    public function testNumberResolvedThroughParser(): void
    {
        $resolver = new ScalarValueResolver();
        $definition = $this->definition(PropertyTypeEnum::NUMBER);

        self::assertTrue($resolver->supports($definition));
        self::assertSame(1.6, $resolver->resolve($definition, '1,6'));
        self::assertSame(1500, $resolver->resolve($definition, '1 500'));
        self::assertNull($resolver->resolve($definition, 'abc'));
    }

    public function testPropertiesWithUserTypeAreNotSupported(): void
    {
        $resolver = new ScalarValueResolver();

        self::assertFalse($resolver->supports($this->definition(PropertyTypeEnum::STRING, 'DateTime')));
        self::assertFalse($resolver->supports($this->definition(PropertyTypeEnum::STRING, 'directory')));
        self::assertFalse($resolver->supports($this->definition(PropertyTypeEnum::NUMBER, 'SASDCheckbox')));
    }

    public function testOtherPropertyTypesAreNotSupported(): void
    {
        $resolver = new ScalarValueResolver();

        self::assertFalse($resolver->supports($this->definition(PropertyTypeEnum::LIST)));
        self::assertFalse($resolver->supports($this->definition(PropertyTypeEnum::FILE)));
        self::assertFalse($resolver->supports($this->definition(PropertyTypeEnum::ELEMENT)));
        self::assertFalse($resolver->supports($this->definition(PropertyTypeEnum::SECTION)));
    }

    private function definition(PropertyTypeEnum $type, ?string $userType = null): IblockPropertyDefinitionRecord
    {
        return new IblockPropertyDefinitionRecord(
            id: 1,
            code: 'PROP',
            name: 'Prop',
            type: $type,
            multiple: false,
            userType: $userType,
        );
    }
}
