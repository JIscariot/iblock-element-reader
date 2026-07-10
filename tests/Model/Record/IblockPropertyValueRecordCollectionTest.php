<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Model\Record;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Enum\PropertyTypeEnum;
use IblockElementReader\Model\Record\IblockElementRecord;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;
use IblockElementReader\Model\Record\IblockPropertyValueRecord;
use IblockElementReader\Model\Record\IblockPropertyValueRecordCollection;

final class IblockPropertyValueRecordCollectionTest extends TestCase
{
    public function testGroupsMultipleValuesByCode(): void
    {
        $photo = $this->def('PHOTO', multiple: true);
        $collection = new IblockPropertyValueRecordCollection(
            new IblockPropertyValueRecord($photo, 'a'),
            new IblockPropertyValueRecord($photo, 'b'),
            new IblockPropertyValueRecord($this->def('PRICE'), '100'),
        );

        self::assertSame(['a', 'b'], $collection->rawValues('PHOTO'));
        self::assertSame('100', $collection->rawValue('PRICE'));
        self::assertSame($photo, $collection->definition('PHOTO'));
    }

    public function testElementExposesRawFieldsAndProps(): void
    {
        $element = new IblockElementRecord(
            id: 10,
            iblockId: 5,
            fields: ['NAME' => 'CBR', 'PREVIEW_PICTURE' => 55],
            properties: new IblockPropertyValueRecordCollection(
                new IblockPropertyValueRecord($this->def('MAKE_ID'), 7),
            ),
        );

        self::assertSame('CBR', $element->field('NAME'));
        self::assertSame(55, $element->field('PREVIEW_PICTURE'));
        self::assertSame(7, $element->rawProp('MAKE_ID'));
    }

    private function def(string $code, bool $multiple = false): IblockPropertyDefinitionRecord
    {
        return new IblockPropertyDefinitionRecord(
            id: 1,
            code: $code,
            name: $code,
            type: PropertyTypeEnum::STRING,
            multiple: $multiple,
        );
    }
}
