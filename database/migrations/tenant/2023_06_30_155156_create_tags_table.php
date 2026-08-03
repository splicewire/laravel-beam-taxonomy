<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Splicewire\Beam\Taxonomy\BeamTaxonomyServiceProvider;

/**
 * TENANT TWIN of the ubiquitous `tags`/`taggables` base tables. Identical DDL to the central
 * migration one dir up; carries the dup-guard `if (Schema::hasTable('tags')) return;` because a host
 * that merges its central + tenant passes into one schema (or re-runs the tenant pass) would otherwise
 * collide on the already-created table. Published into the host's database/migrations/tenant/ by
 * {@see BeamTaxonomyServiceProvider::bootMigrations()} (native publishesMigrations, verbatim copy).
 *
 * Natural timestamp (2023_06_30_155156) sorts before the tenant-only ALTERs that target these tables
 * (tower's add_federation_scope_to_silos at 2026_07_03, taxonomy's add_external_ref at 2026_08_01).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tags')) {
            return;
        }

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
