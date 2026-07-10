<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Resolve;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Model\Record\IblockElementRecord;
use IblockElementReader\Model\Record\IblockElementRecordCollection;
use IblockElementReader\Model\Record\IblockPropertyValueRecordCollection;
use IblockElementReader\Resolve\ResolvePipeline;

final class ResolvePipelineMultiIblockTest extends TestCase
{
    public function testResolvesElementsFromMultipleIblocks(): void
    {
        $elements = new IblockElementRecordCollection(
            new IblockElementRecord(id: 1, iblockId: 5, fields: ['NAME' => 'Bike'], properties: new IblockPropertyValueRecordCollection()),
            new IblockElementRecord(id: 2, iblockId: 10, fields: ['NAME' => 'Store'], properties: new IblockPropertyValueRecordCollection()),
        );

        $collection = (new ResolvePipeline())->resolve(
            elements: $elements,
            order: [1, 2],
            fields: ['NAME'],
            props: null,
        );

        self::assertCount(2, $collection);

        [$bike, $store] = $collection->elements;

        self::assertSame(5, $bike->iblockId);
        self::assertSame(10, $store->iblockId);
        self::assertSame('Bike', $bike->name);
        self::assertSame('Store', $store->name);
    }
}
