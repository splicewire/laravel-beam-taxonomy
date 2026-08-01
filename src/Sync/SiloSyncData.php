<?php

namespace Splicewire\Beam\Taxonomy\Sync;

use Spatie\LaravelData\Attributes\MapName;
use Splicewire\Beam\Taxonomy\Models\Silo;
use Splicewire\Sync\Transit\SyncData;

/**
 * Transit contract for a {@see Silo}. Carries the *source* parent id, remapped through
 * lineage on import. See {@see SyncData}.
 *
 * Relocated DOWN out of `Splicewire\Tower\Tenancy\Sync\Data\SiloSyncData` (tower-tenancy) into
 * beam-taxonomy alongside its model (tower-api-dissolution issue 17 U4a). Extends beam-sync's
 * transit base directly. The `#[MapName]` keys (`_hash`, `source_parent_id`) and field order are
 * UNCHANGED, so the wire payload is byte-identical to the pre-relocation shape (guarded by
 * SyncPayloadByteStabilityTest). The old FQCN stays resolving via a class_alias shim in
 * tower-tenancy.
 */
class SiloSyncData extends SyncData
{
    public function __construct(
        #[MapName('_hash')]
        public string $hash,
        public ?string $name,
        public ?string $slug,
        #[MapName('source_parent_id')]
        public ?string $sourceParentId,
        /**
         * The source's canonical Silo schema `$id` (relative `<name>/<version>`) so the
         * target can reconcile-on-apply (issue 19). A NEW additive protocol key — every
         * other wire key is byte-identical to the pre-issue-19 shape. Emitted last and
         * never folded into `_hash`.
         */
        #[MapName(SyncData::SCHEMA_KEY)]
        public ?string $schemaId = null,
    ) {}
}
