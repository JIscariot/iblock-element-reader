<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve;

use IblockElementReader\Model\IblockElement;

/**
 * Flyweight-кэш linked-элементов (E) на один get().
 * Ключ: linkIblockId + elementId + withFields + nested paths.
 */
final class LinkedElementCache
{
    /** @var array<string, IblockElement> */
    private array $elements = [];

    public function get(int $linkIblockId, int $elementId, string $loadSignature): ?IblockElement
    {
        return $this->elements[$this->key($linkIblockId, $elementId, $loadSignature)] ?? null;
    }

    public function set(int $linkIblockId, int $elementId, string $loadSignature, IblockElement $element): void
    {
        $this->elements[$this->key($linkIblockId, $elementId, $loadSignature)] = $element;
    }

    public function has(int $linkIblockId, int $elementId, string $loadSignature): bool
    {
        return isset($this->elements[$this->key($linkIblockId, $elementId, $loadSignature)]);
    }

    /**
     * @param string[]|null    $fields
     * @param PropertyPath[]   $nestedPaths
     */
    public static function loadSignature(?array $fields, array $nestedPaths = []): string
    {
        $parts = [self::fieldsSignature($fields)];

        if ($nestedPaths !== []) {
            $parts[] = self::nestedPathsSignature($nestedPaths);
        }

        return implode("\1", $parts);
    }

    /**
     * @param string[]|null $fields  as in withFields()
     */
    public static function fieldsSignature(?array $fields): string
    {
        if ($fields === null || $fields === []) {
            return '__id__';
        }

        $normalized = $fields;
        sort($normalized);

        return implode(',', $normalized);
    }

    /**
     * @param PropertyPath[] $nestedPaths
     */
    public static function nestedPathsSignature(array $nestedPaths): string
    {
        $propStrings = array_map(
            static fn(PropertyPath $path): string => PropertyPathParser::toPropString($path),
            $nestedPaths,
        );
        sort($propStrings);

        return implode("\0", $propStrings);
    }

    /**
     * @param PropertyPath[] $nestedPaths
     * @deprecated use {@see loadSignature()}
     */
    public static function nestedSignature(array $nestedPaths): string
    {
        return self::nestedPathsSignature($nestedPaths);
    }

    private function key(int $linkIblockId, int $elementId, string $loadSignature): string
    {
        return $linkIblockId . ':' . $elementId . ':' . $loadSignature;
    }
}
