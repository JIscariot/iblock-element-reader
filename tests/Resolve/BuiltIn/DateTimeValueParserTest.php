<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Resolve\BuiltIn;

use PHPUnit\Framework\Attributes\DataProvider;
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
    private string $defaultTimezone;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../Fixture/BitrixTypeStubs.php';

        $this->defaultTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Minsk');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTimezone);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function supportedStrings(): iterable
    {
        yield 'БД: Y-m-d H:i:s' => ['2024-03-15 14:30:45', '2024-03-15 14:30:45'];
        yield 'БД: Y-m-d H:i' => ['2024-03-15 14:30', '2024-03-15 14:30:00'];
        yield 'БД: Y-m-d' => ['2024-03-15', '2024-03-15 00:00:00'];
        yield 'БД: ISO без пояса' => ['2024-03-15T14:30:45', '2024-03-15 14:30:45'];
        yield 'сайт: d.m.Y H:i:s' => ['15.03.2024 14:30:45', '2024-03-15 14:30:45'];
        yield 'сайт: d.m.Y H:i' => ['15.03.2024 14:30', '2024-03-15 14:30:00'];
        yield 'сайт: d.m.Y' => ['15.03.2024', '2024-03-15 00:00:00'];
        yield 'пробелы по краям' => ['  15.03.2024 ', '2024-03-15 00:00:00'];
    }

    #[DataProvider('supportedStrings')]
    public function testParsesSupportedFormats(string $input, string $expected): void
    {
        $date = DateTimeValueParser::parseValue($input);

        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame($expected, $date->format('Y-m-d H:i:s'));
        self::assertSame('000000', $date->format('u'));
    }

    public function testStringIsParsedInDefaultTimezoneWithoutShift(): void
    {
        $date = DateTimeValueParser::parseValue('2024-03-15 14:30:00');

        self::assertSame('Europe/Minsk', $date->getTimezone()->getName());
        self::assertSame('2024-03-15 14:30:00', $date->format('Y-m-d H:i:s'));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidValues(): iterable
    {
        yield 'пустая строка' => [''];
        yield 'только пробелы' => ['   '];
        yield 'null' => [null];
        yield 'мусор' => ['abc'];
        yield 'относительная дата' => ['tomorrow'];
        yield 'now' => ['now'];
        yield '31 февраля' => ['31.02.2024'];
        yield '13-й месяц' => ['2024-13-01'];
        yield '25 часов' => ['2024-03-15 25:00:00'];
        yield 'двузначный год' => ['15.03.24'];
        yield 'дата и мусор' => ['2024-03-15 abc'];
        yield 'число' => [20240315];
        yield 'массив' => [['2024-03-15']];
        yield 'bool' => [true];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValueReturnsNullWithoutWarnings(mixed $input): void
    {
        set_error_handler(static function (int $errno, string $message): never {
            throw new \ErrorException($message, 0, $errno);
        });

        try {
            self::assertNull(DateTimeValueParser::parseValue($input));
            self::assertNull((new DateTimeValueParser())->parse($input));
        } finally {
            restore_error_handler();
        }
    }

    public function testDateOnlyResetsTime(): void
    {
        self::assertSame(
            '2024-03-15 00:00:00',
            DateTimeValueParser::parseValue('2024-03-15 14:30:45', dateOnly: true)->format('Y-m-d H:i:s'),
        );
        self::assertSame(
            '2024-03-15 00:00:00',
            DateTimeValueParser::parseValue('15.03.2024', dateOnly: true)->format('Y-m-d H:i:s'),
        );
    }

    public function testParsesBitrixDateObject(): void
    {
        $date = DateTimeValueParser::parseValue(new \Bitrix\Main\Type\Date('2024-03-15', 'Y-m-d'));

        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2024-03-15 00:00:00', $date->format('Y-m-d H:i:s'));
    }

    public function testParsesBitrixDateTimeObjectKeepingItsTimezone(): void
    {
        $value = new \Bitrix\Main\Type\DateTime('2024-03-15 14:30:45', 'Y-m-d H:i:s', new \DateTimeZone('Asia/Tokyo'));

        $date = DateTimeValueParser::parseValue($value);

        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2024-03-15 14:30:45', $date->format('Y-m-d H:i:s'));
        self::assertSame('Asia/Tokyo', $date->getTimezone()->getName());
    }

    public function testParsesNativeDateTime(): void
    {
        $date = DateTimeValueParser::parseValue(new \DateTime('2024-03-15 14:30:45'));

        self::assertSame('2024-03-15 14:30:45', $date->format('Y-m-d H:i:s'));
    }

    public function testMapSkipsObjectKeysAndKeepsRawKeys(): void
    {
        $map = (new DateTimeValueParser())->map(['2024-03-15', '15.03.2024 10:00', 'abc', null]);

        self::assertSame('2024-03-15 00:00:00', $map['2024-03-15']->format('Y-m-d H:i:s'));
        self::assertSame('2024-03-15 10:00:00', $map['15.03.2024 10:00']->format('Y-m-d H:i:s'));
        self::assertNull($map['abc']);
        self::assertNull($map['']);
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

    public function testDateUserTypeAlwaysHasMidnightTime(): void
    {
        $definition = $this->definition(PropertyUserTypeEnum::DATE);

        $map = (new PropertyResolverRegistry())->get($definition)(new PropertyResolveContext(
            definition: $definition,
            uniqueValues: ['2024-01-10 15:45:00', '11.01.2024', '', 'abc'],
            uniqueDescriptions: [],
            byElement: [],
        ));

        self::assertSame('2024-01-10 00:00:00', $map['2024-01-10 15:45:00']->format('Y-m-d H:i:s'));
        self::assertSame('2024-01-11 00:00:00', $map['11.01.2024']->format('Y-m-d H:i:s'));
        self::assertNull($map['']);
        self::assertNull($map['abc']);
    }

    public function testDateTimeUserTypeKeepsTime(): void
    {
        $definition = $this->definition(PropertyUserTypeEnum::DATETIME);

        $map = (new PropertyResolverRegistry())->get($definition)(new PropertyResolveContext(
            definition: $definition,
            uniqueValues: ['2024-01-10 15:45:30', '11.01.2024 08:05:00'],
            uniqueDescriptions: [],
            byElement: [],
        ));

        self::assertInstanceOf(\DateTimeImmutable::class, $map['2024-01-10 15:45:30']);
        self::assertSame('2024-01-10 15:45:30', $map['2024-01-10 15:45:30']->format('Y-m-d H:i:s'));
        self::assertSame('2024-01-11 08:05:00', $map['11.01.2024 08:05:00']->format('Y-m-d H:i:s'));
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
