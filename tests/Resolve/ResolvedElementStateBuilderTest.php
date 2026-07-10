<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Resolve;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Resolve\ResolvedElementStateBuilder;

final class ResolvedElementStateBuilderTest extends TestCase
{
    public function testBuildsResolvedElementState(): void
    {
        $builder = ResolvedElementStateBuilder::create(10, 5, ['NAME' => 'Bike']);
        $builder->setProp('VIN', 'ABC');

        $state = $builder->build();

        self::assertSame(10, $state->id);
        self::assertSame(5, $state->iblockId);
        self::assertSame('Bike', $state->field('NAME'));
        self::assertSame('ABC', $state->prop('VIN'));
    }
}
