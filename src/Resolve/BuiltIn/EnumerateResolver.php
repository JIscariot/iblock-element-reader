<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve\BuiltIn;

use IblockElementReader\Model\Enumerate;
use IblockElementReader\Repository\EnumerateRepository;

final class EnumerateResolver
{
    public function __construct(
        private readonly EnumerateRepository $enumerateRepository = new EnumerateRepository(),
    ) {
    }

    /**
     * @param list<mixed> $enumIds
     * @return array<int, Enumerate>
     */
    public function resolve(array $enumIds): array
    {
        $map = [];
        foreach ($this->enumerateRepository->findByIds($enumIds) as $id => $row) {
            $map[$id] = new Enumerate(
                id: $id,
                value: (string)$row['VALUE'],
                xmlId: $row['XML_ID'] !== null ? (string)$row['XML_ID'] : null,
            );
        }

        return $map;
    }
}
