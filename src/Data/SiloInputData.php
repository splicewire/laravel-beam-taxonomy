<?php

declare(strict_types=1);

namespace Splicewire\Beam\Taxonomy\Data;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Example;

#[Description('Payload for creating or updating a silo.')]
class SiloInputData extends Data
{
    public function __construct(
        #[Example('Policies')]
        public ?string $name = null,
        #[Example('policies')]
        public ?string $slug = null,
        #[Description('Parent silo id to nest under, or null for a top-level silo.')]
        public ?string $parentId = null,
        /** @var array[]|null */
        #[Description('Child silos to create beneath this one.')]
        public ?array $children = null,
    ) {}

    public function toModelAttributes(): array
    {
        $map = [
            'name' => 'name',
            'slug' => 'slug',
            'parentId' => 'parent_id',
        ];

        $attrs = [];
        foreach ($map as $prop => $column) {
            if ($this->$prop !== null) {
                $attrs[$column] = $this->$prop;
            }
        }

        return $attrs;
    }
}
