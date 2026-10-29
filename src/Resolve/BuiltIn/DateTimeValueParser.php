<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve\BuiltIn;

/**
 * Парсит значения Bitrix Date / DateTime → DateTimeImmutable.
 *
 * Форматы строк:
 *  - БД:   Y-m-d H:i:s, Y-m-d H:i, Y-m-d (и Y-m-d\TH:i:s без пояса);
 *  - сайт: d.m.Y H:i:s, d.m.Y H:i, d.m.Y.
 * Объекты: Bitrix\Main\Type\Date, Bitrix\Main\Type\DateTime, \DateTimeInterface.
 *
 * Строки разбираются в часовом поясе по умолчанию (в Bitrix — пояс сайта), без сдвига.
 * Объекты сохраняют своё время и пояс.
 * Пустое, некорректное (31.02.2024, 25:00) или нераспознанное значение → null, без исключений и предупреждений.
 *
 * Для разбора дат из строковых свойств: DateTimeValueParser::parseValue($value).
 */
final class DateTimeValueParser
{
    /** @var list<string> «!» — незаданные части (время, микросекунды) обнуляются */
    private const FORMATS = [
        '!Y-m-d H:i:s',
        '!Y-m-d H:i',
        '!Y-m-d',
        '!Y-m-d\\TH:i:s',
        '!d.m.Y H:i:s',
        '!d.m.Y H:i',
        '!d.m.Y',
    ];

    /**
     * @param bool $dateOnly  true — время обнуляется (00:00:00), как у USER_TYPE Date
     */
    public static function parseValue(mixed $value, bool $dateOnly = false): ?\DateTimeImmutable
    {
        $date = self::toDateTime($value);

        return $date !== null && $dateOnly ? $date->setTime(0, 0) : $date;
    }

    public function parse(mixed $value, bool $dateOnly = false): ?\DateTimeImmutable
    {
        return self::parseValue($value, $dateOnly);
    }

    /**
     * @param list<mixed> $uniqueValues
     * @return array<int|string, \DateTimeImmutable|null>
     */
    public function map(array $uniqueValues, bool $dateOnly = false): array
    {
        $map = [];

        foreach ($uniqueValues as $value) {
            if ($value !== null && !is_scalar($value)) {
                continue; // объект не может быть ключом map[rawValue]
            }

            $map[$value] = self::parseValue($value, $dateOnly);
        }

        return $map;
    }

    private static function toDateTime(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        // Bitrix\Main\Type\DateTime наследует Date
        if ($value instanceof \Bitrix\Main\Type\Date) {
            return self::fromBitrix($value);
        }

        if (!is_string($value)) {
            return null;
        }

        $string = trim($value);
        if ($string === '') {
            return null;
        }

        foreach (self::FORMATS as $format) {
            $date = self::createStrict($format, $string, null);
            if ($date !== null) {
                return $date;
            }
        }

        return null;
    }

    private static function fromBitrix(\Bitrix\Main\Type\Date $value): ?\DateTimeImmutable
    {
        $timezone = $value instanceof \Bitrix\Main\Type\DateTime ? $value->getTimeZone() : null;

        return self::createStrict('!Y-m-d H:i:s', $value->format('Y-m-d H:i:s'), $timezone ?: null);
    }

    private static function createStrict(string $format, string $value, ?\DateTimeZone $timezone): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat($format, $value, $timezone);
        if ($date === false) {
            return null;
        }

        // «31.02.2024» или «2024-01-01 25:00:00» парсятся с переполнением и предупреждением — отбрасываем
        $errors = \DateTimeImmutable::getLastErrors();
        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        // Y принимает и «24» → год 0024
        $year = (int)$date->format('Y');
        if ($year < 1000 || $year > 9999) {
            return null;
        }

        return $date;
    }
}
