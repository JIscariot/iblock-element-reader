# Changelog

Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.1.0/),
версии — по [Semantic Versioning](https://semver.org/lang/ru/).

## [Unreleased]

### Added

- `Resolve\Contract\SupportsPropertyResolver` — резолвер с методом `supports(IblockPropertyDefinitionRecord)`: выбор свойств по коду, типу, `LINK_IBLOCK_ID` и другим полям определения, в том числе для свойств без `USER_TYPE`. Проверяется раньше резолверов по `userType()` и встроенных; из нескольких подходящих берётся первый зарегистрированный; `supports()` вызывается один раз на свойство за `get()`.

### Changed

- Два резолвера `UserTypePropertyResolver` (без `supports()`) с одинаковым `userType()` в `withResolvers()` теперь дают `LogicException` с именами обоих классов вместо тихой перезаписи.
- `IblockPropertyDefinitionRepository` больше не `final` — его можно подменить в тестах пайплайна.
- Свойства `S` и `N` без `USER_TYPE`: `resolved` заполняется всегда, независимо от `.VALUE` и в том числе у вложенных свойств связанных элементов. `S` → `raw` как есть, `N` → `int|float|null`.
- `NumberValueParser`: обрезает пробелы (включая неразрывные), убирает пробелы-разделители тысяч (`"1 500"` → `1500`), понимает десятичную запятую (`"1,6"` → `1.6`), возвращает `int` для целых значений вида `"15000.0000"`. Нечисловое значение теперь даёт `null`, а не исходную строку.
- `filter(id: [])` теперь означает пустую выборку: `get()` возвращает пустую коллекцию, `first()` — `null`, запросов к БД нет. Раньше пустой массив снимал фильтр по ID и загружал весь инфоблок, а без `iblock` бросал `IblockScopeException`. Отсутствие фильтра по ID — `id: null` или аргумент не передан.

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

[Unreleased]: https://github.com/JIscariot/iblock-element-reader/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/JIscariot/iblock-element-reader/releases/tag/v0.1.0
