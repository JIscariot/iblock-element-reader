<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve\BuiltIn;

/**
 * Парсит сырые значения Bitrix N → int|float|null.
 *
 * - пробелы по краям (включая неразрывные) обрезаются;
 * - пробелы-разделители тысяч убираются: "1 500" → 1500;
 * - десятичная запятая заменяется точкой: "1,6" → 1.6;
 * - целое → int (в том числе "15000.0000" из числовых колонок), дробное → float;
 * - пустое или нечисловое → null, без исключений и предупреждений.
 */
final class NumberValueParser
{
    /** Пробелы, включая неразрывный (U+00A0), узкий неразрывный (U+202F) и цифровой (U+2007). */
    private const SPACE = '[\s\x{00A0}\x{2007}\x{202F}]';

    public function cast(mixed $value): int|float|null
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return is_finite($value) ? $value : null;
        }

        if (!is_string($value)) {
            return null;
        }

        $number = $this->normalize($value);
        if ($number === null) {
            return null;
        }

        if (preg_match('/^([+-]?)(\d+)(?:\.0*)?$/', $number, $m) === 1) {
            $digits = ltrim($m[2], '0');

            // до 18 значащих цифр гарантированно помещается в int64
            if (strlen($digits) <= 18) {
                return (int)($m[1] . ($digits === '' ? '0' : $digits));
            }
        }

        $float = (float)$number;

        return is_finite($float) ? $float : null;
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

    /**
     * Приводит строку к виду, понятному is_numeric(), или возвращает null.
     */
    private function normalize(string $value): ?string
    {
        $space = self::SPACE;

        $trimmed = preg_replace("/^{$space}+|{$space}+$/u", '', $value);
        if ($trimmed === null || $trimmed === '') {
            return null;
        }

        // "1 500", "1 500 000,25": группы по три цифры через пробел.
        if (preg_match("/^[+-]?\\d{1,3}(?:{$space}\\d{3})+(?:[.,]\\d+)?$/u", $trimmed) === 1) {
            $trimmed = (string)preg_replace("/{$space}/u", '', $trimmed);
        }

        // "1,6" → "1.6": одна запятая и нет точки.
        if (substr_count($trimmed, ',') === 1 && !str_contains($trimmed, '.')) {
            $trimmed = str_replace(',', '.', $trimmed);
        }

        if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/', $trimmed) !== 1) {
            return null;
        }

        return $trimmed;
    }
}
