<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Model\Record;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Enum\PropertyTypeEnum;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;

final class IblockPropertyDefinitionRecordTest extends TestCase
{
    public function testFromArrayReadsWithDescription(): void
    {
        $definition = IblockPropertyDefinitionRecord::fromArray([
            'ID'               => 10,
            'CODE'             => 'PHOTO',
            'NAME'             => 'Photo',
            'PROPERTY_TYPE'    => 'F',
            'MULTIPLE'         => 'N',
            'WITH_DESCRIPTION' => 'Y',
            'VERSION'          => 2,
        ]);

        self::assertTrue($definition->withDescription);
    }

    public function testFromArrayDefaultsWithDescriptionToFalse(): void
    {
        $definition = IblockPropertyDefinitionRecord::fromArray([
            'ID'            => 11,
            'CODE'          => 'PRICE',
            'NAME'          => 'Price',
            'PROPERTY_TYPE' => 'N',
            'MULTIPLE'      => 'N',
            'VERSION'       => 2,
        ]);

        self::assertFalse($definition->withDescription);
        self::assertSame(PropertyTypeEnum::NUMBER, $definition->type);
    }
}
