# jiscariot/iblock-element-reader

Bitrix iblock element reader: загрузка полей и свойств из БД и batch-резолв в типизированные значения.

## Установка

```bash
composer require jiscariot/iblock-element-reader
```

**Требования:** PHP 8.1+, Bitrix CMS с модулем `iblock` (классы `Bitrix\Iblock\*`, `Bitrix\Main\*`).

```
IblockElementReader → IblockElementFetcher → ElementLoader → ResolvePipeline → IblockElementCollection
                                                      ↓
                                               Repository/*
```

Класс immutable: каждый `filter` / `withFields` / `withProps` / `withResolvers` возвращает клон.

Reader **не ходит в `b_iblock`**: scope — через `filter(iblock: ...)` или `IBLOCK_ID` из загруженных элементов; storage mode — `VERSION` из `b_iblock_property`.

---

## Быстрый старт

```php
use IblockElementReader\IblockElementReader;

$elements = (new IblockElementReader())
    ->filter(id: [10, 20, 30], iblock: [5])
    ->withFields(['ID', 'NAME', 'PREVIEW_PICTURE.VALUE'])
    ->withProps(['MAKE_ID.VALUE', 'PRICE.VALUE', 'VIN'])
    ->get();

foreach ($elements->elements as $element) {
    $element->id;                              // int
    $element->name;                            // string|null
    $element->properties['MAKE_ID']->value->raw;       // сырое из БД
    $element->properties['MAKE_ID']->value->resolved;  // после .VALUE или null (у S/N — всегда)
}

$elements->elements[0] ?? null; // первый элемент
$elements->elements;            // IblockElement[]

// один элемент
$element = (new IblockElementReader())
    ->filter(id: 10, iblock: 5)
    ->withFields(['*'])
    ->first();
```

### filter — scope элементов и инфоблоков

```php
// id + iblock
->filter(id: [215674], iblock: [33])

// только id — iblock из IBLOCK_ID в row
->filter(id: [215674, 218572])

// все элементы iblock
->filter(iblock: [33])

// несколько iblock
->filter(id: [100, 200], iblock: [5, 10])

// ошибка: scope не определён
(new IblockElementReader())->get();
```

| `iblock` | `id` | Поведение |
|---|---|---|
| `[5]` | `[10, 20]` | элементы iblock 5 с указанными id |
| `[5, 10]` | `[1, 2, 3]` | элементы из iblock 5 и 10 по id |
| `[5, 10]` | не задан / `null` | все элементы iblock 5 и 10 |
| не задан | `[10, 20]` | загрузка по id, iblock из row (один или несколько) |
| любой | `[]` | пустой результат, запросов к БД нет |
| не задан | не задан / `null` | exception |

#### `id: null` и `id: []` — разные вещи

- `id: null` или аргумент не передан — **фильтра по ID нет**: загружаются все элементы инфоблока из `filter(iblock: ...)`.
- `id: []` — **заведомо пустая выборка**: `get()` возвращает пустую коллекцию, `first()` — `null`, к БД не уходит ни одного запроса. Остальные условия фильтра на это не влияют.

Поэтому список ID из поиска или выборки можно передавать как есть, без проверки `if ($ids === []) return [];`:

```php
$ids = $search->findIds($query); // может оказаться []

$elements = (new IblockElementReader())
    ->filter(id: $ids, iblock: [5])
    ->withProps(['PRICE.VALUE'])
    ->get();                     // при $ids === [] — пустая коллекция, без запросов
```

ID нормализуются: значения `<= 0` и дубликаты отбрасываются. Если валидных ID не осталось (например, `[0]`), это тоже пустая выборка. ID, которых нет в базе, запрашиваются обычным образом и дают пустой результат.

Исключение для `id: []` не бросается намеренно: пустой список ID — нормальная ситуация, а не ошибка вызывающего кода.

Повторный `filter()` меняет только переданные аргументы:

```php
->filter(id: [10, 20])
->filter(iblock: [5])   // id остаётся [10, 20]
```

### Несколько инфоблоков

```php
$elements = (new IblockElementReader())
    ->filter(id: [100, 200, 300], iblock: [5, 10])
    ->withProps(['NAME.VALUE', 'ADDRESS.VALUE'])  // код должен существовать в каждом iblock
    ->get();

foreach ($elements->elements as $element) {
    $element->iblockId;              // 5 или 10
}
```

---

## Главное правило: суффикс `.VALUE`

| | Без `.VALUE` | С `.VALUE` |
|---|---|---|
| **withFields** | сырое значение (id файла, строка) | `File` для `PREVIEW_PICTURE` / `DETAIL_PICTURE` |
| **withProps** | сырое значение из `prop_s` | авто-резолв по типу свойства |

Исключение — простые `S` и `N` без `USER_TYPE`: у них `resolved` заполнен всегда, с `.VALUE` и без (см. [Простые типы S и N](#простые-типы-s-и-n)).

Примеры:

```php
// id файла
->withFields(['PREVIEW_PICTURE'])

// объект File { id, subdir, filename, src, size, width, height }
->withFields(['PREVIEW_PICTURE.VALUE'])

// id связанного элемента
->withProps(['STORE'])

// IblockElement связанного элемента (поля из withFields родительского запроса)
->withProps(['STORE.VALUE'])

// id enum-значения
->withProps(['STATUS'])

// Enumerate { id, value, xmlId }
->withProps(['STATUS.VALUE'])
```

---

## withFields

```php
// withoutFields — в SELECT только ID (withFields не вызывался)
(new IblockElementReader())->filter(id: [10])->withProps([...])->get();

// все поля по умолчанию
->withFields(['*'])

// явный список
->withFields(['ID', 'NAME', 'PREVIEW_PICTURE.VALUE'])
```

| Вызов | SELECT |
|---|---|
| без `withFields` | `ID` (+ `IBLOCK_ID` служебно) |
| `withFields(['*'])` | ID, NAME, CODE, ACTIVE, SORT, PREVIEW_PICTURE, DETAIL_PICTURE, PREVIEW_TEXT, DETAIL_TEXT |
| `withFields([])` | только `ID` |
| явный список | указанные поля + `ID` |

`ID` добавляется в SELECT автоматически, если не указан явно.

---

## withProps

```php
// свойства не загружаются ($props = null)
(new IblockElementReader())->filter(iblock: [5])->get();

// все свойства с авто-резолвом (.VALUE для каждого кода)
->withProps([])
->withProps(['*.VALUE'])

// все свойства как есть (сырые строки / id)
->withProps(['*'])

// явный список
->withProps(['MAKE_ID.VALUE', 'PRICE', 'PHOTOS.VALUE'])
```

| Вызов | Что происходит |
|---|---|
| без `withProps` | свойства не читаются из БД |
| `withProps([])` | все коды, каждый с `.VALUE` |
| `withProps(['*'])` | все коды, без резолва |
| `withProps(['*.VALUE'])` | все коды с резолвом + можно добавить явные пути |

---

## Авто-резолв свойств (без кастомных резолверов)

При `.VALUE` pipeline выбирает стратегию в таком порядке:

1. **USER_TYPE resolver** — встроенный (`Date`, `DateTime`) или из `withResolvers`
2. **Nested E** — если путь вида `STORE.VALUE.ADDRESS.VALUE`
3. **Тип свойства (PROPERTY_TYPE)**:
   - `E` → связанный `IblockElement`
   - `F` → `File`
   - `L` → `Enumerate`
   - `N` → `int` / `float`
   - `S`, `G` → как есть

### Простые типы S и N

Для свойств `S` и `N` **без `USER_TYPE`** `resolved` заполняется всегда — независимо от `.VALUE`, `*` / `*.VALUE` и того, на каком уровне вложенности загружено свойство (в том числе у связанных элементов `E`). Писать `resolved ?? raw` не нужно.

| Тип | `resolved` |
|---|---|
| `S` | `raw` как есть |
| `N` | `int` / `float` / `null` через `NumberValueParser` |

`NumberValueParser`:

- обрезает пробелы по краям, включая неразрывные;
- убирает пробелы-разделители тысяч: `"1 500"` → `1500`;
- заменяет десятичную запятую точкой: `"1,6"` → `1.6`;
- целое → `int` (в том числе `"15000.0000"` из числовых колонок), дробное → `float`;
- пустое или нечисловое значение → `null`, без исключений и предупреждений.

| `raw` | `resolved` |
|---|---|
| `"1,6"` | `1.6` |
| `"1 500"` | `1500` |
| `" 42 "` | `42` |
| `"0"` | `0` |
| `"abc"` | `null` |
| `""` | `null` |

Свойства с `USER_TYPE` (встроенные `Date` / `DateTime`, кастомные из `withResolvers`, любые другие) и свойства, которые забрал `SupportsPropertyResolver`, это правило не затрагивает: для них `resolved` по-прежнему заполняется только с `.VALUE`.

Встроенные USER_TYPE:

| USER_TYPE | Результат |
|---|---|
| `Date` | `DateTimeImmutable` (дата, 00:00:00) |
| `DateTime` | `DateTimeImmutable` |

---

## withResolvers — кастомные резолверы

Для свойств с нестандартным `b_iblock_property.USER_TYPE` (например `DependentSelect`, `booleanValue`) передайте объекты, реализующие интерфейс `UserTypePropertyResolver`. Если нужно выбирать свойства не только по `USER_TYPE` (по коду, типу, `LINK_IBLOCK_ID`) или резолвить свойства без `USER_TYPE` — реализуйте `SupportsPropertyResolver` (см. [ниже](#supportspropertyresolver--выбор-свойств-по-определению)).

```php
use IblockElementReader\Resolve\Contract\UserTypePropertyResolver;
use IblockElementReader\Resolve\PropertyResolveContext;

final class BooleanValuePropertyResolver implements UserTypePropertyResolver
{
    public function userType(): string
    {
        return 'booleanValue';
    }

    public function resolve(PropertyResolveContext $context): array
    {
        return [
            '0'    => false,
            '1'    => true,
            ''     => false,
            'null' => false,
        ];
    }
}
```

Подключение:

```php
$elements = (new IblockElementReader())
    ->filter(id: [...], iblock: [5])
    ->withProps(['IS_ACTIVE.VALUE', 'MAKE_ID.VALUE'])
    ->withResolvers([
        new BooleanValuePropertyResolver(),
        new DependentSelectPropertyResolver($makeRepo, $modelRepo),
    ])
    ->get();
```

### Контракт резолвера

**`userType(): string`** — точное значение `b_iblock_property.USER_TYPE`. Регистр и строка должны совпадать с БД.

**`resolve(PropertyResolveContext $context): array`** — один batch-вызов на тип за весь `get()`.

`PropertyResolveContext`:

| Поле | Описание |
|---|---|
| `$definition` | метаданные свойства (`code`, `type`, `userType`, `linkIblockId`, …) |
| `$uniqueValues` | уникальные сырые значения по всем элементам запроса |
| `$uniqueDescriptions` | уникальные description (для multiple) |
| `$byElement` | `elementId => value` или `elementId => list<value>` |

Возвращаемый map: **`rawValue => resolved`**. Ключи должны совпадать с тем, что лежит в `VALUE` свойства (строка `"123"`, не int `123`, если так хранит Bitrix).

Пустые single-свойства попадают в batch как `null` (в `$uniqueValues` и `$byElement`). В PHP ключи `null` и `''` в map — одно и то же; достаточно `'' => false`.

Резолвер срабатывает **только** если свойство запрошено **с `.VALUE`**.

Кастомный резолвер **переопределяет** встроенный для того же USER_TYPE (например свой `Date` вместо стандартного парсера).

Два резолвера `UserTypePropertyResolver` с одинаковым `userType()` в одном `withResolvers()` — ошибка: `get()` бросит `LogicException` с именами обоих классов (раньше второй молча заменял первый). Чтобы обрабатывать свойства одного `USER_TYPE` разными резолверами, используйте `SupportsPropertyResolver`.

### SupportsPropertyResolver — выбор свойств по определению

`SupportsPropertyResolver` расширяет `UserTypePropertyResolver` методом `supports()`. Резолвер сам решает, какие свойства обрабатывает, по любым полям `IblockPropertyDefinitionRecord`: `code`, `type`, `userType`, `linkIblockId`, `multiple`, `withDescription`.

Например, свойства `MAKE_ID` и `MODEL_ID` с одним `USER_TYPE` `DependentSelect` ссылаются на разные справочники — каждому свой резолвер:

```php
use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;
use IblockElementReader\Resolve\Contract\SupportsPropertyResolver;
use IblockElementReader\Resolve\PropertyResolveContext;

final class MakePropertyResolver implements SupportsPropertyResolver
{
    public function __construct(private readonly MakeRepository $makes)
    {
    }

    public function supports(IblockPropertyDefinitionRecord $definition): bool
    {
        return $definition->code === 'MAKE_ID';
    }

    public function userType(): string
    {
        return 'DependentSelect'; // только подсказка
    }

    public function resolve(PropertyResolveContext $context): array
    {
        return $this->makes->namesByIds($context->uniqueValues); // ['10' => 'Volkswagen', ...]
    }
}

$elements = (new IblockElementReader())
    ->filter(iblock: [5])
    ->withProps(['MAKE_ID.VALUE', 'MODEL_ID.VALUE'])
    ->withResolvers([
        new MakePropertyResolver($makeRepo),
        new ModelPropertyResolver($modelRepo), // supports(): code === 'MODEL_ID'
    ])
    ->get();
```

Свойство без `USER_TYPE` тоже можно забрать своим резолвером — например, привязку `E` к справочнику, который не нужно загружать как `IblockElement`:

```php
public function supports(IblockPropertyDefinitionRecord $definition): bool
{
    return $definition->type === PropertyTypeEnum::ELEMENT && $definition->linkIblockId === 12;
}

public function userType(): string
{
    return ''; // свойство без USER_TYPE
}
```

Порядок выбора резолвера для свойства:

1. первый `SupportsPropertyResolver` в порядке регистрации, у которого `supports()` вернул `true`;
2. `UserTypePropertyResolver` по `userType()`;
3. встроенные (`Date`, `DateTime`);
4. ничего не подошло — авто-резолв по типу свойства (`E`, `F`, `L`, `N`, …).

- `supports()` вызывается один раз на свойство за `get()`, а не на каждое значение.
- `userType()` у `SupportsPropertyResolver` — только подсказка; пустая строка допустима. Проверка на дубликаты `userType()` к таким резолверам не применяется.
- Резолвер, как и раньше, срабатывает только для свойств с `.VALUE`. Если он забрал простое `S` / `N` без `USER_TYPE`, правило «`resolved` всегда» для этого свойства не действует: без `.VALUE` `resolved === null`.
- Если резолвер забрал свойство `E`, связанный элемент не загружается, и вложенные пути (`STORE.VALUE.ADDRESS.VALUE`) для этого свойства не применяются.

---

## Вложенные свойства (E → другой инфоблок)

Путь через точку после `.VALUE` родительского свойства типа `E`:

```php
->withProps([
    'STORE.VALUE.ADDRESS.VALUE',
    'STORE.VALUE.PHONES.VALUE',
    'STORE.VALUE.NAME',           // поле связанного элемента без резолва
])
```

- `STORE.VALUE` — связанный элемент как `IblockElement` (поля из `withFields` родительского запроса)
- `STORE.VALUE.ADDRESS.VALUE` — свойство `ADDRESS` внутри связанного инфоблока, с резолвом
- Связанный элемент (E) наследует **`withFields`** верхнего запроса (`['*']`, `['NAME']`, …); nested props добавляются отдельно (`LABEL.VALUE.ICON`)
- Максимальная глубина пути — 8 сегментов

Повторные ссылки на один и тот же элемент кешируются (`LinkedElementCache`).

---

## Результат

`get()` возвращает **`IblockElementCollection`** — типизированную domain-модель. Pipeline резолвит значения и сразу собирает `IblockElement`.

Scope проверяется в **`IblockElementReader`**: пустой filter — exception; несовпадение `iblock` у загруженных элементов — exception после fetch.

### IblockElement

```php
$element->id;
$element->iblockId;
$element->name;
$element->code;
$element->sort;
$element->previewPicture;   // null|int|File — int без .VALUE, File с PREVIEW_PICTURE.VALUE
$element->detailPicture;    // null|int|File
$element->isActive;
$element->createdAt;       // ?DateTimeInterface
$element->properties;       // array<string, IblockProperty>  ключ = CODE свойства
                            // все запрошенные свойства, в т.ч. с пустым value => null
```

### IblockProperty / IblockPropertyValue

```php
$property->definition;     // IblockPropertyDefinitionRecord
$property->value;            // IblockPropertyValue | IblockPropertyValue[] | null

$value->raw;                 // сырое значение из БД
$value->resolved;            // после .VALUE или null, если .VALUE не запрашивали; у S/N без USER_TYPE — всегда
$value->description;
```

Для свойства типа E с `.VALUE` в `resolved` — вложенный `IblockElement`.

### IblockElementCollection

```php
$elements->count();
$elements->elements; // IblockElement[]
```

Используйте `first()` на reader или `$collection->elements[0]`.

---

## Структура модуля

**`return null` из резолвера** — нужен массив. Пустой результат: `return []`.

**Неверные ключи map** — ключ должен быть raw value из свойства. Осторожно с `array_column($rows, 'name', 'id')`: в VALUE может лежать `ID` из другой таблицы, не `id` строки.

**Забыли `.VALUE`** — резолвер не вызовется, вернётся сырое значение.

**`withProps(['*.VALUE'])` на большом инфоблоке** — загружаются и резолвятся все свойства. Для API лучше явный список кодов.

**USER_TYPE не совпадает** — проверьте `b_iblock_property.USER_TYPE` в БД (`DependentSelect`, не `dependentSelect`).

**Multiple-свойство** — `$byElement[$id]` может быть массивом значений; map всё равно строится по `uniqueValues`.

---

## Полный пример

```php
use IblockElementReader\IblockElementReader;

final class BikeCatalogReader
{
    public function __construct(
        private int $bikeIblockId,
        private DependentSelectPropertyResolver $dependentSelect,
        private BooleanValuePropertyResolver $booleanValue,
    ) {
    }

    public function loadPromoted(array $ids): array
    {
        $elements = (new IblockElementReader())
            ->filter(id: $ids, iblock: $this->bikeIblockId)
            ->withFields(['ID', 'NAME', 'CODE', 'PREVIEW_PICTURE.VALUE'])
            ->withProps([
                'MAKE_ID.VALUE',
                'MODEL_ID.VALUE',
                'PRICE.VALUE',
                'IS_NEW.VALUE',
                'STORE.VALUE.ADDRESS.VALUE',
            ])
            ->withResolvers([
                $this->dependentSelect,
                $this->booleanValue,
            ])
            ->get();

        return $elements->elements;
    }
}
```

---

## Структура пакета

```
src/
├── IblockElementReader.php      fluent API (filter, withFields, withProps), scope validation
├── IblockElementFetcher.php     оркестрация load → resolve
├── Model/                       domain-модель
│   ├── IblockElement.php
│   ├── IblockElementCollection.php
│   ├── IblockProperty.php
│   ├── IblockPropertyValue.php
│   ├── Enumerate.php            resolved L (список)
│   ├── File.php                 resolved F (файл)
│   └── Record/                  сырые данные из БД (load → resolve)
│       ├── IblockElementRecord.php
│       ├── IblockElementRecordCollection.php
│       ├── IblockPropertyDefinitionRecord.php
│       ├── IblockPropertyValueRecord.php
│       └── IblockPropertyValueRecordCollection.php
├── Load/                        orchestration (specs → records)
│   └── ElementLoader.php
├── Repository/                  обращения к БД
│   ├── IblockElementRepository.php
│   ├── IblockPropertyDefinitionRepository.php
│   ├── IblockPropertyValueRepository.php
│   ├── FileRepository.php
│   └── EnumerateRepository.php
└── Resolve/
    ├── Contract/
    │   ├── UserTypePropertyResolver.php
    │   └── SupportsPropertyResolver.php
    ├── LinkedElementCache.php   flyweight linked E на один get()
    ├── PropertyResolverRegistry.php
    ├── ResolvePipeline.php      resolve + сборка IblockElement
    └── BuiltIn/                 Date, DateTime, File, Enum, Number, …

tests/                           unit-тесты (без Bitrix runtime)
```

---

## Install

composer require jiscariot/iblock-element-reader
