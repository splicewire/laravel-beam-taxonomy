<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Splicewire\Beam\Taxonomy\BeamTaxonomyServiceProvider;

/**
 * Beam taxonomy base table — the hierarchical `BeamSilo` facet + its `siloable` morph pivot.
 *
 * UBIQUITOUS — shipped as this CENTRAL migration plus a guarded tenant twin at
 * database/migrations/tenant/, both published into the host by
 * {@see BeamTaxonomyServiceProvider::bootMigrations()} (native publishesMigrations), so
 * `silos`/`siloables` exist identically in central and every tenant schema. Mirrors the beam-ux
 * `beam_ux_entries` charter (context-following residency): S7 attaches `BeamSilo` as an OPTIONAL
 * hierarchical morph facet on the ubiquitous `BeamUxEntry`.
 *
 * Beam ships only the BASE shape here. The federation-scope columns (`silos.is_grantable`,
 * `silos.default_member_scope`, `siloables.scope`) are a satellite/federation concern and stay in
 * tower as a tenant-only ALTER (`add_federation_scope_to_silos`, 2026_07_03) — free beam never carries a
 * federation column. That ALTER runs against the tenant `silos` this migration creates.
 *
 * NATURAL timestamp (`2023_06_30_171106`) — restored from the table's original create — so the base
 * create sorts before every timestamped ALTER that targets it (tower federation scope 2026_07_03,
 * taxonomy external_ref 2026_08_01) in the globally-basename-sorted migrate pass. publishesMigrations
 * copies verbatim (update_date_on_publish=false), so this timestamp is the published timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('silos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug');
            $table->uuid('parent_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('siloables', function (Blueprint $table) {
            $table->foreignUuid('silo_id')->constrained()->cascadeOnDelete();
            $table->uuid('siloable_id');
            $table->string('siloable_type');
            $table->unique(['silo_id', 'siloable_id', 'siloable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siloables');
        Schema::dropIfExists('silos');
    }
};
