<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Resolve;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Resolve\PropertyPathParser;
use IblockElementReader\Resolve\PropertySpecParser;

final class PropertyPathParserTest extends TestCase
{
    public function testParsesNestedElementPropertyPath(): void
    {
        $path = PropertyPathParser::parse('STORE.VALUE.ADDRESS');

        self::assertSame('STORE', $path->code);
        self::assertTrue($path->autoResolve);
        self::assertCount(1, $path->nested);
        self::assertSame('ADDRESS', $path->nested[0]->code);
        self::assertFalse($path->nested[0]->autoResolve);
    }

    public function testParsesDeepNestedPath(): void
    {
        $path = PropertyPathParser::parse('STORE.VALUE.CITY.VALUE.REGION');

        self::assertSame('STORE', $path->code);
        self::assertSame('CITY', $path->nested[0]->code);
        self::assertTrue($path->nested[0]->autoResolve);
        self::assertSame('REGION', $path->nested[0]->nested[0]->code);
    }

    public function testConvertsPathBackToPropString(): void
    {
        self::assertSame(
            'STORE.VALUE.ADDRESS',
            PropertyPathParser::toPropString(PropertyPathParser::parse('STORE.VALUE.ADDRESS')),
        );
    }

    public function testParserMergesNestedPathsWithSameRoot(): void
    {
        $specs = (new PropertySpecParser())->parse([
            'STORE.VALUE.ADDRESS',
            'STORE.VALUE.PHONE',
        ]);

        self::assertCount(1, $specs);
        self::assertSame('STORE', $specs[0]->code);
        self::assertTrue($specs[0]->hasNested());
        self::assertSame(['ADDRESS', 'PHONE'], array_map(
            static fn($path) => $path->code,
            $specs[0]->nested,
        ));
    }
}
