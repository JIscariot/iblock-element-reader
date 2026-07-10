<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve\BuiltIn;

/** Парсит сырые значения Bitrix N → int|float. */
final class NumberValueParser
{
    public function cast(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return $value;
        }

        $string = (string)$value;

        return str_contains($string, '.') ? (float)$value : (int)$value;
    }

    /**
     * @param list<mixed> $uniqueValues
     * @return array<int|string, mixed>
     */
    public function map(array $uniqueValues): array
    {
        $map = [];

        foreach ($uniqueValues as $value) {
            $map[$value] = $this->cast($value);
        }

        return $map;
    }
}
