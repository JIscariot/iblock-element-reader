<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Resolve;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Resolve\ResolvedValueDistributor;

final class ResolvedValueDistributorTest extends TestCase
{
    public function testDistributesSingleValuesPerElement(): void
    {
        $byElement = [10 => 5, 20 => 7];
        $map = [5 => 'make-a', 7 => 'make-b'];

        $result = (new ResolvedValueDistributor())->distribute($byElement, $map);

        self::assertSame('make-a', $result[10]);
        self::assertSame('make-b', $result[20]);
    }

    public function testDistributesMultipleValuesPerElement(): void
    {
        $byElement = [10 => [1, 2], 20 => [2, 3]];
        $map = [1 => 'a', 2 => 'b', 3 => 'c'];

        $result = (new ResolvedValueDistributor())->distribute($byElement, $map);

        self::assertSame(['a', 'b'], $result[10]);
        self::assertSame(['b', 'c'], $result[20]);
    }

    public function testResolvesNullAndEmptyStringViaSameMapEntry(): void
    {
        $map = [
            '0'    => false,
            '1'    => true,
            ''     => false,
            'null' => false,
        ];

        $distributor = new ResolvedValueDistributor();

        self::assertFalse($distributor->distribute([10 => null], $map)[10]);
        self::assertFalse($distributor->distribute([10 => ''], $map)[10]);
        self::assertFalse($distributor->distribute([10 => '0'], $map)[10]);
        self::assertTrue($distributor->distribute([10 => '1'], $map)[10]);
    }

    public function testPreservesExplicitFalseResolvedValue(): void
    {
        $map = ['0' => false];

        $distributor = new ResolvedValueDistributor();

        self::assertFalse($distributor->distribute([10 => '0'], $map)[10]);
    }
}
