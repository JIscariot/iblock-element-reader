<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Resolve;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Model\File;
use IblockElementReader\Model\Record\IblockElementRecord;
use IblockElementReader\Model\Record\IblockElementRecordCollection;
use IblockElementReader\Model\Record\IblockPropertyValueRecordCollection;
use IblockElementReader\Resolve\AutoResolveMaps;
use IblockElementReader\Resolve\InMemoryAutoResolveMapsLoader;
use IblockElementReader\Resolve\ResolvePipeline;

final class ResolvePipelineModelTest extends TestCase
{
    public function testAssemblesFieldsFromElements(): void
    {
        $elements = new IblockElementRecordCollection(
            new IblockElementRecord(
                id: 10,
                iblockId: 5,
                fields: [
                    'NAME' => 'Bike',
                    'CODE' => 'bike-10',
                    'ACTIVE' => 'Y',
                    'SORT' => 100,
                ],
                properties: new IblockPropertyValueRecordCollection(),
            ),
        );

        $collection = (new ResolvePipeline())->resolve(
            elements: $elements,
            order: [10],
            fields: ['*'],
            props: null,
        );

        $element = $collection->elements[0];
        self::assertSame('Bike', $element->name);
        self::assertSame('bike-10', $element->code);
        self::assertSame(100, $element->sort);
        self::assertTrue($element->isActive);
    }

    public function testPreviewPictureWithoutValueReturnsIntId(): void
    {
        $elements = new IblockElementRecordCollection(
            new IblockElementRecord(
                id: 10,
                iblockId: 5,
                fields: ['PREVIEW_PICTURE' => '42'],
                properties: new IblockPropertyValueRecordCollection(),
            ),
        );

        $collection = (new ResolvePipeline())->resolve(
            elements: $elements,
            order: [10],
            fields: ['PREVIEW_PICTURE'],
            props: null,
        );

        self::assertSame(42, $collection->elements[0]->previewPicture);
    }

    public function testPreviewPictureWithValueReturnsFile(): void
    {
        $file = new File(
            id: 42,
            subdir: 'iblock/abc',
            filename: 'pic.jpg',
            src: '/upload/iblock/abc/pic.jpg',
            size: 1000,
        );

        $elements = new IblockElementRecordCollection(
            new IblockElementRecord(
                id: 10,
                iblockId: 5,
                fields: ['PREVIEW_PICTURE' => '42'],
                properties: new IblockPropertyValueRecordCollection(),
            ),
        );

        $pipeline = new ResolvePipeline(
            mapsLoader: new InMemoryAutoResolveMapsLoader(new AutoResolveMaps(files: [42 => $file])),
        );

        $collection = $pipeline->resolve(
            elements: $elements,
            order: [10],
            fields: ['PREVIEW_PICTURE.VALUE'],
            props: null,
        );

        self::assertSame($file, $collection->elements[0]->previewPicture);
    }
}
