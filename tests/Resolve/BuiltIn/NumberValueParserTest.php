<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Resolve\BuiltIn;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use IblockElementReader\Resolve\BuiltIn\NumberValueParser;

final class NumberValueParserTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, int|float|null}>
     */
    public static function values(): iterable
    {
        yield 'десятичная запятая' => ['1,6', 1.6];
        yield 'разделитель тысяч' => ['1 500', 1500];
        yield 'пробелы по краям' => [' 42 ', 42];
        yield 'нечисловое' => ['abc', null];
        yield 'пустая строка' => ['', null];
        yield 'ноль' => ['0', 0];

        yield 'целое' => ['15000', 15000];
        yield 'дробное с точкой' => ['12.5', 12.5];
        yield 'отрицательное' => ['-3,25', -3.25];
        yield 'неразрывные пробелы по краям' => ["\u{00A0}42\u{00A0}", 42];
        yield 'неразрывный пробел как разделитель тысяч' => ["1\u{00A0}500", 1500];
        yield 'узкий неразрывный пробел' => ["1\u{202F}500\u{202F}000", 1500000];
        yield 'тысячи и десятичная запятая' => ['1 500,75', 1500.75];
        yield 'целое из числовой колонки' => ['15000.0000', 15000];
        yield 'дробное из числовой колонки' => ['1.6000', 1.6];
        yield 'ведущие нули' => ['007', 7];
        yield 'только пробелы' => ['   ', null];
        yield 'пробел не по группам тысяч' => ['1 5', null];
        yield 'число с текстом' => ['12 шт', null];
        yield 'две запятые' => ['1,2,3', null];
        yield 'null' => [null, null];
        yield 'int как есть' => [42, 42];
        yield 'float как есть' => [1.5, 1.5];
        yield 'массив' => [['1'], null];
    }

    #[DataProvider('values')]
    public function testCast(mixed $input, int|float|null $expected): void
    {
        self::assertSame($expected, (new NumberValueParser())->cast($input));
    }

    public function testNonNumericDoesNotEmitWarnings(): void
    {
        set_error_handler(static function (int $errno, string $message): never {
            throw new \ErrorException($message, 0, $errno);
        });

        try {
            self::assertNull((new NumberValueParser())->cast("\xFF\xFE"));
            self::assertNull((new NumberValueParser())->cast('1e999999'));
        } finally {
            restore_error_handler();
        }
    }

    public function testMapPreservesRawKeys(): void
    {
        $map = (new NumberValueParser())->map(['15000', '12.5', '1,6', 'abc']);

        self::assertSame(15000, $map['15000']);
        self::assertSame(12.5, $map['12.5']);
        self::assertSame(1.6, $map['1,6']);
        self::assertNull($map['abc']);
    }
}
