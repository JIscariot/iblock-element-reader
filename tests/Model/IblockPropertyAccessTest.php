<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Model;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Enum\PropertyTypeEnum;
use IblockElementReader\Model\Enumerate;
use IblockElementReader\Model\IblockElement;
use IblockElementReader\Model\IblockProperty;
use IblockElementReader\Model\IblockPropertyValue;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;

final class IblockPropertyAccessTest extends TestCase
{
    // --- values() ---

    public function testValuesOfSingleProperty(): void
    {
        $value = new IblockPropertyValue(raw: '5', resolved: 5);

        self::assertSame([$value], $this->single($value)->values());
    }

    public function testValuesOfMultipleProperty(): void
    {
        $a = new IblockPropertyValue(raw: 'a');
        $b = new IblockPropertyValue(raw: 'b');

        self::assertSame([$a, $b], $this->multiple($a, $b)->values());
    }

    public function testValuesOfMultiplePropertyIsAlwaysList(): void
    {
        $a = new IblockPropertyValue(raw: 'a');
        $b = new IblockPropertyValue(raw: 'b');
        $property = new IblockProperty($this->definition(multiple: true), [3 => $a, 7 => $b]);

        self::assertSame([$a, $b], $property->values());
    }

    public function testValuesOfEmptyProperty(): void
    {
        self::assertSame([], $this->single(null)->values());
        self::assertSame([], $this->multiple()->values());
    }

    // --- first() ---

    public function testFirstOfSingleProperty(): void
    {
        $value = new IblockPropertyValue(raw: '5');

        self::assertSame($value, $this->single($value)->first());
    }

    public function testFirstOfMultipleProperty(): void
    {
        $a = new IblockPropertyValue(raw: 'a');

        self::assertSame($a, $this->multiple($a, new IblockPropertyValue(raw: 'b'))->first());
    }

    public function testFirstOfEmptyProperty(): void
    {
        self::assertNull($this->single(null)->first());
        self::assertNull($this->multiple()->first());
    }

    // --- resolved() ---

    public function testResolvedOfSingleProperty(): void
    {
        $enum = new Enumerate(id: 7, value: 'Новый');

        self::assertSame($enum, $this->single(new IblockPropertyValue(raw: '7', resolved: $enum))->resolved());
    }

    public function testResolvedFallsBackToRaw(): void
    {
        self::assertSame('7', $this->single(new IblockPropertyValue(raw: '7', resolved: null))->resolved());
    }

    public function testResolvedKeepsFalsyResolvedValues(): void
    {
        self::assertSame(0, $this->single(new IblockPropertyValue(raw: '0', resolved: 0))->resolved());
        self::assertFalse($this->single(new IblockPropertyValue(raw: 'N', resolved: false))->resolved());
    }

    public function testResolvedOfMultiplePropertyIsFirstValue(): void
    {
        $property = $this->multiple(
            new IblockPropertyValue(raw: '1', resolved: 1.6),
            new IblockPropertyValue(raw: '2', resolved: 2),
        );

        self::assertSame(1.6, $property->resolved());
    }

    public function testResolvedOfEmptyProperty(): void
    {
        self::assertNull($this->single(null)->resolved());
        self::assertNull($this->multiple()->resolved());
    }

    // --- resolvedList() ---

    public function testResolvedListOfSingleProperty(): void
    {
        self::assertSame([5], $this->single(new IblockPropertyValue(raw: '5', resolved: 5))->resolvedList());
        self::assertSame(['x'], $this->single(new IblockPropertyValue(raw: 'x'))->resolvedList());
    }

    public function testResolvedListOfMultiplePropertySkipsNullAndEmptyStrings(): void
    {
        $property = $this->multiple(
            new IblockPropertyValue(raw: '10', resolved: 'Volkswagen'),
            new IblockPropertyValue(raw: '11', resolved: null),
            new IblockPropertyValue(raw: '', resolved: null),
            new IblockPropertyValue(raw: null, resolved: null),
            new IblockPropertyValue(raw: '0', resolved: 0),
            new IblockPropertyValue(raw: '12', resolved: ''),
        );

        // '12' → resolved '' не null, поэтому raw не подставляется, и значение отбрасывается как пустое
        self::assertSame(['Volkswagen', '11', 0], $property->resolvedList());
    }

    public function testResolvedListOfEmptyProperty(): void
    {
        self::assertSame([], $this->single(null)->resolvedList());
        self::assertSame([], $this->multiple()->resolvedList());
        self::assertSame([], $this->single(new IblockPropertyValue(raw: ''))->resolvedList());
    }

    // --- descriptions() ---

    public function testDescriptionsOfSingleProperty(): void
    {
        self::assertSame(['Главное фото'], $this->single(new IblockPropertyValue(raw: 'a.jpg', description: 'Главное фото'))->descriptions());
        self::assertSame([null], $this->single(new IblockPropertyValue(raw: 'a.jpg'))->descriptions());
    }

    public function testDescriptionsOfMultiplePropertyKeepOrder(): void
    {
        $property = $this->multiple(
            new IblockPropertyValue(raw: 'rear.jpg', description: 'Вид сзади'),
            new IblockPropertyValue(raw: '', description: 'Пустое значение'),
            new IblockPropertyValue(raw: 'front.jpg'),
        );

        self::assertSame(['Вид сзади', 'Пустое значение', null], $property->descriptions());
    }

    public function testDescriptionsOfEmptyProperty(): void
    {
        self::assertSame([], $this->single(null)->descriptions());
        self::assertSame([], $this->multiple()->descriptions());
    }

    // --- методы не меняют данные ---

    public function testAccessorsDoNotChangeValue(): void
    {
        $values = [new IblockPropertyValue(raw: 'a'), new IblockPropertyValue(raw: '')];
        $property = new IblockProperty($this->definition(multiple: true), $values);

        $property->values();
        $property->first();
        $property->resolved();
        $property->resolvedList();
        $property->descriptions();

        self::assertSame($values, $property->value);
    }

    // --- IblockElement ---

    public function testElementPropertyAndValue(): void
    {
        $make = $this->single(new IblockPropertyValue(raw: '10', resolved: 'Volkswagen'));
        $gallery = $this->multiple(new IblockPropertyValue(raw: 'a.jpg'), new IblockPropertyValue(raw: 'b.jpg'));
        $empty = $this->single(null);

        $element = new IblockElement(id: 1, iblockId: 5, properties: [
            'MAKE_ID' => $make,
            'GALLERY' => $gallery,
            'EMPTY' => $empty,
        ]);

        self::assertSame($make, $element->property('MAKE_ID'));
        self::assertSame('Volkswagen', $element->value('MAKE_ID'));
        self::assertSame('a.jpg', $element->value('GALLERY'));
        self::assertSame($empty, $element->property('EMPTY'));
        self::assertNull($element->value('EMPTY'));
    }

    public function testElementMissingProperty(): void
    {
        $element = new IblockElement(id: 1, iblockId: 5);

        self::assertNull($element->property('MISSING'));
        self::assertNull($element->value('MISSING'));
    }

    private function single(?IblockPropertyValue $value): IblockProperty
    {
        return new IblockProperty($this->definition(multiple: false), $value);
    }

    private function multiple(IblockPropertyValue ...$values): IblockProperty
    {
        return new IblockProperty($this->definition(multiple: true), $values);
    }

    private function definition(bool $multiple): IblockPropertyDefinitionRecord
    {
        return new IblockPropertyDefinitionRecord(
            id: 1,
            code: 'PROP',
            name: 'Prop',
            type: PropertyTypeEnum::STRING,
            multiple: $multiple,
        );
    }
}
