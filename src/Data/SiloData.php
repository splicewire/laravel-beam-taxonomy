<?php

namespace Splicewire\Beam\Taxonomy\Data;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Example;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[Description('A silo — a hierarchical folder that organizes fragments. Silos can nest via `parentId`.')]
class SiloData extends Data
{
    public function __construct(
        #[Example('1f3c2a9e-7b6d-4c1a-8e2f-0a9b8c7d6e5f')]
        public string $id,
        #[Example('Policies')]
        public string $name,
        #[Example('policies')]
        public ?string $slug = null,
        #[Description('Parent silo id, or null for a top-level silo.')]
        public ?string $parentId = null,
        #[Description('Full slash-delimited name path from the root, e.g. for breadcrumbs.')]
        #[Example('Policies / Refunds')]
        public ?string $namePath = null,
        public ?int $childrenCount = null,
        #[Description('Derived compliance data filed into this silo (ontology ticket 04): concepts / rules / evidence extracted from the silo\'s source material. Null unless the read eager-counts them.')]
        public ?int $conceptsCount = null,
        public ?int $rulesCount = null,
        public ?int $evidenceCount = null,
    ) {}
}
