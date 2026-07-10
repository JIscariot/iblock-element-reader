<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve\BuiltIn;

use IblockElementReader\Model\File;
use IblockElementReader\Repository\FileRepository;

final class FileValueResolver
{
    public function __construct(
        private readonly FileRepository $fileRepository = new FileRepository(),
    ) {
    }

    /**
     * @param list<mixed> $fileIds
     * @return array<int, File>
     */
    public function resolve(array $fileIds): array
    {
        $map = [];
        foreach ($this->fileRepository->findByIds($fileIds) as $id => $row) {
            $map[$id] = new File(
                id: $id,
                subdir: (string)($row['SUBDIR'] ?? ''),
                filename: (string)($row['FILE_NAME'] ?? ''),
                src: \CFile::GetFileSRC($row),
                size: (int)$row['FILE_SIZE'],
                width: isset($row['WIDTH']) ? (int)$row['WIDTH'] : null,
                height: isset($row['HEIGHT']) ? (int)$row['HEIGHT'] : null,
            );
        }

        return $map;
    }
}
