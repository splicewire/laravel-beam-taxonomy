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
        }

        $this->registerResources();
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
