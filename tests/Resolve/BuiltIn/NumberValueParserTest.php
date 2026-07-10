<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Resolve\BuiltIn;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Resolve\BuiltIn\NumberValueParser;

final class NumberValueParserTest extends TestCase
{
    public function testCastsIntegerString(): void
    {
        $parser = new NumberValueParser();

        self::assertSame(15000, $parser->cast('15000'));
        self::assertSame(0, $parser->cast('0'));
    }

    public function testCastsFloatString(): void
    {
        $parser = new NumberValueParser();

        self::assertSame(12.5, $parser->cast('12.5'));
    }

    public function testEmptyReturnsNull(): void
    {
        $parser = new NumberValueParser();

        self::assertNull($parser->cast(''));
        self::assertNull($parser->cast(null));
    }

    public function testMapPreservesRawKeys(): void
    {
        $map = (new NumberValueParser())->map(['15000', '12.5']);

        self::assertSame(15000, $map['15000']);
        self::assertSame(12.5, $map['12.5']);
    }
}
