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
use IblockElementReader\Model\Section;
use IblockElementReader\Resolve\AutoResolveMapsLoader;
use IblockElementReader\Resolve\Contract\UserTypePropertyResolver;
use IblockElementReader\Resolve\PropertyResolveContext;
use IblockElementReader\Resolve\PropertyResolverRegistry;
use IblockElementReader\Resolve\ResolvePipeline;
use IblockElementReader\Tests\Fixture\InMemoryPropertyDefinitionRepository;
use IblockElementReader\Tests\Fixture\InMemorySectionRepository;

final class SectionPropertyResolveTest extends TestCase
{
    private const IBLOCK = 5;

    private IblockPropertyDefinitionRecord $sectionLink;
    private IblockPropertyDefinitionRecord $sectionLinks;
    private InMemorySectionRepository $sections;

    protected function setUp(): void
    {
        $this->sectionLink = $this->definition(1, 'SECTION_LINK', multiple: false);
        $this->sectionLinks = $this->definition(2, 'SECTION_LINKS', multiple: true);
        $this->sections = new InMemorySectionRepository(
            new Section(id: 100, iblockId: 3, name: 'Легковые', code: 'cars', sort: 10, parentId: null, isActive: true),
            new Section(id: 101, iblockId: 3, name: 'Седаны', code: 'sedans', sort: 20, parentId: 100, isActive: true),
            new Section(id: 102, iblockId: 3, name: 'Архив', code: null, sort: 500, parentId: null, isActive: false),
        );
    }

    public function testSinglePropertyResolvesToSection(): void
    {
        $element = $this->resolve([$this->record(10, [[$this->sectionLink, '101']])], ['SECTION_LINK.VALUE'])[0];

        $value = $element->properties['SECTION_LINK']->value;
        self::assertInstanceOf(IblockPropertyValue::class, $value);
        self::assertSame('101', $value->raw);
        self::assertInstanceOf(Section::class, $value->resolved);
        self::assertSame(101, $value->resolved->id);
        self::assertSame('Седаны', $value->resolved->name);
        self::assertSame('sedans', $value->resolved->code);
        self::assertSame(100, $value->resolved->parentId);
    }

    public function testMultiplePropertyResolvesEachValueToSection(): void
    {
        $element = $this->resolve(
            [$this->record(10, [[$this->sectionLinks, '100'], [$this->sectionLinks, '102']])],
            ['SECTION_LINKS.VALUE'],
        )[0];

        $values = $element->properties['SECTION_LINKS']->value;
        self::assertCount(2, $values);
        self::assertSame(['100', '102'], array_map(static fn(IblockPropertyValue $v) => $v->raw, $values));
        self::assertSame(['Легковые', 'Архив'], array_map(static fn(IblockPropertyValue $v) => $v->resolved->name, $values));
        self::assertFalse($values[1]->resolved->isActive);
    }

    public function testMissingSectionResolvesToNullAndKeepsRaw(): void
    {
        $element = $this->resolve(
            [$this->record(10, [[$this->sectionLink, '999'], [$this->sectionLinks, '100'], [$this->sectionLinks, '998']])],
            ['SECTION_LINK.VALUE', 'SECTION_LINKS.VALUE'],
        )[0];

        self::assertSame('999', $element->properties['SECTION_LINK']->value->raw);
        self::assertNull($element->properties['SECTION_LINK']->value->resolved);

        [$existing, $missing] = $element->properties['SECTION_LINKS']->value;
        self::assertSame(100, $existing->resolved->id);
        self::assertSame('998', $missing->raw);
        self::assertNull($missing->resolved);
    }

    public function testSectionsAreLoadedWithOneQueryPerGet(): void
    {
        $elements = $this->resolve(
            [
                $this->record(10, [[$this->sectionLink, '100'], [$this->sectionLinks, '101'], [$this->sectionLinks, '102']]),
                $this->record(11, [[$this->sectionLink, '100'], [$this->sectionLinks, '101']]),
            ],
            ['SECTION_LINK.VALUE', 'SECTION_LINKS.VALUE'],
        );

        self::assertCount(1, $this->sections->queries);
        self::assertSame([100, 101, 102], $this->sorted($this->sections->queries[0]));

        $first = $elements[0]->properties['SECTION_LINK']->value->resolved;
        $second = $elements[1]->properties['SECTION_LINK']->value->resolved;
        self::assertSame(100, $first->id);
        self::assertSame($first, $second);
    }

    public function testWithoutValueSuffixSectionIsNotLoaded(): void
    {
        $element = $this->resolve([$this->record(10, [[$this->sectionLink, '101']])], ['SECTION_LINK'])[0];

        self::assertSame('101', $element->properties['SECTION_LINK']->value->raw);
        self::assertNull($element->properties['SECTION_LINK']->value->resolved);
        self::assertSame([], $this->sections->queries);
    }

    public function testCustomUserTypeResolverHasPriorityForSectionProperty(): void
    {
        $custom = $this->definition(3, 'CATEGORY', multiple: false, userType: 'customSection');
        $resolver = new class implements UserTypePropertyResolver {
            public function userType(): string
            {
                return 'customSection';
            }

            public function resolve(PropertyResolveContext $context): array
            {
                return ['101' => 'custom:101'];
            }
        };

        $element = $this->resolve(
            [$this->record(10, [[$custom, '101'], [$this->sectionLink, '100']])],
            ['CATEGORY.VALUE', 'SECTION_LINK.VALUE'],
            [$resolver],
            [$custom, $this->sectionLink],
        )[0];

        self::assertSame('custom:101', $element->properties['CATEGORY']->value->resolved);
        self::assertSame(100, $element->properties['SECTION_LINK']->value->resolved->id);
        self::assertSame([[100]], $this->sections->queries, 'ID раздела из CATEGORY не должен предзагружаться');
    }

    public function testSectionFromArray(): void
    {
        $section = Section::fromArray([
            'ID' => '7',
            'IBLOCK_ID' => '3',
            'NAME' => 'Кроссоверы',
            'CODE' => '',
            'SORT' => '500',
            'IBLOCK_SECTION_ID' => null,
            'ACTIVE' => 'N',
        ]);

        self::assertSame(7, $section->id);
        self::assertSame(3, $section->iblockId);
        self::assertSame('Кроссоверы', $section->name);
        self::assertNull($section->code);
        self::assertSame(500, $section->sort);
        self::assertNull($section->parentId);
        self::assertFalse($section->isActive);
    }

    /**
     * @param IblockElementRecord[] $records
     * @param string[] $props
     * @param UserTypePropertyResolver[] $resolvers
     * @param IblockPropertyDefinitionRecord[]|null $definitions
     * @return list<IblockElement>
     */
    private function resolve(array $records, array $props, array $resolvers = [], ?array $definitions = null): array
    {
        $pipeline = new ResolvePipeline(
            resolverRegistry: new PropertyResolverRegistry($resolvers),
            mapsLoader: new AutoResolveMapsLoader(sectionRepository: $this->sections),
            propertyDefinitionRepository: (new InMemoryPropertyDefinitionRepository())->add(
                self::IBLOCK,
                ...($definitions ?? [$this->sectionLink, $this->sectionLinks]),
            ),
        );

        return $pipeline->resolve(
            elements: new IblockElementRecordCollection(...$records),
            order: array_map(static fn(IblockElementRecord $r) => $r->id, $records),
            fields: null,
            props: $props,
        )->elements;
    }

    /**
     * @param list<array{IblockPropertyDefinitionRecord, mixed}> $values
     */
    private function record(int $id, array $values): IblockElementRecord
    {
        return new IblockElementRecord(
            id: $id,
            iblockId: self::IBLOCK,
            fields: [],
            properties: new IblockPropertyValueRecordCollection(
                ...array_map(
                    static fn(array $pair): IblockPropertyValueRecord => new IblockPropertyValueRecord($pair[0], $pair[1]),
                    $values,
                ),
            ),
        );
    }

    private function definition(int $id, string $code, bool $multiple, ?string $userType = null): IblockPropertyDefinitionRecord
    {
        return new IblockPropertyDefinitionRecord(
            id: $id,
            code: $code,
            name: $code,
            type: PropertyTypeEnum::SECTION,
            multiple: $multiple,
            userType: $userType,
            linkIblockId: 3,
        );
    }

    /**
     * @param int[] $ids
     * @return int[]
     */
    private function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }
}
