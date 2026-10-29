<?php

declare(strict_types=1);

/**
 * Минимальные заглушки Bitrix\Main\Type\Date / DateTime для тестов без Bitrix.
 * Объявляются, только если настоящих классов нет. Подключать через require_once.
 */

namespace Bitrix\Main\Type {
    if (!class_exists(Date::class, false)) {
        class Date
        {
            protected \DateTime $value;

            public function __construct(?string $date = null, ?string $format = null)
            {
                $this->value = $date === null
                    ? new \DateTime('today')
                    : \DateTime::createFromFormat('!' . ($format ?? 'Y-m-d'), $date);
            }

            public function format(string $format): string
            {
                return $this->value->format($format);
            }

            public function getTimestamp(): int
            {
                return $this->value->getTimestamp();
            }
        }
    }

    if (!class_exists(DateTime::class, false)) {
        class DateTime extends Date
        {
            public function __construct(?string $time = null, ?string $format = null, ?\DateTimeZone $timezone = null)
            {
                $this->value = $time === null
                    ? new \DateTime('now', $timezone)
                    : \DateTime::createFromFormat($format ?? 'Y-m-d H:i:s', $time, $timezone);
            }

            public function getTimeZone(): \DateTimeZone
            {
                return $this->value->getTimezone();
            }
        }
    }
}
