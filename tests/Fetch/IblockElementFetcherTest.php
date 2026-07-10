<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Fetch;

use PHPUnit\Framework\TestCase;
use IblockElementReader\IblockElementFetcher;
use IblockElementReader\Load\ElementLoaderInterface;
use IblockElementReader\Model\Record\IblockElementRecordCollection;

final class IblockElementFetcherTest extends TestCase
{
    public function testReturnsEmptyCollectionWhenLoaderFindsNothing(): void
    {
        $loader = $this->createMock(ElementLoaderInterface::class);
        $loader->method('load')->willReturn(new IblockElementRecordCollection());

        $collection = (new IblockElementFetcher($loader))->fetch(
            iblockIds: [5],
            elementIds: [10],
            fields: null,
            props: null,
        );

        self::assertCount(0, $collection);
        self::assertSame([], $collection->elements);
    }
}
