<?php

namespace Splicewire\Beam\Taxonomy;

use Illuminate\Support\ServiceProvider;
use Splicewire\Beam\Particle\ParticleResource;
use Splicewire\Beam\Particle\ParticleResourceRegistry;

/**
 * Wires the beam taxonomy surface. Each taxonomy resource (Tag, Silo …) is a
 * declared {@see ParticleResource} registered into beam-core's
 * {@see ParticleResourceRegistry}, so the generic ParticleController serves its
 * CRUD with no controller class — mounted by the host via
 * `Route::particleResource($uri, $key)`.
 *
 * The concrete model + read Data class are host-bound via config
 * (`beam.taxonomy.models.*` / `beam.taxonomy.data.*`) so this foundation-tier
 * package never depends UP on the host's model tier.
 */
class BeamTaxonomyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/beam/taxonomy.php', 'beam.taxonomy');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/beam/taxonomy.php' => $this->app->configPath('beam/taxonomy.php'),
            ], 'beam-taxonomy-config');

            $this->bootMigrations();
        }

        $this->registerResources();
    }

    /**
     * PUBLISH-ONLY ubiquitous migrations — the idiomatic pattern for a PLAIN ServiceProvider, mirroring
     * the beam-workflows exemplar (commit 994aba1) / beam-core PackageServiceProvider. Undo of the
     * recohere runtime `loadMigrationsFrom` (central) + Stancl `--path` push (tenant) on the epoch-prefixed
     * shared/ dir.
     *
     * A plain provider has no spatie/laravel-package-tools machinery, so this uses Laravel's native
     * {@see ServiceProvider::publishesMigrations()} (Laravel 11+). It does NOT loadMigrationsFrom and
     * does NOT push onto `tenancy.migration_parameters.--path`: the package never runs these at runtime.
     * `vendor:publish --tag=beam-taxonomy-migrations` drops the copies into the HOST, which commits the
     * runnable copies and runs its own `migrate` + `tenants:migrate` passes.
     *
     * UBIQUITOUS residency (S7 / ADR-0165 §2): `tags`/`taggables` and `silos`/`siloables` must exist in
     * BOTH the central and every tenant schema because `BeamTag`/`BeamSilo` attach as OPTIONAL morph
     * facets on the context-following (central + tenant) `BeamUxEntry`. So each base table ships as a
     * CENTRAL migration AND a `Schema::hasTable()`-guarded TENANT twin (the dup-guard is exactly the
     * ubiquitous-table tenant-twin case).
     *
     * The base creates carry their NATURAL timestamps (2023_06_30_155156 tags, 2023_06_30_171106 silos),
     * restored from the tables' original creates. publishesMigrations copies verbatim
     * (`database.migrations.update_date_on_publish`=false), so the source timestamp IS the published
     * timestamp — a 2023 base sorts before every tenant-only ALTER that targets it (tower's
     * add_federation_scope_to_silos at 2026_07_03, this package's add_external_ref at 2026_08_01) in the
     * globally-basename-sorted migrate pass.
     *
     * The tenant `add_external_ref` ALTER stays a publish-STUB (`.php.stub` → renamed to `.php` on
     * publish) — kept out of any directory mapping so it is not copied verbatim as an unrunnable `.stub`.
     * Explicit per-file mappings are used throughout for the same reason (a directory mapping would sweep
     * the `.stub` and cross the central/tenant subdir boundary).
     */
    protected function bootMigrations(): void
    {
        $migrations = __DIR__.'/../database/migrations';

        $this->publishesMigrations([
            // CENTRAL base creates.
            $migrations.'/2023_06_30_155156_create_tags_table.php' => $this->app->databasePath('migrations/2023_06_30_155156_create_tags_table.php'),
            $migrations.'/2023_06_30_171106_create_silos_table.php' => $this->app->databasePath('migrations/2023_06_30_171106_create_silos_table.php'),

            // TENANT twins (dup-guarded) + the external_ref ALTER stub (renamed on publish).
            $migrations.'/tenant/2023_06_30_155156_create_tags_table.php' => $this->app->databasePath('migrations/tenant/2023_06_30_155156_create_tags_table.php'),
            $migrations.'/tenant/2023_06_30_171106_create_silos_table.php' => $this->app->databasePath('migrations/tenant/2023_06_30_171106_create_silos_table.php'),
            $migrations.'/tenant/2026_08_01_000002_add_external_ref_to_taxonomy_tables.php.stub' => $this->app->databasePath('migrations/tenant/2026_08_01_000002_add_external_ref_to_taxonomy_tables.php'),
        ], 'beam-taxonomy-migrations');
    }

    /**
     * Register the taxonomy resource declarations, guarded so the package is a
     * no-op on a host that hasn't wired the particle surface or bound the models.
     */
    protected function registerResources(): void
    {
        if (! $this->app->bound(ParticleResourceRegistry::class)) {
            return;
        }

        $registry = $this->app->make(ParticleResourceRegistry::class);

        // Tag — read-only surface today (index-only mount preserves the exact
        // pre-dissolution contract). Filterable rides DataFilter::query('tag').
        $tagModel = config('beam.taxonomy.models.tag');
        if (is_string($tagModel) && class_exists($tagModel)) {
            $registry->register(new ParticleResource(
                key: 'tag',
                model: $tagModel,
                data: config('beam.taxonomy.data.tag'),
            ));
        }

        // Silo — the CRUD declaration BeamSilo rides. The host's SiloController (a tier-C
        // survivor: `?tree` index, withCount show, custom create/delete envelopes) resolves
        // THIS declaration from the registry for its inherited write/read internals, so the
        // silo write/filter surface is declared once, here — not inline in the controller.
        // includes:['fragments'] preserves the eager-load; the child-sync afterWrite was a
        // no-op pre-dissolution, so it's omitted.
        $siloModel = config('beam.taxonomy.models.silo');
        if (is_string($siloModel) && class_exists($siloModel)) {
            $registry->register(new ParticleResource(
                key: 'silo',
                model: $siloModel,
                data: config('beam.taxonomy.data.silo'),
                input: config('beam.taxonomy.input.silo'),
                includes: ['fragments'],
            ));
        }
    }
}
