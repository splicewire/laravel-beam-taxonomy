<?php

namespace Splicewire\Beam\Taxonomy\Data;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Example;
use Schemastud\DataSchemas\Contracts\SchemaIdentity;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The canonical Silo DTO — and, since tower-api-dissolution issue 19 (deferred slice),
 * a versioned SYSTEM schema: it implements {@see SchemaIdentity} so the beam schema
 * ladder migrator can reconcile-on-apply a synced silo payload against the current
 * shape before it is persisted (the tenant-sync-in bridge passes `SiloData::class` as
 * the reconcile record type). Bump `schemaVersion()` whenever the wire shape of a
 * persisted silo changes.
 */
#[TypeScript]
#[Description('A silo — a hierarchical folder that organizes fragments. Silos can nest via `parentId`.')]
class SiloData extends Data implements SchemaIdentity
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

    public static function schemaName(): string
    {
        return 'taxonomy/silo';
    }

    public static function schemaVersion(): int
    {
        return 1;
    }
}
