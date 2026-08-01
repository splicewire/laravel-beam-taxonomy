<?php

namespace Splicewire\Beam\Taxonomy\Data;

use Schemastud\DataSchemas\Contracts\SchemaIdentity;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The canonical Tag DTO — and, since tower-api-dissolution issue 19 (deferred slice),
 * a versioned SYSTEM schema: it implements {@see SchemaIdentity} so the beam schema
 * ladder migrator can reconcile-on-apply a synced tag payload against the current
 * shape before it is persisted (the tenant-sync-in bridge passes `TagData::class` as
 * the reconcile record type). Bump `schemaVersion()` whenever the wire shape of a
 * persisted tag changes.
 */
#[TypeScript]
class TagData extends Data implements SchemaIdentity
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $slug = null,
        public ?string $type = null,
    ) {}

    public static function schemaName(): string
    {
        return 'taxonomy/tag';
    }

    public static function schemaVersion(): int
    {
        return 1;
    }
}
