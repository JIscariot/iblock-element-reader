<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve\Contract;

use IblockElementReader\Model\Record\IblockPropertyDefinitionRecord;

/**
 * Резолвер, который сам решает, какие свойства обрабатывает.
 *
 * Выбор — по любым полям определения: code, type, userType, linkIblockId, multiple, withDescription.
 * Проверяется раньше резолверов по userType() и встроенных; из нескольких подходящих берётся
 * первый в порядке регистрации. supports() вызывается один раз на свойство за get().
 *
 * userType() здесь — только подсказка (для логов, отладки); может быть пустой строкой,
 * если резолвер работает со свойствами без USER_TYPE.
 */
interface SupportsPropertyResolver extends UserTypePropertyResolver
{
    public function supports(IblockPropertyDefinitionRecord $definition): bool;
}
