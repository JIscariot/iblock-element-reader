<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Resolve;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Model\IblockElement;
use IblockElementReader\Resolve\LinkedElementCache;
use IblockElementReader\Resolve\PropertyPath;
use IblockElementReader\Resolve\PropertyPathParser;

final class LinkedElementCacheTest extends TestCase
{
    public function testStoresAndReturnsSameElementInstance(): void
    {
        $cache = new LinkedElementCache();
        $signature = LinkedElementCache::loadSignature(
            ['*'],
            [PropertyPathParser::parse('ADDRESS.VALUE')],
        );
        $element = new IblockElement(id: 100, iblockId: 5);

        $cache->set(linkIblockId: 5, elementId: 100, loadSignature: $signature, element: $element);

        self::assertTrue($cache->has(5, 100, $signature));
        self::assertSame($element, $cache->get(5, 100, $signature));
        self::assertNull($cache->get(5, 101, $signature));
    }

    public function testNestedSignatureIsOrderIndependent(): void
    {
        $left = LinkedElementCache::loadSignature(
            ['*'],
            [
                new PropertyPath('ADDRESS', true),
                new PropertyPath('PHONE', true),
            ],
        );
        $right = LinkedElementCache::loadSignature(
            ['*'],
            [
                new PropertyPath('PHONE', true),
                new PropertyPath('ADDRESS', true),
            ],
        );

        self::assertSame($left, $right);
    }

    public function testDifferentNestedPathsProduceDifferentSignatures(): void
    {
        $address = LinkedElementCache::loadSignature(
            ['*'],
            [PropertyPathParser::parse('ADDRESS.VALUE')],
        );
        $phone = LinkedElementCache::loadSignature(
            ['*'],
            [PropertyPathParser::parse('PHONE.VALUE')],
        );

        self::assertNotSame($address, $phone);
    }

    public function testDifferentFieldsProduceDifferentSignatures(): void
    {
        $all = LinkedElementCache::loadSignature(['*'], []);
        $nameOnly = LinkedElementCache::loadSignature(['NAME'], []);

        self::assertNotSame($all, $nameOnly);
        self::assertSame('__id__', LinkedElementCache::fieldsSignature(null));
        self::assertSame('*', LinkedElementCache::fieldsSignature(['*']));
    }
}
