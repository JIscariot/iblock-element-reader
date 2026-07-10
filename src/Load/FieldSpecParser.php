<?php

declare(strict_types=1);

namespace IblockElementReader\Load;

final class FieldSpecParser
{
    public const WILDCARD = '*';

    /** @var string[] */
    private const DEFAULT_FIELDS = [
        'ID', 'NAME', 'CODE', 'ACTIVE', 'SORT',
        'PREVIEW_PICTURE', 'DETAIL_PICTURE',
        'PREVIEW_TEXT', 'DETAIL_TEXT',
    ];

    /**
     * @param string[]|null $fields  null = только ID; ['*'] = {@see DEFAULT_FIELDS}
     * @return FieldSpec[]
     */
    public function parse(?array $fields): array
    {
        if ($fields === null || $fields === []) {
            return [new FieldSpec('ID', false)];
        }

        if ($this->usesWildcard($fields)) {
            $fields = $this->expandWildcard($fields);
        }

        return array_map(
            fn(string $raw): FieldSpec => new FieldSpec(
                name: $this->normalizeName($raw),
                autoResolve: str_ends_with($raw, '.VALUE'),
            ),
            $fields,
        );
    }

    /**
     * @param FieldSpec[] $specs
     * @return string[]
     */
    public function selectNames(array $specs): array
    {
        $names = array_map(static fn(FieldSpec $s): string => $s->name, $specs);

        if (!in_array('ID', $names, true)) {
            array_unshift($names, 'ID');
        }

        return array_values(array_unique($names));
    }

    /**
     * @param string[] $fields
     * @return string[]
     */
    private function expandWildcard(array $fields): array
    {
        $explicit = array_values(array_filter(
            $fields,
            static fn(string $field): bool => $field !== self::WILDCARD,
        ));

        return [...self::DEFAULT_FIELDS, ...$explicit];
    }

    /**
     * @param string[] $fields
     */
    private function usesWildcard(array $fields): bool
    {
        return in_array(self::WILDCARD, $fields, true);
    }

    private function normalizeName(string $raw): string
    {
        return str_ends_with($raw, '.VALUE')
            ? substr($raw, 0, -strlen('.VALUE'))
            : $raw;
    }
}
