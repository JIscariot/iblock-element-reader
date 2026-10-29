# Changelog

Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.1.0/),
версии — по [Semantic Versioning](https://semver.org/lang/ru/).

## [Unreleased]

### Changed

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
