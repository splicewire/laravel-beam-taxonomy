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

            // Package-owned tenant migration, published to the host under the
            // `beam-taxonomy-migrations` tag (PUBLISH convention — the package is the source of
            // truth; the host commits the published runnable copy). The `tenant/` subdir is
            // preserved on publish, so it lands in the host's `database/migrations/tenant/`.
            $this->publishes([
                __DIR__.'/../database/migrations/tenant/2026_08_01_000002_add_external_ref_to_taxonomy_tables.php.stub' => database_path('migrations/tenant/2026_08_01_000002_add_external_ref_to_taxonomy_tables.php'),
            ], 'beam-taxonomy-migrations');
        }

        $this->bootMigrations();
        $this->registerResources();
    }

    /**
     * Register the taxonomy base-table migrations (`tags`/`taggables`, `silos`/`siloables`) so a bare
     * beam site provisions its own taxonomy facets — no longer homed in tower (recohere S7 loose end).
     *
     * UBIQUITOUS (shared/) residency: the tables must exist in BOTH the central and every tenant schema
     * because `BeamTag`/`BeamSilo` attach as OPTIONAL morph facets on the context-following (central +
     * tenant) `BeamUxEntry` (S7 / ADR-0165 §2). So the shared dir is registered via BOTH mechanisms —
     * `loadMigrationsFrom()` for the central `migrate` pass, and a `--path` push onto Stancl's tenant
     * migration parameters for the `tenants:migrate` pass. Same dir feeds both, so the shape is identical.
     *
     * Gated by `config('beam.taxonomy.register_migrations', true)` — defaults on; a host that vendors the
     * base tables elsewhere (or publishes them) turns it off.
     */
    protected function bootMigrations(): void
    {
        if (! config('beam.taxonomy.register_migrations', true)) {
            return;
        }

        $sharedDir = realpath(__DIR__.'/../database/migrations/shared')
            ?: __DIR__.'/../database/migrations/shared';

        // Central estate — auto-discovered by `migrate`.
        $this->loadMigrationsFrom($sharedDir);

        // Tenant estate — pushed onto Stancl's `--path` array (no auto-discovery). Same dir, so the
        // table shape is identical central + tenant.
        $paths = config('tenancy.migration_parameters.--path', []);

        if (! in_array($sharedDir, $paths, true)) {
            config()->push('tenancy.migration_parameters.--path', $sharedDir);
        }
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
