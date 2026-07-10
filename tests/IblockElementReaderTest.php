<?php

declare(strict_types=1);

namespace IblockElementReader\Tests;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Exception\IblockScopeException;
use IblockElementReader\IblockElementReader;

final class IblockElementReaderTest extends TestCase
{
    public function testGetWithoutScopeThrows(): void
    {
        $this->expectException(IblockScopeException::class);
        $this->expectExceptionMessage('filter(id: ...) or filter(iblock: ...)');

        (new IblockElementReader())->get();
    }

    public function testFilterPreservesUnsetParameterOnSecondCall(): void
    {
        $reader = (new IblockElementReader())
            ->filter(id: [10, 20])
            ->filter(iblock: [5]);

        $reflection = new \ReflectionClass(IblockElementReader::class);
        $elementIds = $reflection->getProperty('elementIds');
        $elementIds->setAccessible(true);
        $iblockIds = $reflection->getProperty('iblockIds');
        $iblockIds->setAccessible(true);

        self::assertSame([10, 20], $elementIds->getValue($reader));
        self::assertSame([5], $iblockIds->getValue($reader));
    }
}
