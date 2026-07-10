<?php

declare(strict_types=1);

namespace IblockElementReader\Enum;

/** Кастомные типы свойств (b_iblock_property.USER_TYPE). */
enum PropertyUserTypeEnum: string
{
    case HTML           = 'HTML';
    case DATE           = 'Date';
    case DATETIME       = 'DateTime';
    case DIRECTORY      = 'directory';
    case ELEMENT_XML_ID = 'ElementXmlID';
    case SECTION_XML_ID = 'SectionXmlID';
    case EMPLOYEE       = 'employee';
    case USER_ID        = 'UserID';
    case MAP_YANDEX     = 'map_yandex';
    case MAP_GOOGLE     = 'map_google';
    case VIDEO          = 'video';
    case SEQUENCE       = 'Sequence';
}
