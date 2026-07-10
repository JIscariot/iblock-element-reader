<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve\BuiltIn;

/** Парсит сырые значения Bitrix Date / DateTime → DateTimeImmutable. */
final class DateTimeValueParser
{
    /** @var string[] */
    private const FORMATS = [
        '!Y-m-d H:i:s',
        '!Y-m-d H:i',
        '!Y-m-d',
        '!d.m.Y H:i:s',
        '!d.m.Y H:i',
        '!d.m.Y',
    ];

    public function parse(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = trim((string)$value);
        if ($string === '') {
            return null;
        }

        foreach (self::FORMATS as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $string);
            if ($date !== false) {
                return $date;
            }
        }

        try {
            return new \DateTimeImmutable($string);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param list<mixed> $uniqueValues
     * @return array<int|string, \DateTimeImmutable|null>
     */
    public function map(array $uniqueValues): array
    {
        $map = [];

        foreach ($uniqueValues as $value) {
            $map[$value] = $this->parse($value);
        }

        return $map;
    }
}
