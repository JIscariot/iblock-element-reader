<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Load;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Load\FieldSpecParser;

final class FieldSpecParserTest extends TestCase
{
    public function testParsesValueSuffix(): void
    {
        $specs = (new FieldSpecParser())->parse(['ID', 'PREVIEW_PICTURE.VALUE']);

        self::assertSame('ID', $specs[0]->name);
        self::assertFalse($specs[0]->autoResolve);
        self::assertSame('PREVIEW_PICTURE', $specs[1]->name);
        self::assertTrue($specs[1]->autoResolve);
    }

    public function testNullFieldsSelectsOnlyId(): void
    {
        $parser = new FieldSpecParser();
        $names = $parser->selectNames($parser->parse(null));

        self::assertSame(['ID'], $names);
    }

    public function testEmptyFieldsSelectsOnlyId(): void
    {
        $parser = new FieldSpecParser();
        $names = $parser->selectNames($parser->parse([]));

        self::assertSame(['ID'], $names);
    }

    public function testWildcardExpandsDefaultFields(): void
    {
        $parser = new FieldSpecParser();
        $names = $parser->selectNames($parser->parse(['*']));

        self::assertContains('ID', $names);
        self::assertContains('NAME', $names);
        self::assertContains('PREVIEW_PICTURE', $names);
        self::assertContains('DETAIL_TEXT', $names);
    }

    public function testWildcardWithExplicitFields(): void
    {
        $parser = new FieldSpecParser();
        $names = $parser->selectNames($parser->parse(['*', 'XML_ID']));

        self::assertContains('NAME', $names);
        self::assertContains('XML_ID', $names);
    }
}
