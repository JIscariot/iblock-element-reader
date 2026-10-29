# Changelog

Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.1.0/),
версии — по [Semantic Versioning](https://semver.org/lang/ru/).

## [Unreleased]

## [0.2.0] - 2026-10-29

### Added

- README: полный пример с резолвером `supports()`, свойством `G`, вложенными описаниями и методами `property()` / `value()` / `values()`.
- `IblockElement::$iblockSectionId` — основной раздел элемента (`IBLOCK_SECTION_ID`), в том числе у связанных элементов `E`. Выбирается тем же запросом, что и остальные поля, без `withFields`.
- Методы чтения значений, одинаковые для одиночных и множественных свойств: `IblockProperty::values()`, `first()`, `resolved()` (при `null` — `raw`), `resolvedList()` (без `null` и пустых строк), `descriptions()`; `IblockElement::property($code)` и `value($code)`.
- Резолвинг свойств типа `G` (привязка к разделу): с `.VALUE` в `resolved` — `Model\Section` (`id`, `iblockId`, `name`, `code`, `sort`, `parentId`, `isActive`); несуществующий раздел → `null`. Разделы загружаются одним запросом к `SectionTable` на `get()` (`Repository\SectionRepository`, карта `AutoResolveMaps::$sections`).
- `Resolve\Contract\SupportsPropertyResolver` — резолвер с методом `supports(IblockPropertyDefinitionRecord)`: выбор свойств по коду, типу, `LINK_IBLOCK_ID` и другим полям определения, в том числе для свойств без `USER_TYPE`. Проверяется раньше резолверов по `userType()` и встроенных; из нескольких подходящих берётся первый зарегистрированный; `supports()` вызывается один раз на свойство за `get()`.
- `DateTimeValueParser::parseValue($value, dateOnly: false)` — статический разбор дат для строковых свойств тем же кодом, что и у `Date` / `DateTime`. Принимает форматы БД (`Y-m-d H:i:s`, `Y-m-d H:i`, `Y-m-d`, `Y-m-d\TH:i:s`), сайта (`d.m.Y H:i:s`, `d.m.Y H:i`, `d.m.Y`) и объекты `Bitrix\Main\Type\Date` / `DateTime` / `\DateTimeInterface`.

### Changed

- Минимальная версия PHP — 8.2 (в коде используются `readonly`-классы, в 8.1 они не поддерживаются).
- `resolved` у свойств `G` с `.VALUE` — теперь `Section` (или `null`), а не ID раздела. ID по-прежнему в `raw`.
- Два резолвера `UserTypePropertyResolver` (без `supports()`) с одинаковым `userType()` в `withResolvers()` теперь дают `LogicException` с именами обоих классов вместо тихой перезаписи.
- `IblockPropertyDefinitionRepository`, `IblockElementRepository` и новый `SectionRepository` не `final` — их можно подменить в тестах.
- Свойства `S` и `N` без `USER_TYPE`: `resolved` заполняется всегда, независимо от `.VALUE` и в том числе у вложенных свойств связанных элементов. `S` → `raw` как есть, `N` → `int|float|null`.
- `NumberValueParser`: обрезает пробелы (включая неразрывные), убирает пробелы-разделители тысяч (`"1 500"` → `1500`), понимает десятичную запятую (`"1,6"` → `1.6`), возвращает `int` для целых значений вида `"15000.0000"`. Нечисловое значение теперь даёт `null`, а не исходную строку.
- `filter(id: [])` теперь означает пустую выборку: `get()` возвращает пустую коллекцию, `first()` — `null`, запросов к БД нет. Раньше пустой массив снимал фильтр по ID и загружал весь инфоблок, а без `iblock` бросал `IblockScopeException`. Отсутствие фильтра по ID — `id: null` или аргумент не передан.

### Fixed

- Порядок значений множественных свойств теперь детерминирован — по `ID` значения в БД (`ORDER BY ID` для `b_iblock_element_prop_m*` и `ElementPropertyTable`); раньше порядок зависел от СУБД.
- `description` заполняется только у свойств с `WITH_DESCRIPTION = Y`, на всех уровнях вложенности; у остальных — `null`.
- Вложенный пайплайн (связанные элементы `E`) теперь использует тот же загрузчик элементов, репозиторий определений и загрузчик карт, что и корневой, — раньше он всегда создавал свои.
- `DateTimeValueParser`: несуществующие даты больше не «перетекают» (`31.02.2024` давало 2 марта, `2024-13-01` — 1 января 2025, `25:00` — 01:00 следующего дня), двузначный год не превращается в год 0024, а `now` / `tomorrow` и подобные выражения больше не дают текущую дату — всё это теперь `null`. Убран свободный разбор через `new DateTimeImmutable($string)`.
- `USER_TYPE` `Date`: время в `resolved` всегда `00:00:00`, даже если в БД значение хранится со временем.

## [0.1.0] - 2026-10-29

Первый публичный релиз.

### Added

- `IblockElementReader` — immutable fluent API: `filter()`, `withFields()`, `withProps()`, `withResolvers()`, `get()`, `first()`.
- Загрузка полей и свойств элементов напрямую из БД без обращения к `b_iblock`; поддержка нескольких инфоблоков и storage mode (`VERSION` из `b_iblock_property`).
- Batch resolve pipeline с суффиксом `.VALUE`: значение `raw` из БД и `resolved` после резолва.
- Встроенный авто-резолв для типов `L` (списки), `F` (файлы), `E` (привязка к элементам), а также чисел и даты/времени.
- Вложенные свойства по привязке `E` → другой инфоблок с кешированием связанных элементов.
- Контракт `UserTypePropertyResolver` для кастомных `USER_TYPE`.
- Модели результата: `IblockElement`, `IblockProperty`, `IblockPropertyValue`, `IblockElementCollection`.
- Требования: PHP 8.1+, Bitrix CMS с модулем `iblock`.

[Unreleased]: https://github.com/JIscariot/iblock-element-reader/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/JIscariot/iblock-element-reader/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/JIscariot/iblock-element-reader/releases/tag/v0.1.0
