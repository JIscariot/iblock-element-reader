<?php

declare(strict_types=1);

namespace IblockElementReader\Resolve;

/** Mutable resolved props while pipeline runs. */
final class ResolvedElementStateBuilder
{
    /** @param array<string, mixed> $fields */
    private function __construct(
        private readonly int $id,
        private readonly int $iblockId,
        private readonly array $fields,
        private array $props = [],
    ) {
    }

    /**
     * @param array<string, mixed> $fields
     */
    public static function create(int $id, int $iblockId, array $fields = []): self
    {
        return new self($id, $iblockId, $fields);
    }

    public function setProp(string $code, mixed $value): void
    {
        $this->props[$code] = $value;
    }

    public function build(): ResolvedElementState
    {
        return new ResolvedElementState(
            id: $this->id,
            iblockId: $this->iblockId,
            fields: $this->fields,
            props: $this->props,
        );
    }
}
