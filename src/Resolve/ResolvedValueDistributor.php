<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve;

/**
 * Раскладывает map[raw => resolved] по элементам.
 */
final class ResolvedValueDistributor
{
    /**
     * @param array<int, mixed|list>           $byElement
     * @param array<int|string, mixed>         $map
     * @return array<int, mixed|list>         elementId => resolved | resolved[]
     */
    public function distribute(array $byElement, array $map): array
    {
        $result = [];

        foreach ($byElement as $elementId => $raw) {
            $result[$elementId] = is_array($raw)
                ? array_map(fn(mixed $v): mixed => $this->lookup($map, $v), $raw)
                : $this->lookup($map, $raw);
        }

        return $result;
    }

    /**
     * @param array<int|string, mixed> $map
     */
    private function lookup(array $map, mixed $raw): mixed
    {
        if (array_key_exists($raw, $map)) {
            return $map[$raw];
        }

        if ($raw === null && array_key_exists('', $map)) {
            return $map[''];
        }

        if (is_int($raw) && array_key_exists((string)$raw, $map)) {
            return $map[(string)$raw];
        }

        return null;
    }
}
