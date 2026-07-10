<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve;

final class PropertySpecParser
{
    public const WILDCARD = '*';
    public const WILDCARD_VALUE = '*.VALUE';

    /**
     * @param string[] $props
     * @return PropertySpec[]
     */
    public function parse(array $props): array
    {
        /** @var array<string, PropertySpec> $merged */
        $merged = [];

        foreach ($props as $prop) {
            $path = PropertyPathParser::parse((string)$prop);
            $root = $path->code;

            if (!isset($merged[$root])) {
                $merged[$root] = new PropertySpec(
                    code: $root,
                    autoResolve: $path->autoResolve,
                    nested: $path->nested,
                );
                continue;
            }

            $existing = $merged[$root];
            $merged[$root] = new PropertySpec(
                code: $root,
                autoResolve: $existing->autoResolve || $path->autoResolve,
                nested: $this->mergeNested($existing->nested, $path->nested),
            );
        }

        return array_values($merged);
    }

    /**
     * @param string[]|null $props
     */
    public function needsExpansion(?array $props): bool
    {
        if ($props === null) {
            return false;
        }

        if ($props === []) {
            return true;
        }

        foreach ($props as $prop) {
            if ($prop === self::WILDCARD || $prop === self::WILDCARD_VALUE) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $props
     * @param string[] $allCodes
     * @return string[]
     */
    public function expandWildcard(array $props, array $allCodes): array
    {
        if ($allCodes === [] || !$this->needsExpansion($props)) {
            return $props;
        }

        $autoResolve = $this->usesValueWildcard($props);
        $explicitCodes = array_flip($this->explicitCodes($props));

        $expanded = [];
        foreach ($allCodes as $code) {
            if (isset($explicitCodes[$code])) {
                continue;
            }

            $expanded[] = $autoResolve ? $code . '.VALUE' : $code;
        }

        return [...$expanded, ...$this->extractExplicit($props)];
    }

    /**
     * @param PropertyPath[] $left
     * @param PropertyPath[] $right
     * @return PropertyPath[]
     */
    private function mergeNested(array $left, array $right): array
    {
        /** @var array<string, PropertyPath> $byCode */
        $byCode = [];

        foreach ([...$left, ...$right] as $path) {
            $code = $path->code;
            if (!isset($byCode[$code])) {
                $byCode[$code] = $path;
                continue;
            }

            $existing = $byCode[$code];
            $byCode[$code] = new PropertyPath(
                code: $code,
                autoResolve: $existing->autoResolve || $path->autoResolve,
                nested: $this->mergeNested($existing->nested, $path->nested),
            );
        }

        return array_values($byCode);
    }

    /**
     * @param string[] $props
     */
    private function usesValueWildcard(array $props): bool
    {
        if ($props === []) {
            return true;
        }

        return in_array(self::WILDCARD_VALUE, $props, true);
    }

    /**
     * @param string[] $props
     * @return string[]
     */
    private function extractExplicit(array $props): array
    {
        return array_values(array_filter(
            $props,
            static fn(string $prop): bool => $prop !== self::WILDCARD && $prop !== self::WILDCARD_VALUE,
        ));
    }

    /**
     * @param string[] $props
     * @return string[]
     */
    private function explicitCodes(array $props): array
    {
        return array_map(
            static fn(string $prop): string => PropertyPathParser::rootCode($prop),
            $this->extractExplicit($props),
        );
    }
}
