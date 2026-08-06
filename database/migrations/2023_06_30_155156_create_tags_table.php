<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Splicewire\Beam\Taxonomy\BeamTaxonomyServiceProvider;

/**
 * Beam taxonomy base table — the flat `BeamTag` facet + its `taggable` morph pivot.
 *
 * UBIQUITOUS — shipped as this CENTRAL migration plus a guarded tenant twin at
 * database/migrations/tenant/, both published into the host by
 * {@see BeamTaxonomyServiceProvider::bootMigrations()} (native publishesMigrations), so
 * `tags`/`taggables` exist identically in central and every tenant schema. This mirrors the beam-ux
 * `beam_ux_entries` charter (context-following residency): S7 attaches `BeamTag` as an OPTIONAL morph
 * facet on the ubiquitous `BeamUxEntry`, so a central entry that classifies with a tag needs
 * `tags`/`taggables` present centrally too.
 *
 * NATURAL timestamp (`2023_06_30_155156`) — restored from the table's original create. Laravel's
 * Migrator sorts ALL registered paths by basename globally; the taxonomy base tables are ALTERed by
 * timestamped migrations in OTHER packages (tower's tenant-only `add_federation_scope_to_silos` at
 * 2026_07_03, beam-taxonomy's tenant-published `add_external_ref_to_taxonomy_tables` at 2026_08_01), so
 * this 2023 base create sorts well before every such ALTER in both the central and the merged tenant
 * pass. publishesMigrations copies verbatim (update_date_on_publish=false), so this timestamp is the
 * published timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 512);
            $table->string('slug', 512);
            $table->string('type')->nullable();
            $table->timestamps();
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignUuid('tag_id')->constrained()->cascadeOnDelete();
            $table->uuid('taggable_id');
            $table->string('taggable_type');
            $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
    }
};
