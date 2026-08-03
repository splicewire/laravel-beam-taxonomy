<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Splicewire\Beam\Taxonomy\BeamTaxonomyServiceProvider;

/**
 * Beam taxonomy base table — the flat `BeamTag` facet + its `taggable` morph pivot.
 *
 * UBIQUITOUS (shared/) — registered into BOTH the central `migrate` and the tenant
 * `tenants:migrate` passes by {@see BeamTaxonomyServiceProvider::bootMigrations()},
 * so `tags`/`taggables` exist identically in central and every tenant schema. This mirrors the
 * beam-ux `beam_ux_entries` charter (context-following residency): S7 attaches `BeamTag` as an
 * OPTIONAL morph facet on the ubiquitous `BeamUxEntry`, so a central entry that classifies with a
 * tag needs `tags`/`taggables` present centrally too.
 *
 * Epoch prefix (`0000_00_00_000000_`) — Laravel's Migrator sorts ALL registered paths by basename
 * globally, so a bare `create_` would sort AFTER real timestamps (`c` > `2`). The taxonomy base
 * tables are ALTERed by timestamped migrations in OTHER packages (tower's `add_federation_scope_to_silos`,
 * beam-taxonomy's tenant-published `add_external_ref_to_taxonomy_tables`), so the base creates MUST sort
 * before the earliest possible timestamp — the epoch prefix guarantees that in both the central and the
 * merged tenant pass.
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
