<?php

declare(strict_types=1);

namespace IblockElementReader\Normalizer;

final class IdsNormalizer
{
    /**
     * @param list<mixed> $values
     * @return int[]
     */
    public static function unique(array $values): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn(mixed $value): int => (int)$value, $values),
            static fn(int $id): bool => $id > 0,
        )));
    }

    /**
     * @return int[]
     */
    public static function normalize(int|array $values): array
    {
        return self::unique(is_array($values) ? $values : [$values]);
    }
}
