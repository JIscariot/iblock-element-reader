<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Repository;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Enum\PropertyTypeEnum;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;
use IblockElementReader\Repository\IblockPropertyDefinitionRepository;

final class IblockPropertyDefinitionRepositoryTest extends TestCase
{
    public function testFilterByCodesReturnsMatchingDefinitions(): void
    {
        $repository = new IblockPropertyDefinitionRepository();
        $byId = [
            1 => new IblockPropertyDefinitionRecord(1, 'MAKE_ID', 'Make', PropertyTypeEnum::NUMBER, false),
            2 => new IblockPropertyDefinitionRecord(2, 'PRICE', 'Price', PropertyTypeEnum::NUMBER, false),
            3 => new IblockPropertyDefinitionRecord(3, 'VIN', 'VIN', PropertyTypeEnum::STRING, false),
        ];

        $filtered = $repository->filterByCodes($byId, ['MAKE_ID', 'PRICE']);

        self::assertCount(2, $filtered);
        self::assertArrayHasKey(1, $filtered);
        self::assertArrayHasKey(2, $filtered);
        self::assertArrayNotHasKey(3, $filtered);
    }
}
