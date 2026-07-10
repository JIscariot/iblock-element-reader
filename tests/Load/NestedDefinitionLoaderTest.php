<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Load;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Enum\PropertyTypeEnum;
use IblockElementReader\Load\NestedDefinitionLoader;
use IblockElementReader\Resolve\PropertyPath;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;

final class NestedDefinitionLoaderTest extends TestCase
{
    public function testCollectsNestedCodesForLinkedIblock(): void
    {
        $codesByIblock = [];
        $loader = new NestedDefinitionLoader();
        $reflection = new \ReflectionClass(NestedDefinitionLoader::class);
        $method = $reflection->getMethod('collectNestedCodes');
        $method->setAccessible(true);
        $args = [
            [
                new PropertyPath('ADDRESS', false),
                new PropertyPath('PHONES', false),
            ],
            new IblockPropertyDefinitionRecord(
                id: 1,
                code: 'STORE',
                name: 'Store',
                type: PropertyTypeEnum::ELEMENT,
                multiple: false,
                linkIblockId: 99,
            ),
            &$codesByIblock,
        ];
        $method->invokeArgs($loader, $args);

        self::assertSame(['ADDRESS', 'PHONES'], $codesByIblock[99]);
    }
}
