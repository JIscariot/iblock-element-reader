<?php

declare(strict_types=1);

namespace IblockElementReader\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use IblockElementReader\Exception\IblockScopeException;
use IblockElementReader\IblockElementFetcher;
use IblockElementReader\IblockElementReader;
use IblockElementReader\Load\ElementLoaderInterface;
use IblockElementReader\Model\Record\IblockElementRecordCollection;

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

    /**
     * @return iterable<string, array{0: callable(IblockElementReader): IblockElementReader}>
     */
    public static function emptyIdSelections(): iterable
    {
        yield 'id: [] без iblock' => [static fn(IblockElementReader $r) => $r->filter(id: [])];
        yield 'id: [] с iblock' => [static fn(IblockElementReader $r) => $r->filter(id: [], iblock: [5])];
        yield 'iblock, затем id: []' => [static fn(IblockElementReader $r) => $r->filter(iblock: [5])->filter(id: [])];
        yield 'id: [] с полями и свойствами' => [
            static fn(IblockElementReader $r) => $r->filter(id: [], iblock: [5])
                ->withFields(['*'])
                ->withProps(['PRICE.VALUE']),
        ];
        yield 'id: [0] — валидных ID нет' => [static fn(IblockElementReader $r) => $r->filter(id: [0], iblock: [5])];
    }

    #[DataProvider('emptyIdSelections')]
    public function testEmptyIdListReturnsEmptyCollectionWithoutQueries(callable $configure): void
    {
        $loader = $this->createMock(ElementLoaderInterface::class);
        $loader->expects(self::never())->method('load');

        $reader = $configure(new IblockElementReader(new IblockElementFetcher($loader)));

        self::assertSame([], $reader->get()->elements);
    }

    #[DataProvider('emptyIdSelections')]
    public function testEmptyIdListFirstReturnsNullWithoutQueries(callable $configure): void
    {
        $loader = $this->createMock(ElementLoaderInterface::class);
        $loader->expects(self::never())->method('load');

        $reader = $configure(new IblockElementReader(new IblockElementFetcher($loader)));

        self::assertNull($reader->first());
    }

    public function testWithoutIdFilterLoadsWholeIblock(): void
    {
        $loader = $this->createMock(ElementLoaderInterface::class);
        $loader->expects(self::once())
            ->method('load')
            ->with([5], [], null, null)
            ->willReturn(new IblockElementRecordCollection());

        $reader = new IblockElementReader(new IblockElementFetcher($loader));

        self::assertSame([], $reader->filter(iblock: [5])->get()->elements);
    }

    public function testNullIdKeepsWholeIblockScope(): void
    {
        $loader = $this->createMock(ElementLoaderInterface::class);
        $loader->expects(self::once())
            ->method('load')
            ->with([5], [], null, null)
            ->willReturn(new IblockElementRecordCollection());

        $reader = new IblockElementReader(new IblockElementFetcher($loader));

        self::assertSame([], $reader->filter(id: null, iblock: [5])->get()->elements);
    }

    public function testMissingIdsAreQueriedNormally(): void
    {
        $loader = $this->createMock(ElementLoaderInterface::class);
        $loader->expects(self::once())
            ->method('load')
            ->with([5], [999999], null, null)
            ->willReturn(new IblockElementRecordCollection());

        $reader = new IblockElementReader(new IblockElementFetcher($loader));

        self::assertSame([], $reader->filter(id: [999999], iblock: [5])->get()->elements);
    }
}
