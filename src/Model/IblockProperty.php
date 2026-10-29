<?php

declare(strict_types=1);

namespace IblockElementReader\Model;

use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;

final readonly class IblockProperty
{
    /**
     * @param null|IblockPropertyValue|IblockPropertyValue[] $value
     */
    public function __construct(
        public IblockPropertyDefinitionRecord $definition,
        public null|IblockPropertyValue|array $value = null,
    ) {
    }

    /**
     * Все значения списком — одинаково для одиночного и множественного свойства.
     *
     * @return list<IblockPropertyValue>
     */
    public function values(): array
    {
        if ($this->value === null) {
            return [];
        }

        if ($this->value instanceof IblockPropertyValue) {
            return [$this->value];
        }

        return array_values($this->value);
    }

    /** Первое значение или null, если значений нет. */
    public function first(): ?IblockPropertyValue
    {
        return $this->values()[0] ?? null;
    }

    /** resolved первого значения; если он null — raw. Нет значений — null. */
    public function resolved(): mixed
    {
        $first = $this->first();

        return $first === null ? null : self::resolvedOrRaw($first);
    }

    /**
     * resolved (при null — raw) всех значений, без null и пустых строк.
     *
     * @return list<mixed>
     */
    public function resolvedList(): array
    {
        $list = [];

        foreach ($this->values() as $value) {
            $item = self::resolvedOrRaw($value);
            if ($item === null || $item === '') {
                continue;
            }

            $list[] = $item;
        }

        return $list;
    }

    /**
     * Описания значений в порядке {@see values()} (пустые значения не пропускаются).
     *
     * @return list<?string>
     */
    public function descriptions(): array
    {
        return array_map(
            static fn(IblockPropertyValue $value): ?string => $value->description,
            $this->values(),
        );
    }

    private static function resolvedOrRaw(IblockPropertyValue $value): mixed
    {
        return $value->resolved ?? $value->raw;
    }
}
