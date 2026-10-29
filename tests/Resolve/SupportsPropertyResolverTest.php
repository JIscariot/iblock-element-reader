<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Resolve;

use PHPUnit\Framework\TestCase;
use IblockElementReader\Enum\PropertyTypeEnum;
use IblockElementReader\Enum\PropertyUserTypeEnum;
use IblockElementReader\Model\IblockElement;
use IblockElementReader\Model\Record\IblockElementRecord;
use IblockElementReader\Model\Record\IblockElementRecordCollection;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;
use IblockElementReader\Model\Record\IblockPropertyValueRecord;
use IblockElementReader\Model\Record\IblockPropertyValueRecordCollection;
use IblockElementReader\Resolve\Contract\UserTypePropertyResolver;
use IblockElementReader\Resolve\InMemoryAutoResolveMapsLoader;
use IblockElementReader\Resolve\PropertyResolveContext;
use IblockElementReader\Resolve\PropertyResolverRegistry;
use IblockElementReader\Resolve\ResolvePipeline;
use IblockElementReader\Tests\Fixture\AnotherMakeDictionaryResolver;
use IblockElementReader\Tests\Fixture\CodeResolver;
use IblockElementReader\Tests\Fixture\InMemoryPropertyDefinitionRepository;
use IblockElementReader\Tests\Fixture\MakeDictionaryResolver;

final class SupportsPropertyResolverTest extends TestCase
{
    private const IBLOCK = 5;

    public function testResolversWithSameUserTypeAreChosenByPropertyCode(): void
    {
        $make = $this->definition(1, 'MAKE_ID', PropertyTypeEnum::STRING, 'DependentSelect');
        $model = $this->definition(2, 'MODEL_ID', PropertyTypeEnum::STRING, 'DependentSelect');

        $element = $this->resolve(
            definitions: [$make, $model],
            values: [[$make, '10'], [$model, '20']],
            props: ['MAKE_ID.VALUE', 'MODEL_ID.VALUE'],
            resolvers: [
                new CodeResolver('MAKE_ID', 'make', 'DependentSelect'),
                new CodeResolver('MODEL_ID', 'model', 'DependentSelect'),
            ],
        );

        self::assertSame('make:10', $element->properties['MAKE_ID']->value->resolved);
        self::assertSame('model:20', $element->properties['MODEL_ID']->value->resolved);
    }

    public function testSupportsResolverIsUsedForElementPropertyInsteadOfLinkedElementLoading(): void
    {
        // Без резолвера STORE.VALUE пошёл бы в ElementLoader (БД) — в тестовом окружении это упало бы.
        $store = $this->definition(1, 'STORE', PropertyTypeEnum::ELEMENT, linkIblockId: 7);
        $resolver = new CodeResolver('STORE', 'store');

        $element = $this->resolve(
            definitions: [$store],
            values: [[$store, '100']],
            props: ['STORE.VALUE'],
            resolvers: [$resolver],
        );

        self::assertSame('100', $element->properties['STORE']->value->raw);
        self::assertSame('store:100', $element->properties['STORE']->value->resolved);
        self::assertSame(1, $resolver->resolveCalls);
    }

    public function testSupportsResolverIsUsedForSectionPropertyInsteadOfPreload(): void
    {
        $section = $this->definition(1, 'SECTION', PropertyTypeEnum::SECTION);

        $element = $this->resolve(
            definitions: [$section],
            values: [[$section, '42']],
            props: ['SECTION.VALUE'],
            resolvers: [new CodeResolver('SECTION', 'section')],
        );

        self::assertSame('section:42', $element->properties['SECTION']->value->resolved);
    }

    public function testSupportsResolverOverridesScalarRuleForPlainString(): void
    {
        $vin = $this->definition(1, 'VIN', PropertyTypeEnum::STRING);

        $element = $this->resolve(
            definitions: [$vin],
            values: [[$vin, 'ABC']],
            props: ['VIN.VALUE'],
            resolvers: [new CodeResolver('VIN', 'vin')],
        );

        self::assertSame('vin:ABC', $element->properties['VIN']->value->resolved);
    }

    public function testFirstRegisteredSupportsResolverWins(): void
    {
        $definition = $this->definition(1, 'MAKE_ID', PropertyTypeEnum::STRING);
        $first = new CodeResolver('MAKE_ID', 'first');
        $second = new CodeResolver('MAKE_ID', 'second');

        $map = (new PropertyResolverRegistry([$first, $second]))->get($definition)($this->context($definition, ['1']));

        self::assertSame('first:1', $map['1']);
        self::assertSame(0, $second->supportsCalls);
    }

    public function testSupportsResolverHasPriorityOverUserTypeAndBuiltIn(): void
    {
        $date = $this->definition(1, 'SOLD_AT', PropertyTypeEnum::STRING, PropertyUserTypeEnum::DATE->value);
        $make = $this->definition(2, 'MAKE_ID', PropertyTypeEnum::STRING, 'makeDictionary');

        $registry = new PropertyResolverRegistry([
            new MakeDictionaryResolver(),
            new CodeResolver('SOLD_AT', 'date'),
            new CodeResolver('MAKE_ID', 'supports'),
        ]);

        self::assertSame('date:x', $registry->get($date)($this->context($date, ['x']))['x']);
        self::assertSame('supports:x', $registry->get($make)($this->context($make, ['x']))['x']);
    }

    public function testFallsBackToUserTypeThenBuiltInWhenNoSupportsResolverMatches(): void
    {
        $make = $this->definition(1, 'MAKE_ID', PropertyTypeEnum::STRING, 'makeDictionary');
        $date = $this->definition(2, 'SOLD_AT', PropertyTypeEnum::STRING, PropertyUserTypeEnum::DATE->value);
        $plain = $this->definition(3, 'VIN', PropertyTypeEnum::STRING);

        $registry = new PropertyResolverRegistry([
            new CodeResolver('OTHER', 'other'),
            new MakeDictionaryResolver(),
        ]);

        self::assertSame('make:7', $registry->get($make)($this->context($make, ['7']))['7']);
        self::assertInstanceOf(
            \DateTimeImmutable::class,
            $registry->get($date)($this->context($date, ['2024-01-10']))['2024-01-10'],
        );
        self::assertFalse($registry->has($plain));
        self::assertNull($registry->get($plain));
    }

    public function testSupportsIsCalledOncePerDefinition(): void
    {
        $make = $this->definition(1, 'MAKE_ID', PropertyTypeEnum::STRING);
        $model = $this->definition(2, 'MODEL_ID', PropertyTypeEnum::STRING);
        $resolver = new CodeResolver('MAKE_ID', 'make');
        $registry = new PropertyResolverRegistry([$resolver]);

        for ($i = 0; $i < 3; $i++) {
            $registry->has($make);
            $registry->get($make);
            $registry->has($model);
            $registry->get($model);
        }

        self::assertSame(2, $resolver->supportsCalls);
    }

    public function testSupportsIsCalledOncePerDefinitionDuringPipeline(): void
    {
        $make = $this->definition(1, 'MAKE_ID', PropertyTypeEnum::STRING);
        $resolver = new CodeResolver('MAKE_ID', 'make');

        $elements = new IblockElementRecordCollection(
            $this->record(10, [[$make, 'a']]),
            $this->record(11, [[$make, 'b']]),
            $this->record(12, [[$make, 'a']]),
        );

        $collection = $this->pipeline([$make], [$resolver])->resolve(
            elements: $elements,
            order: [10, 11, 12],
            fields: null,
            props: ['MAKE_ID.VALUE'],
        );

        self::assertSame(
            ['make:a', 'make:b', 'make:a'],
            array_map(static fn(IblockElement $e) => $e->properties['MAKE_ID']->value->resolved, $collection->elements),
        );
        self::assertSame(1, $resolver->supportsCalls);
        self::assertSame(1, $resolver->resolveCalls);
    }

    public function testTwoUserTypeResolversWithSameUserTypeThrow(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(MakeDictionaryResolver::class);
        $this->expectExceptionMessage(AnotherMakeDictionaryResolver::class);

        new PropertyResolverRegistry([new MakeDictionaryResolver(), new AnotherMakeDictionaryResolver()]);
    }

    public function testSupportsResolversWithSameUserTypeDoNotThrow(): void
    {
        $registry = new PropertyResolverRegistry([
            new CodeResolver('A', 'a', 'makeDictionary'),
            new CodeResolver('B', 'b', 'makeDictionary'),
            new MakeDictionaryResolver(),
        ]);

        self::assertTrue($registry->has($this->definition(1, 'C', PropertyTypeEnum::STRING, 'makeDictionary')));
    }

    public function testLegacyUserTypeResolverWorksAsBefore(): void
    {
        $make = $this->definition(1, 'MAKE_ID', PropertyTypeEnum::NUMBER, 'makeDictionary');
        $plain = $this->definition(2, 'PRICE', PropertyTypeEnum::NUMBER);

        $element = $this->resolve(
            definitions: [$make, $plain],
            values: [[$make, '3'], [$plain, '1 500']],
            props: ['MAKE_ID.VALUE', 'PRICE.VALUE'],
            resolvers: [new MakeDictionaryResolver()],
        );

        self::assertSame('make:3', $element->properties['MAKE_ID']->value->resolved);
        self::assertSame(1500, $element->properties['PRICE']->value->resolved);

        $withoutValue = $this->resolve(
            definitions: [$make],
            values: [[$make, '3']],
            props: ['MAKE_ID'],
            resolvers: [new MakeDictionaryResolver()],
        );

        self::assertNull($withoutValue->properties['MAKE_ID']->value->resolved);
    }

    public function testLegacyResolverWithEmptyUserTypeDoesNotMatchPropertiesWithoutUserType(): void
    {
        $resolver = new class implements UserTypePropertyResolver {
            public function userType(): string
            {
                return '';
            }

            public function resolve(PropertyResolveContext $context): array
            {
                return [];
            }
        };

        $registry = new PropertyResolverRegistry([$resolver]);

        self::assertFalse($registry->has($this->definition(1, 'VIN', PropertyTypeEnum::STRING)));
    }

    /**
     * @param IblockPropertyDefinitionRecord[] $definitions
     * @param list<array{IblockPropertyDefinitionRecord, mixed}> $values
     * @param string[] $props
     * @param UserTypePropertyResolver[] $resolvers
     */
    private function resolve(array $definitions, array $values, array $props, array $resolvers): IblockElement
    {
        $collection = $this->pipeline($definitions, $resolvers)->resolve(
            elements: new IblockElementRecordCollection($this->record(10, $values)),
            order: [10],
            fields: null,
            props: $props,
        );

        return $collection->elements[0];
    }

    /**
     * @param IblockPropertyDefinitionRecord[] $definitions
     * @param UserTypePropertyResolver[] $resolvers
     */
    private function pipeline(array $definitions, array $resolvers): ResolvePipeline
    {
        return new ResolvePipeline(
            resolverRegistry: new PropertyResolverRegistry($resolvers),
            mapsLoader: new InMemoryAutoResolveMapsLoader(),
            propertyDefinitionRepository: (new InMemoryPropertyDefinitionRepository())->add(self::IBLOCK, ...$definitions),
        );
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

    /**
     * @param list<mixed> $values
     */
    private function context(IblockPropertyDefinitionRecord $definition, array $values): PropertyResolveContext
    {
        return new PropertyResolveContext(
            definition: $definition,
            uniqueValues: $values,
            uniqueDescriptions: [],
            byElement: [],
        );
    }

    private function definition(
        int $id,
        string $code,
        PropertyTypeEnum $type,
        ?string $userType = null,
        ?int $linkIblockId = null,
    ): IblockPropertyDefinitionRecord {
        return new IblockPropertyDefinitionRecord(
            id: $id,
            code: $code,
            name: $code,
            type: $type,
            multiple: false,
            userType: $userType,
            linkIblockId: $linkIblockId,
        );
    }
}
