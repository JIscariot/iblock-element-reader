<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Resolve\BuiltIn;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Enum\PropertyTypeEnum;
use IblockElementReader\Enum\PropertyUserTypeEnum;
use IblockElementReader\Resolve\BuiltIn\BuiltInUserTypeResolvers;
use IblockElementReader\Resolve\BuiltIn\DateTimeValueParser;
use IblockElementReader\Resolve\PropertyResolveContext;
use IblockElementReader\Resolve\Contract\UserTypePropertyResolver;
use IblockElementReader\Resolve\PropertyResolverRegistry;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;

final class DateTimeValueParserTest extends TestCase
{
    public function testParsesBitrixDateTimeFormat(): void
    {
        $date = (new DateTimeValueParser())->parse('2024-03-15 14:30:00');

        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2024-03-15 14:30:00', $date->format('Y-m-d H:i:s'));
    }

    public function testParsesBitrixDateFormatAsDateTime(): void
    {
        $date = (new DateTimeValueParser())->parse('2024-03-15');

        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2024-03-15 00:00:00', $date->format('Y-m-d H:i:s'));
    }

    public function testParsesRussianDateFormat(): void
    {
        $date = (new DateTimeValueParser())->parse('15.03.2024');

        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2024-03-15 00:00:00', $date->format('Y-m-d H:i:s'));
    }

    public function testEmptyValueReturnsNull(): void
    {
        self::assertNull((new DateTimeValueParser())->parse(''));
        self::assertNull((new DateTimeValueParser())->parse(null));
    }
}

final class BuiltInUserTypeResolversTest extends TestCase
{
    public function testSupportsDateAndDateTimeUserTypes(): void
    {
        self::assertTrue(BuiltInUserTypeResolvers::supports($this->definition(PropertyUserTypeEnum::DATE)));
        self::assertTrue(BuiltInUserTypeResolvers::supports($this->definition(PropertyUserTypeEnum::DATETIME)));
        self::assertFalse(BuiltInUserTypeResolvers::supports($this->definition(null)));
    }

    public function testRegistryUsesBuiltInResolverForDateUserType(): void
    {
        $definition = $this->definition(PropertyUserTypeEnum::DATE);
        $registry = new PropertyResolverRegistry();

        self::assertTrue($registry->has($definition));

        $resolver = $registry->get($definition);
        self::assertNotNull($resolver);

        $map = $resolver(new PropertyResolveContext(
            definition: $definition,
            uniqueValues: ['2024-01-10', '2024-01-11'],
            uniqueDescriptions: [],
            byElement: [10 => '2024-01-10'],
        ));

        self::assertInstanceOf(\DateTimeImmutable::class, $map['2024-01-10']);
        self::assertSame('2024-01-10 00:00:00', $map['2024-01-10']->format('Y-m-d H:i:s'));
    }

    public function testCustomResolverOverridesBuiltInDate(): void
    {
        $definition = $this->definition(PropertyUserTypeEnum::DATE);
        $registry = new PropertyResolverRegistry([
            new class implements UserTypePropertyResolver {
                public function userType(): string
                {
                    return PropertyUserTypeEnum::DATE->value;
                }

                public function resolve(PropertyResolveContext $context): array
                {
                    return ['2024-01-10' => 'custom'];
                }
            },
        ]);

        $map = $registry->get($definition)(new PropertyResolveContext(
            definition: $definition,
            uniqueValues: ['2024-01-10'],
            uniqueDescriptions: [],
            byElement: [10 => '2024-01-10'],
        ));

        self::assertSame('custom', $map['2024-01-10']);
    }

    private function definition(?PropertyUserTypeEnum $userType): IblockPropertyDefinitionRecord
    {
        return new IblockPropertyDefinitionRecord(
            id: 1,
            code: 'SOLD_AT',
            name: 'Sold at',
            type: PropertyTypeEnum::STRING,
            multiple: false,
            userType: $userType?->value,
        );
    }
}
