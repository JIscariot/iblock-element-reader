<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Resolve;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Enum\PropertyTypeEnum;
use IblockElementReader\Resolve\RawValueAggregator;
use IblockElementReader\Model\Record\IblockElementRecord;
use IblockElementReader\Model\Record\IblockElementRecordCollection;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;
use IblockElementReader\Model\Record\IblockPropertyValueRecord;
use IblockElementReader\Model\Record\IblockPropertyValueRecordCollection;

final class RawValueAggregatorTest extends TestCase
{
    public function testCollectsUniqueDescriptions(): void
    {
        $def = $this->def('SOCIAL', multiple: true);
        $elements = new IblockElementRecordCollection(
            new IblockElementRecord(
                id: 10,
                iblockId: 5,
                fields: [],
                properties: new IblockPropertyValueRecordCollection(
                    new IblockPropertyValueRecord($def, 'tg', '@shop'),
                    new IblockPropertyValueRecord($def, 'vk', 'club'),
                ),
            ),
            new IblockElementRecord(
                id: 20,
                iblockId: 5,
                fields: [],
                properties: new IblockPropertyValueRecordCollection(
                    new IblockPropertyValueRecord($def, 'tg', '@shop'),
                ),
            ),
        );

        $ctx = (new RawValueAggregator())->context($elements, 'SOCIAL', $def);

        self::assertSame(['tg', 'vk'], $ctx->uniqueValues);
        self::assertSame(['@shop', 'club'], $ctx->uniqueDescriptions);
    }

    public function testIncludesElementsWithEmptySingleValue(): void
    {
        $def = $this->def('IS_ACTIVE');
        $elements = new IblockElementRecordCollection(
            new IblockElementRecord(
                id: 10,
                iblockId: 5,
                fields: [],
                properties: new IblockPropertyValueRecordCollection(
                    new IblockPropertyValueRecord($def, '1'),
                ),
            ),
            new IblockElementRecord(
                id: 20,
                iblockId: 5,
                fields: [],
                properties: new IblockPropertyValueRecordCollection(),
            ),
        );

        $ctx = (new RawValueAggregator())->context($elements, 'IS_ACTIVE', $def);

        self::assertSame(['1', null], $ctx->uniqueValues);
        self::assertSame([10 => '1', 20 => null], $ctx->byElement);
    }

    public function testIncludesElementsWithEmptyMultipleValue(): void
    {
        $def = $this->def('TAGS', multiple: true);
        $elements = new IblockElementRecordCollection(
            new IblockElementRecord(
                id: 10,
                iblockId: 5,
                fields: [],
                properties: new IblockPropertyValueRecordCollection(),
            ),
        );

        $ctx = (new RawValueAggregator())->context($elements, 'TAGS', $def);

        self::assertSame([], $ctx->uniqueValues);
        self::assertSame([10 => []], $ctx->byElement);
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
