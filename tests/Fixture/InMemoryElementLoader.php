<?php

declare(strict_types=1);

namespace IblockElementReader\Tests\Fixture;

use IblockElementReader\Load\ElementLoaderInterface;
use IblockElementReader\Model\Record\IblockElementRecord;
use IblockElementReader\Model\Record\IblockElementRecordCollection;
use IblockElementReader\Model\Record\IblockPropertyValueRecord;
use IblockElementReader\Model\Record\IblockPropertyValueRecordCollection;
use IblockElementReader\Resolve\PropertySpecParser;

/**
 * Загрузчик элементов без БД: отдаёт заранее заданные записи.
 * Свойства фильтруются по запрошенным кодам, порядок значений сохраняется как задан.
 */
final class InMemoryElementLoader implements ElementLoaderInterface
{
    /** @var array<int, IblockElementRecord> */
    private array $records = [];

    /** @var list<array{iblockIds: int[], ids: int[], props: string[]|null}> */
    public array $calls = [];

    public function __construct(IblockElementRecord ...$records)
    {
        foreach ($records as $record) {
            $this->records[$record->id] = $record;
        }
    }

    public function load(array $iblockIds, array $ids, ?array $fields, ?array $props): IblockElementRecordCollection
    {
        $this->calls[] = ['iblockIds' => $iblockIds, 'ids' => $ids, 'props' => $props];

        $codes = $props === null
            ? []
            : array_map(static fn($spec) => $spec->code, (new PropertySpecParser())->parse($props));

        $result = [];
        foreach ($ids as $id) {
            $record = $this->records[(int)$id] ?? null;
            if ($record === null || ($iblockIds !== [] && !in_array($record->iblockId, $iblockIds, true))) {
                continue;
            }

            $values = array_filter(
                iterator_to_array($record->properties, false),
                static fn(IblockPropertyValueRecord $value): bool => in_array($value->definition->code, $codes, true),
            );

            $result[] = new IblockElementRecord(
                id: $record->id,
                iblockId: $record->iblockId,
                fields: $record->fields,
                properties: new IblockPropertyValueRecordCollection(...$values),
            );
        }

        return new IblockElementRecordCollection(...$result);
    }
}
