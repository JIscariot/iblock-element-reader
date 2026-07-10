<?php

declare(strict_types=1);

namespace IblockElementReader\Enum;

/** Родные типы свойств (b_iblock_property.PROPERTY_TYPE). */
enum PropertyTypeEnum: string
{
    case STRING  = 'S';
    case NUMBER  = 'N';
    case LIST    = 'L';
    case FILE    = 'F';
    case ELEMENT = 'E';
    case SECTION = 'G';
}
