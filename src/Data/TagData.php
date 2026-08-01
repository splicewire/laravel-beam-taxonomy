<?php

declare(strict_types=1);

namespace Splicewire\Beam\Taxonomy\Data;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TagData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $slug = null,
        public ?string $type = null,
    ) {}
}
