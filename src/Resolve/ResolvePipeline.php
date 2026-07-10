<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve;

use IblockElementReader\Enum\PropertyTypeEnum;
use IblockElementReader\Load\FieldSpec;
use IblockElementReader\Load\FieldSpecParser;
use IblockElementReader\Load\NestedDefinitionLoader;
use IblockElementReader\Resolve\Contract\AutoResolveMapsLoaderInterface;
use IblockElementReader\Repository\IblockPropertyDefinitionRepository;
use IblockElementReader\Model\IblockElement;
use IblockElementReader\Model\IblockElementCollection;
use IblockElementReader\Model\IblockProperty;
use IblockElementReader\Model\IblockPropertyValue;
use IblockElementReader\Model\Record\IblockElementRecord;
use IblockElementReader\Model\Record\IblockElementRecordCollection;
use IblockElementReader\Model\File;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;

final class ResolvePipeline
{
    public function __construct(
        private readonly PropertyResolverRegistry $resolverRegistry = new PropertyResolverRegistry(),
        private readonly FieldSpecParser $fieldSpecParser = new FieldSpecParser(),
        private readonly PropertySpecParser $propertySpecParser = new PropertySpecParser(),
        private readonly RawValueAggregator $aggregator = new RawValueAggregator(),
        private readonly ResolvedValueDistributor $distributor = new ResolvedValueDistributor(),
        private readonly LinkedElementCache $linkedElementCache = new LinkedElementCache(),
        private readonly ?AutoResolveMapsLoaderInterface $mapsLoader = null,
        private readonly IblockPropertyDefinitionRepository $propertyDefinitionRepository = new IblockPropertyDefinitionRepository(),
    ) {
    }

    /**
     * @param int[] $order
     * @param string[]|null $fields  null = только ID
     * @param string[]|null $props
     */
    public function resolve(
        IblockElementRecordCollection $elements,
        array $order,
        ?array $fields,
        ?array $props,
    ): IblockElementCollection {
        if ($elements->count() === 0) {
            return new IblockElementCollection([]);
        }

        $fieldSpecs = $this->fieldSpecParser->parse($fields);
        $iblockIds = $elements->iblockIds();

        $propSpecs = [];
        $definitionsByIblock = $this->loadDefinitionsByIblock($iblockIds, $props, $propSpecs);

        $mapsLoader = $this->mapsLoader ?? new AutoResolveMapsLoader();
        $maps = $mapsLoader->load($elements, $propSpecs, $definitionsByIblock, $fieldSpecs, $this->resolverRegistry);

        /** @var array<int, ResolvedElementStateBuilder> $builders */
        $builders = [];
        foreach ($elements as $element) {
            $builders[$element->id] = ResolvedElementStateBuilder::create(
                id: $element->id,
                iblockId: $element->iblockId,
                fields: $this->resolveFieldsFromMap($element->fields, $fieldSpecs, $maps),
            );
        }

        foreach ($propSpecs as $spec) {
            foreach ($iblockIds as $iblockId) {
                $definition = $definitionsByIblock[$iblockId][$spec->code] ?? null;
                if ($definition === null) {
                    continue;
                }

                $iblockElements = $elements->filterByIblock($iblockId);
                $resolvedByElement = $this->resolveProperty($iblockElements, $spec, $definition, $maps, $fields);

                foreach ($resolvedByElement as $elementId => $value) {
                    $builders[$elementId]?->setProp($spec->code, $value);
                }
            }
        }

        /** @var array<int, ResolvedElementState> $resolvedById */
        $resolvedById = [];
        foreach ($builders as $id => $builder) {
            $resolvedById[$id] = $builder->build();
        }

        return $this->assembleElements(
            elements: $elements,
            order: $order,
            resolvedById: $resolvedById,
            definitionsByIblock: $definitionsByIblock,
            props: $props,
        );
    }

    /**
     * @param int[] $order
     * @param array<int, ResolvedElementState> $resolvedById
     * @param array<int, array<string, IblockPropertyDefinitionRecord>> $definitionsByIblock
     * @param string[]|null $props
     */
    private function assembleElements(
        IblockElementRecordCollection $elements,
        array $order,
        array $resolvedById,
        array $definitionsByIblock,
        ?array $props,
    ): IblockElementCollection {
        $autoResolveByCode = $this->autoResolveByCode($props, $definitionsByIblock);
        $mapped = [];

        foreach ($order as $id) {
            $state = $resolvedById[$id] ?? null;
            if ($state === null) {
                continue;
            }

            $mapped[] = $this->mapElement(
                state: $state,
                element: $elements->get($state->id),
                autoResolveByCode: $autoResolveByCode,
                definitionsByIblock: $definitionsByIblock,
            );
        }

        return new IblockElementCollection(elements: $mapped);
    }

    /**
     * @param array<string, bool> $autoResolveByCode
     * @param array<int, array<string, IblockPropertyDefinitionRecord>> $definitionsByIblock
     */
    private function mapElement(
        ResolvedElementState $state,
        ?IblockElementRecord $element,
        array $autoResolveByCode,
        array $definitionsByIblock,
    ): IblockElement {
        $fieldSource = $element?->fields ?? $state->fields;

        return new IblockElement(
            id: $state->id,
            iblockId: $state->iblockId,
            name: $this->stringField($fieldSource, 'NAME'),
            code: $this->stringField($fieldSource, 'CODE'),
            sort: $this->intField($fieldSource, 'SORT'),
            detailPicture: $this->pictureField($fieldSource, 'DETAIL_PICTURE', $state, 'DETAIL_PICTURE'),
            previewPicture: $this->pictureField($fieldSource, 'PREVIEW_PICTURE', $state, 'PREVIEW_PICTURE'),
            detailText: $this->stringField($fieldSource, 'DETAIL_TEXT'),
            previewText: $this->stringField($fieldSource, 'PREVIEW_TEXT'),
            isActive: $this->boolField($fieldSource, 'ACTIVE'),
            updatedAt: $this->dateField($fieldSource, 'TIMESTAMP_X'),
            createdAt: $this->dateField($fieldSource, 'DATE_CREATE'),
            activeFromAt: $this->dateField($fieldSource, 'ACTIVE_FROM'),
            activeToAt: $this->dateField($fieldSource, 'ACTIVE_TO'),
            properties: $this->mapProperties($state, $element, $autoResolveByCode, $definitionsByIblock),
        );
    }

    /**
     * @param array<string, bool> $autoResolveByCode
     * @param array<int, array<string, IblockPropertyDefinitionRecord>> $definitionsByIblock
     * @return array<string, IblockProperty>
     */
    private function mapProperties(
        ResolvedElementState $state,
        ?IblockElementRecord $element,
        array $autoResolveByCode,
        array $definitionsByIblock,
    ): array {
        $definitions = $definitionsByIblock[$state->iblockId] ?? [];
        $properties = [];

        foreach ($definitions as $code => $definition) {
            $autoResolve = $autoResolveByCode[$code] ?? false;
            $value = $this->mapPropertyValue($code, $state, $element, $autoResolve, $definition);

            $properties[$code] = new IblockProperty(
                definition: $definition,
                value: $value,
            );
        }

        return $properties;
    }

    private function mapPropertyValue(
        string $code,
        ResolvedElementState $state,
        ?IblockElementRecord $element,
        bool $autoResolve,
        IblockPropertyDefinitionRecord $definition,
    ): null|IblockPropertyValue|array {
        if ($element === null) {
            return $this->mapPropertyValueFromStateOnly($code, $state, $definition);
        }

        if ($definition->multiple) {
            return $this->mapMultiplePropertyValue($code, $state, $element, $autoResolve);
        }

        $raw = $element->properties->rawValue($code);
        if ($raw === null && !$state->hasProp($code)) {
            return null;
        }

        $resolved = $autoResolve && $state->hasProp($code)
            ? $this->mapResolved($state->prop($code))
            : null;

        return new IblockPropertyValue(
            raw: $raw,
            resolved: $resolved,
            description: $element->properties->first($code)?->description,
        );
    }

    /**
     * @return IblockPropertyValue[]
     */
    private function mapMultiplePropertyValue(
        string $code,
        ResolvedElementState $state,
        IblockElementRecord $element,
        bool $autoResolve,
    ): array {
        $rawItems = $element->properties->all($code);
        $resolvedList = $autoResolve && $state->hasProp($code)
            ? ($state->prop($code) ?? [])
            : [];

        if (!is_array($resolvedList)) {
            $resolvedList = [$resolvedList];
        }

        if ($rawItems === [] && $resolvedList === []) {
            return [];
        }

        if ($rawItems === []) {
            return array_map(
                fn(mixed $resolved): IblockPropertyValue => new IblockPropertyValue(
                    raw: null,
                    resolved: $this->mapResolved($resolved),
                ),
                $resolvedList,
            );
        }

        $values = [];
        foreach ($rawItems as $index => $rawItem) {
            $values[] = new IblockPropertyValue(
                raw: $rawItem->value,
                resolved: $autoResolve
                    ? $this->mapResolved($resolvedList[$index] ?? null)
                    : null,
                description: $rawItem->description,
            );
        }

        return $values;
    }

    private function mapPropertyValueFromStateOnly(
        string $code,
        ResolvedElementState $state,
        IblockPropertyDefinitionRecord $definition,
    ): null|IblockPropertyValue|array {
        if (!$state->hasProp($code)) {
            return null;
        }

        $resolvedValue = $state->prop($code);

        if ($definition->multiple) {
            if (!is_array($resolvedValue)) {
                $resolvedValue = [$resolvedValue];
            }

            return array_map(
                fn(mixed $value): IblockPropertyValue => new IblockPropertyValue(
                    raw: null,
                    resolved: $this->mapResolved($value),
                ),
                $resolvedValue,
            );
        }

        return new IblockPropertyValue(
            raw: null,
            resolved: $this->mapResolved($resolvedValue),
        );
    }

    private function mapResolved(mixed $value): mixed
    {
        if ($value instanceof IblockElement) {
            return $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        if ($value === []) {
            return [];
        }

        if (array_is_list($value)) {
            return array_map(
                fn(mixed $item): mixed => $this->mapResolved($item),
                $value,
            );
        }

        return $value;
    }

    /**
     * @param string[]|null $props
     * @param array<int, array<string, IblockPropertyDefinitionRecord>> $definitionsByIblock
     * @return array<string, bool>
     */
    private function autoResolveByCode(
        ?array $props,
        array $definitionsByIblock,
    ): array {
        if ($props === null) {
            return [];
        }

        $parser = new PropertySpecParser();
        $map = [];

        if ($parser->needsExpansion($props)) {
            $autoResolve = $props === [] || in_array(PropertySpecParser::WILDCARD_VALUE, $props, true);

            foreach ($definitionsByIblock as $definitions) {
                foreach (array_keys($definitions) as $code) {
                    $map[$code] = $autoResolve;
                }
            }
        }

        foreach ($parser->parse($props) as $spec) {
            if ($spec->code === PropertySpecParser::WILDCARD) {
                continue;
            }

            $map[$spec->code] = $spec->autoResolve;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function pictureField(
        array $fields,
        string $name,
        ResolvedElementState $state,
        string $stateKey,
    ): null|int|File {
        $resolved = $state->field($stateKey);
        if ($resolved instanceof File) {
            return $resolved;
        }

        if (array_key_exists($name, $fields)) {
            return $this->intField($fields, $name);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function stringField(array $fields, string $name): ?string
    {
        if (!array_key_exists($name, $fields)) {
            return null;
        }

        $value = $fields[$name];

        return $value === null || $value === '' ? null : (string)$value;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function intField(array $fields, string $name): ?int
    {
        if (!array_key_exists($name, $fields)) {
            return null;
        }

        $value = $fields[$name];
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function boolField(array $fields, string $name): ?bool
    {
        if (!array_key_exists($name, $fields)) {
            return null;
        }

        $value = $fields[$name];
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        return (string)$value === 'Y';
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function dateField(array $fields, string $name): ?\DateTimeInterface
    {
        if (!array_key_exists($name, $fields)) {
            return null;
        }

        $value = $fields[$name];
        if ($value instanceof \DateTimeInterface) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i:sP', $value)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date ?: null;
    }

    /**
     * @param int[] $iblockIds
     * @param string[]|null $props
     * @param PropertySpec[] $propSpecs
     * @return array<int, array<string, IblockPropertyDefinitionRecord>>
     */
    private function loadDefinitionsByIblock(array $iblockIds, ?array $props, array &$propSpecs): array
    {
        if ($props === null) {
            return [];
        }

        $allPropStrings = [];
        /** @var array<int, array<int, IblockPropertyDefinitionRecord>> $expandedDefinitionsById */
        $expandedDefinitionsById = [];

        foreach ($iblockIds as $iblockId) {
            $iblockProps = $props;

            if ($this->propertySpecParser->needsExpansion($props)) {
                $definitionsById = $this->propertyDefinitionRepository->findByCodes($iblockId, []);
                $expandedDefinitionsById[$iblockId] = $definitionsById;
                $allCodes = array_values(array_map(
                    static fn(IblockPropertyDefinitionRecord $d): string => $d->code,
                    $definitionsById,
                ));
                $iblockProps = $this->propertySpecParser->expandWildcard($props, $allCodes);
            }

            $allPropStrings = [...$allPropStrings, ...$iblockProps];
        }

        $propSpecs = $this->propertySpecParser->parse(array_values(array_unique($allPropStrings)));
        $codes = array_map(static fn($s) => $s->code, $propSpecs);

        $definitionsByIblock = [];
        foreach ($iblockIds as $iblockId) {
            if (isset($expandedDefinitionsById[$iblockId])) {
                $definitionsById = $this->propertyDefinitionRepository->filterByCodes($expandedDefinitionsById[$iblockId], $codes);
            } else {
                $definitionsById = $this->propertyDefinitionRepository->findByCodes($iblockId, $codes);
            }

            $definitionsByCode = $this->propertyDefinitionRepository->indexByCode($definitionsById);
            $definitionsByIblock[$iblockId] = $definitionsByCode;

            foreach ((new NestedDefinitionLoader())->load($propSpecs, $definitionsByCode) as $linkIblockId => $definitions) {
                $definitionsByIblock[$linkIblockId] = array_merge(
                    $definitionsByIblock[$linkIblockId] ?? [],
                    $definitions,
                );
            }
        }

        return $definitionsByIblock;
    }

    /**
     * @param array<string, mixed> $rawFields
     * @param FieldSpec[] $fieldSpecs
     * @return array<string, mixed>
     */
    private function resolveFieldsFromMap(
        array $rawFields,
        array $fieldSpecs,
        AutoResolveMaps $maps,
    ): array {
        $resolved = [];

        foreach ($fieldSpecs as $spec) {
            if (!array_key_exists($spec->name, $rawFields)) {
                continue;
            }

            $raw = $rawFields[$spec->name];
            $resolved[$spec->name] = $spec->autoResolve && (int)$raw > 0
                ? ($maps->files[(int)$raw] ?? null)
                : $raw;
        }

        return $resolved;
    }

    /**
     * @return array<int, mixed|list<mixed>>
     */
    private function resolveProperty(
        IblockElementRecordCollection $elements,
        PropertySpec $spec,
        IblockPropertyDefinitionRecord $definition,
        AutoResolveMaps $maps,
        ?array $fields,
    ): array {
        $context = $this->aggregator->context($elements, $spec->code, $definition);

        if ($context->byElement === []) {
            return [];
        }

        if ($spec->autoResolve) {
            $resolver = $this->resolverRegistry->get($definition);
            if ($resolver !== null) {
                $map = $resolver($context);

                return $this->distributor->distribute($context->byElement, $map);
            }
        }

        if ($spec->autoResolve && $definition->type === PropertyTypeEnum::ELEMENT) {
            return $this->resolveLinkedElementProperty($context, $spec, $definition, $fields);
        }

        if ($spec->autoResolve) {
            $map = $this->mapFromPreload($definition, $context->uniqueValues, $maps);

            return $this->distributor->distribute($context->byElement, $map);
        }

        return $context->byElement;
    }

    /**
     * @param list<mixed> $uniqueValues
     * @return array<int|string, mixed>
     */
    private function mapFromPreload(
        IblockPropertyDefinitionRecord $definition,
        array $uniqueValues,
        AutoResolveMaps $maps,
    ): array {
        $source = match ($definition->type) {
            PropertyTypeEnum::FILE    => $maps->files,
            PropertyTypeEnum::LIST    => $maps->enums,
            PropertyTypeEnum::ELEMENT => $maps->elements,
            default => null,
        };

        if ($source === null) {
            if ($definition->type === PropertyTypeEnum::NUMBER) {
                return (new BuiltIn\NumberValueParser())->map($uniqueValues);
            }

            return array_combine(
                array_map('strval', $uniqueValues),
                $uniqueValues,
            ) ?: [];
        }

        $map = [];
        foreach ($uniqueValues as $value) {
            $map[$value] = $source[(int)$value] ?? null;
        }

        return $map;
    }

    /**
     * @return array<int, mixed|list<mixed>>
     */
    private function resolveLinkedElementProperty(
        PropertyResolveContext $context,
        PropertySpec $spec,
        IblockPropertyDefinitionRecord $definition,
        ?array $fields,
    ): array {
        $linkIblockId = $definition->linkIblockId;
        if ($linkIblockId === null || $linkIblockId <= 0) {
            return [];
        }

        $nestedResolver = new NestedPropertyResolver(
            $this->resolverRegistry,
            $this->linkedElementCache,
        );
        $nestedElements = $nestedResolver->resolve(
            linkIblockId: $linkIblockId,
            elementIds: $context->uniqueValues,
            nestedPaths: $spec->nested,
            fields: $fields,
        );

        $map = [];
        foreach ($context->uniqueValues as $value) {
            $id = (int)$value;
            $map[$value] = $id > 0 ? ($nestedElements[$id] ?? null) : null;
        }

        return $this->distributor->distribute($context->byElement, $map);
    }
}
