<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve;

/** Resolved fields and props for one element (internal pipeline state). */
final readonly class ResolvedElementState
{
    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $props
     */
    public function __construct(
        public int $id,
        public int $iblockId,
        public array $fields = [],
        public array $props = [],
    ) {
    }

    public function field(string $key): mixed
    {
        return $this->fields[$key] ?? null;
    }

    public function prop(string $key): mixed
    {
        return $this->props[$key] ?? null;
    }

    public function hasProp(string $key): bool
    {
        return array_key_exists($key, $this->props);
    }
}
