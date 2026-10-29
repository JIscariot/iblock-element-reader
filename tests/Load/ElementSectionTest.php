<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Load;

use PHPUnit\Framework\TestCase;
use IblockElementReader\IblockElementFetcher;
use IblockElementReader\IblockElementReader;
use IblockElementReader\Load\ElementLoader;
use IblockElementReader\Tests\Fixture\InMemoryElementRepository;

final class ElementSectionTest extends TestCase
{
    private InMemoryElementRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryElementRepository(
            ['ID' => '10', 'IBLOCK_ID' => '5', 'IBLOCK_SECTION_ID' => '12', 'NAME' => 'В разделе'],
            ['ID' => '11', 'IBLOCK_ID' => '5', 'IBLOCK_SECTION_ID' => null, 'NAME' => 'Без раздела'],
            ['ID' => '13', 'IBLOCK_ID' => '5', 'IBLOCK_SECTION_ID' => '0', 'NAME' => 'Раздел 0'],
        );
    }

    public function testLoaderSelectsSectionIdWithTheSameQuery(): void
    {
        $records = (new ElementLoader(elementRepository: $this->repository))->load([5], [10, 11, 13], null, null);

        self::assertCount(1, $this->repository->queries);
        self::assertContains('IBLOCK_SECTION_ID', $this->repository->queries[0]['select']);

        self::assertSame(12, $records->get(10)->iblockSectionId);
        self::assertNull($records->get(11)->iblockSectionId);
        self::assertNull($records->get(13)->iblockSectionId);
    }

    public function testElementWithAndWithoutSection(): void
    {
        $elements = $this->reader()->filter(id: [10, 11], iblock: 5)->withFields(['NAME'])->get()->elements;

        self::assertSame(10, $elements[0]->id);
        self::assertSame(12, $elements[0]->iblockSectionId);
        self::assertSame(11, $elements[1]->id);
        self::assertNull($elements[1]->iblockSectionId);
    }

    public function testSectionIdIsFilledWithoutWithFields(): void
    {
        $element = $this->reader()->filter(id: 10, iblock: 5)->first();

        self::assertSame(12, $element->iblockSectionId);
    }

    public function testExplicitSectionFieldStaysInSelectOnce(): void
    {
        $element = $this->reader()->filter(id: 10, iblock: 5)->withFields(['ID', 'IBLOCK_SECTION_ID'])->first();

        self::assertSame(12, $element->iblockSectionId);
        self::assertSame(
            1,
            count(array_keys($this->repository->queries[0]['select'], 'IBLOCK_SECTION_ID', true)),
        );
    }

    private function reader(): IblockElementReader
    {
        return new IblockElementReader(new IblockElementFetcher(new ElementLoader(elementRepository: $this->repository)));
    }
}
