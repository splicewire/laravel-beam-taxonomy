<?php

namespace Splicewire\Beam\Taxonomy\Sync;

use Splicewire\Beam\Taxonomy\Models\Silo;
use Splicewire\Sync\Transit\SyncData;

/**
 * Transit contract for a {@see Silo}. Carries the *source* parent id, remapped through
 * lineage on import. See {@see SyncData}.
 *
 * Relocated DOWN out of `Splicewire\Tower\Tenancy\Sync\Data\SiloSyncData` (tower-tenancy) into
 * beam-taxonomy alongside its model (tower-api-dissolution issue 17 U4a). Extends beam-sync's
 * transit base directly. Sync payloads use the estate's camelCase wire convention; model storage
 * remains snake_case behind the import/export seam.
 */
class SiloSyncData extends SyncData
{
    public function __construct(
        public string $hash,
        public ?string $name,
        public ?string $slug,
        public ?string $sourceParentId,
        /**
         * The source's canonical Silo schema `$id` (relative `<name>/<version>`) so the
         * target can reconcile-on-apply (issue 19). A NEW additive protocol key — every
         * other wire key is emitted last and never folded into `hash`.
         */
        public ?string $schemaId = null,
    ) {}
}
