<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Resolve;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Enum\PropertyTypeEnum;
use IblockElementReader\Model\IblockElement;
use IblockElementReader\Model\IblockPropertyValue;
use IblockElementReader\Model\Record\IblockElementRecord;
use IblockElementReader\Model\Record\IblockElementRecordCollection;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;
use IblockElementReader\Model\Record\IblockPropertyValueRecord;
use IblockElementReader\Model\Record\IblockPropertyValueRecordCollection;
use IblockElementReader\Resolve\InMemoryAutoResolveMapsLoader;
use IblockElementReader\Resolve\ResolvePipeline;
use IblockElementReader\Tests\Fixture\InMemoryElementLoader;
use IblockElementReader\Tests\Fixture\InMemoryPropertyDefinitionRepository;

final class NestedPropertyDescriptionTest extends TestCase
{
    private const IBLOCK = 5;
    private const LINK_IBLOCK = 7;

    private IblockPropertyDefinitionRecord $link;
    private IblockPropertyDefinitionRecord $gallery;
    private IblockPropertyDefinitionRecord $mainPhoto;
    private IblockPropertyDefinitionRecord $tags;
    private IblockPropertyDefinitionRecord $color;
    private InMemoryElementLoader $loader;

    protected function setUp(): void
    {
        $this->link = $this->definition(1, 'LINK', PropertyTypeEnum::ELEMENT, multiple: false, linkIblockId: self::LINK_IBLOCK);
        $this->gallery = $this->definition(11, 'GALLERY', PropertyTypeEnum::STRING, multiple: true, withDescription: true);
        $this->mainPhoto = $this->definition(12, 'MAIN_PHOTO', PropertyTypeEnum::STRING, multiple: false, withDescription: true);
        $this->tags = $this->definition(13, 'TAGS', PropertyTypeEnum::STRING, multiple: true);
        $this->color = $this->definition(14, 'COLOR', PropertyTypeEnum::STRING, multiple: false);

        // Порядок значений GALLERY нарочно не алфавитный: так они пришли из БД (ORDER BY ID).
        // У TAGS и COLOR нет WITH_DESCRIPTION, но в сырых данных описание есть — в модель оно попасть не должно.
        $this->loader = new InMemoryElementLoader(
            $this->record(100, self::LINK_IBLOCK, [
                [$this->gallery, 'rear.jpg', 'Вид сзади'],
                [$this->gallery, 'front.jpg', 'Вид спереди'],
                [$this->gallery, 'cabin.jpg', 'Салон'],
                [$this->mainPhoto, 'main.jpg', 'Главное фото'],
                [$this->tags, 'new', 'не должно попасть'],
                [$this->tags, 'sale', null],
                [$this->color, 'red', 'не должно попасть'],
            ]),
        );
    }

    public function testMultipleNestedPropertyKeepsDescriptionsAndOrder(): void
    {
        $linked = $this->linked($this->resolve(['LINK.VALUE.GALLERY.VALUE'])[0]);

        $values = $linked->properties['GALLERY']->value;
        self::assertIsList($values);
        self::assertContainsOnlyInstancesOf(IblockPropertyValue::class, $values);
        self::assertSame(
            [
                ['rear.jpg', 'Вид сзади'],
                ['front.jpg', 'Вид спереди'],
                ['cabin.jpg', 'Салон'],
            ],
            array_map(static fn(IblockPropertyValue $v): array => [$v->raw, $v->description], $values),
        );
    }

    public function testMultipleNestedPropertyWithoutValueSuffixKeepsDescriptions(): void
    {
        $linked = $this->linked($this->resolve(['LINK.VALUE.GALLERY'])[0]);

        self::assertSame(
            ['Вид сзади', 'Вид спереди', 'Салон'],
            array_map(static fn(IblockPropertyValue $v) => $v->description, $linked->properties['GALLERY']->value),
        );
    }

    public function testSingleNestedPropertyKeepsDescription(): void
    {
        $linked = $this->linked($this->resolve(['LINK.VALUE.MAIN_PHOTO.VALUE'])[0]);

        $value = $linked->properties['MAIN_PHOTO']->value;
        self::assertInstanceOf(IblockPropertyValue::class, $value);
        self::assertSame('main.jpg', $value->raw);
        self::assertSame('Главное фото', $value->description);
    }

    public function testPropertiesWithoutDescriptionHaveNullDescription(): void
    {
        $linked = $this->linked($this->resolve(['LINK.VALUE.TAGS.VALUE', 'LINK.VALUE.COLOR.VALUE'])[0]);

        self::assertSame(
            [['new', null], ['sale', null]],
            array_map(static fn(IblockPropertyValue $v): array => [$v->raw, $v->description], $linked->properties['TAGS']->value),
        );
        self::assertSame('red', $linked->properties['COLOR']->value->raw);
        self::assertNull($linked->properties['COLOR']->value->description);
    }

    public function testCachedLinkedElementKeepsDescriptions(): void
    {
        $elements = $this->resolve(['LINK.VALUE.GALLERY.VALUE'], rootIds: [10, 20]);

        self::assertCount(1, $this->loader->calls, 'связанный элемент загружается один раз');

        foreach ($elements as $element) {
            self::assertSame(
                ['Вид сзади', 'Вид спереди', 'Салон'],
                array_map(static fn(IblockPropertyValue $v) => $v->description, $this->linked($element)->properties['GALLERY']->value),
            );
        }
    }

    public function testLinkedElementHasSectionId(): void
    {
        $this->loader = new InMemoryElementLoader(
            new IblockElementRecord(
                id: 100,
                iblockId: self::LINK_IBLOCK,
                fields: [],
                properties: new IblockPropertyValueRecordCollection(),
                iblockSectionId: 33,
            ),
            new IblockElementRecord(
                id: 101,
                iblockId: self::LINK_IBLOCK,
                fields: [],
                properties: new IblockPropertyValueRecordCollection(),
            ),
        );

        $records = new IblockElementRecordCollection(
            $this->record(10, self::IBLOCK, [[$this->link, '100', null]]),
            $this->record(11, self::IBLOCK, [[$this->link, '101', null]]),
        );

        [$inSection, $withoutSection] = $this->pipeline()->resolve(
            elements: $records,
            order: [10, 11],
            fields: null,
            props: ['LINK.VALUE'],
        )->elements;

        self::assertSame(33, $inSection->property('LINK')->first()->resolved->iblockSectionId);
        self::assertNull($withoutSection->property('LINK')->first()->resolved->iblockSectionId);
        self::assertCount(1, $this->loader->calls, 'оба связанных элемента — одним запросом');
    }

    public function testTopLevelPropertiesFollowTheSameRules(): void
    {
        $element = $this->pipeline()->resolve(
            elements: new IblockElementRecordCollection($this->record(100, self::LINK_IBLOCK, [
                [$this->gallery, 'rear.jpg', 'Вид сзади'],
                [$this->gallery, 'front.jpg', 'Вид спереди'],
                [$this->tags, 'new', 'не должно попасть'],
            ])),
            order: [100],
            fields: null,
            props: ['GALLERY.VALUE', 'TAGS.VALUE'],
        )->elements[0];

        self::assertSame(
            ['Вид сзади', 'Вид спереди'],
            array_map(static fn(IblockPropertyValue $v) => $v->description, $element->properties['GALLERY']->value),
        );
        self::assertNull($element->properties['TAGS']->value[0]->description);
    }

    /**
     * @param string[] $props
     * @param int[] $rootIds
     * @return list<IblockElement>
     */
    private function resolve(array $props, array $rootIds = [10]): array
    {
        $records = array_map(fn(int $id) => $this->record($id, self::IBLOCK, [[$this->link, '100', null]]), $rootIds);

        return $this->pipeline()->resolve(
            elements: new IblockElementRecordCollection(...$records),
            order: $rootIds,
            fields: null,
            props: $props,
        )->elements;
    }

    private function pipeline(): ResolvePipeline
    {
        return new ResolvePipeline(
            mapsLoader: new InMemoryAutoResolveMapsLoader(),
            propertyDefinitionRepository: (new InMemoryPropertyDefinitionRepository())
                ->add(self::IBLOCK, $this->link)
                ->add(self::LINK_IBLOCK, $this->gallery, $this->mainPhoto, $this->tags, $this->color),
            elementLoader: $this->loader,
        );
    }

    private function linked(IblockElement $element): IblockElement
    {
        $linked = $element->properties['LINK']->value->resolved;
        self::assertInstanceOf(IblockElement::class, $linked);
        self::assertSame(100, $linked->id);

        return $linked;
    }

    /**
     * @param list<array{IblockPropertyDefinitionRecord, mixed, ?string}> $values
     */
    private function record(int $id, int $iblockId, array $values): IblockElementRecord
    {
        return new IblockElementRecord(
            id: $id,
            iblockId: $iblockId,
            fields: [],
            properties: new IblockPropertyValueRecordCollection(
                ...array_map(
                    static fn(array $v): IblockPropertyValueRecord => new IblockPropertyValueRecord($v[0], $v[1], $v[2]),
                    $values,
                ),
            ),
        );
    }

    private function definition(
        int $id,
        string $code,
        PropertyTypeEnum $type,
        bool $multiple,
        bool $withDescription = false,
        ?int $linkIblockId = null,
    ): IblockPropertyDefinitionRecord {
        return new IblockPropertyDefinitionRecord(
            id: $id,
            code: $code,
            name: $code,
            type: $type,
            multiple: $multiple,
            linkIblockId: $linkIblockId,
            withDescription: $withDescription,
        );
    }
}
