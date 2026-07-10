<?php

declare(strict_types=1);

namespace IblockElementReader\Load;

use IblockElementReader\Model\Record\IblockElementRecordCollection;

interface ElementLoaderInterface
{
    /**
     * @param int[] $iblockIds
     * @param int[] $ids
     * @param string[]|null $fields
     * @param string[]|null $props
     */
    public function load(array $iblockIds, array $ids, ?array $fields, ?array $props): IblockElementRecordCollection;
}
