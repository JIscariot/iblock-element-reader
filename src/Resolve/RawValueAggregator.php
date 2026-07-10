<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve;

use IblockElementReader\Model\Record\IblockElementRecord;
use IblockElementReader\Model\Record\IblockElementRecordCollection;
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;

final class RawValueAggregator
{
    /**
     * @return array{
     *     unique: list<mixed>,
     *     uniqueDescriptions: list<mixed>,
     *     byElement: array<int, mixed|list<mixed>>,
     * }
     */
    public function aggregate(IblockElementRecordCollection $elements, string $code, IblockPropertyDefinitionRecord $definition): array
    {
        $byElement = [];
        $unique = [];
        $uniqueDescriptions = [];

        foreach ($elements as $element) {
            if ($definition->multiple) {
                $values = $element->properties->rawValues($code);
                $byElement[$element->id] = $values;
                if ($values === []) {
                    continue;
                }

                foreach ($values as $value) {
                    $this->trackUniqueValue($unique, $value);
                }
                $this->trackUniqueDescriptions($element, $code, $uniqueDescriptions);
                continue;
            }

            $value = $element->properties->has($code)
                ? $element->properties->rawValue($code)
                : null;

            $byElement[$element->id] = $value;
            $this->trackUniqueValue($unique, $value);
            $this->trackUniqueDescriptions($element, $code, $uniqueDescriptions);
        }

        return [
            'unique'             => array_values($unique),
            'uniqueDescriptions' => array_values($uniqueDescriptions),
            'byElement'          => $byElement,
        ];
    }

    public function context(
        IblockElementRecordCollection $elements,
        string $code,
        IblockPropertyDefinitionRecord $definition,
    ): PropertyResolveContext {
        $aggregated = $this->aggregate($elements, $code, $definition);

        return new PropertyResolveContext(
            definition: $definition,
            uniqueValues: $aggregated['unique'],
            uniqueDescriptions: $aggregated['uniqueDescriptions'],
            byElement: $aggregated['byElement'],
        );
    }

    /**
     * @param array<string, mixed> $unique
     */
    private function trackUniqueValue(array &$unique, mixed $value): void
    {
        if ($value === null) {
            $unique[''] = null;

            return;
        }

        $unique[(string)$value] = $value;
    }

    /**
     * @param array<string, mixed> $uniqueDescriptions
     */
    private function trackUniqueDescriptions(
        IblockElementRecord $element,
        string $code,
        array &$uniqueDescriptions,
    ): void {
        foreach ($element->properties->descriptions($code) as $description) {
            if ($description === null || $description === '') {
                continue;
            }

            $uniqueDescriptions[(string)$description] = $description;
        }
    }
}
