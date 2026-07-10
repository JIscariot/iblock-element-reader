<?php

declare(strict_types=1);

namespace IblockElementReader\Model;

final readonly class IblockElement
{
    /**
     * @param array<string, IblockProperty> $properties
     */
    public function __construct(
        public int $id,
        public int $iblockId,
        public ?string $name = null,
        public ?string $code = null,
        public ?int $sort = null,
        public null|int|File $detailPicture = null,
        public null|int|File $previewPicture = null,
        public ?string $detailText = null,
        public ?string $previewText = null,
        public ?bool $isActive = null,
        public ?\DateTimeInterface $updatedAt = null,
        public ?\DateTimeInterface $createdAt = null,
        public ?\DateTimeInterface $activeFromAt = null,
        public ?\DateTimeInterface $activeToAt = null,
        public array $properties = [],
    ) {
    }
}
