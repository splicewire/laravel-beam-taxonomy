<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Splicewire\Beam\Taxonomy\BeamTaxonomyServiceProvider;

/**
 * TENANT TWIN of the ubiquitous `silos`/`siloables` base tables. Identical BASE DDL to the central
 * migration one dir up; carries the dup-guard `if (Schema::hasTable('silos')) return;` because a host
 * that merges its central + tenant passes into one schema (or re-runs the tenant pass) would otherwise
 * collide on the already-created table. Published into the host's database/migrations/tenant/ by
 * {@see BeamTaxonomyServiceProvider::bootMigrations()} (native publishesMigrations, verbatim copy).
 *
 * Beam ships only the BASE shape. The federation-scope columns are tower's tenant-only ALTER
 * (add_federation_scope_to_silos at 2026_07_03), which runs against the tenant `silos` this twin
 * creates — the 2023 natural timestamp sorts before it (and before external_ref at 2026_08_01).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('silos')) {
            return;
        }

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
