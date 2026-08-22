<?php

namespace Splicewire\Beam\Taxonomy;

use Illuminate\Database\Eloquent\Relations\Relation;
use Rushing\PermissionCascade\Support\CascadePolicyRegistrar;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Splicewire\Beam\Doctor\BeamDoctorManifest;
use Splicewire\Beam\Install\BeamInstallManifest;
use Splicewire\Beam\Particle\ParticleResource;
use Splicewire\Beam\Particle\ParticleResourceRegistry;
use Splicewire\Beam\Taxonomy\Doctor\BeamTaxonomyMigrationsAudit;
use Splicewire\Beam\Taxonomy\Models\Silo;
use Splicewire\Beam\Taxonomy\Models\Tag;

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
 *
 * The taxonomy base tables (`tags`/`taggables`, `silos`/`siloables`) plus the tenant-only
 * `add_external_ref_to_taxonomy_tables` ALTER ship as PUBLISH-ONLY spatie/laravel-package-tools
 * stubs — the idiomatic pattern for a PackageServiceProvider, mirroring beam-core's own substrate
 * migrations. `runsMigrations` stays FALSE, so beam-taxonomy never loads them at runtime;
 * `vendor:publish --tag=beam-taxonomy-migrations` re-stamps + sequences timestamped copies into the
 * HOST at install time, which runs them.
 *
 * UBIQUITOUS residency (S7 / ADR-0165 §2): `tags`/`taggables` and `silos`/`siloables` must exist in
 * BOTH the central and every tenant schema because `BeamTag`/`BeamSilo` attach as OPTIONAL morph
 * facets on the context-following (central + tenant) `BeamUxEntry`. Per "everything is shared by
 * default," each base table publishes to the SINGLE `database/migrations/shared/` destination, not a
 * duplicated flat+tenant pair, registered via `->hasMigrations([...])` in
 * {@see self::configurePackage()} (NOT `->discoversMigrations()`, whose `->files()` is non-recursive
 * and would miss the `shared/` subdir) — beam-tenancy's `registerSharedMigrationsPath()` runs that one
 * directory in both the central `migrate` pass and Stancl's tenant pass. package-tools'
 * `generateMigrationName` stamps each entry a second apart in declared order, so the base creates
 * re-stamp before the tenant-only `add_external_ref` ALTER that targets them, every install.
 */
class BeamTaxonomyServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-beam-taxonomy')
            ->hasConfigFile('beam/taxonomy')
            ->hasMigrations([
                'shared/create_tags_table',
                'shared/create_silos_table',
                'tenant/add_external_ref_to_taxonomy_tables',
            ]);
    }

    public function packageBooted(): void
    {
        $this->bootMorphMap();
        $this->bootPolicies();
        $this->registerResources();

        // Self-register into beam-core's install manifest so `splicewire:beam:install` publishes
        // this package's `shared/` migrations (tags/silos, including name_path) with the rest of
        // the stack. Recohere gap: this package predates the manifest and was never wired in.
        if ($this->app->bound(BeamInstallManifest::class)) {
            $this->app->make(BeamInstallManifest::class)->register(
                package: 'splicewire/laravel-beam-taxonomy',
                publishTags: ['beam-taxonomy-config', 'beam-taxonomy-migrations'],
                migrates: true,
            );
        }

        if ($this->app->bound(BeamDoctorManifest::class)) {
            $this->app->make(BeamDoctorManifest::class)->register(
                'splicewire/laravel-beam-taxonomy',
                BeamTaxonomyMigrationsAudit::class,
            );
        }
    }

    /**
     * This package's own morph aliases — the wire identifiers its polymorphic rows store
     * (`siloable_type`, `taggable_type`), and the permission-token prefixes (ADR-0118: the alias
     * IS the prefix, so an unaliased model leaks its FQCN into the token).
     *
     * The package that OWNS the model owns its alias; a host should only have to declare aliases
     * for its own models. Registered ADDITIVELY (`Relation::morphMap`), NEVER `enforceMorphMap`:
     * a beam-composing host has many models on class-string morphs, and toggling global strict
     * mode rejects every one of them (`ClassMorphViolationException`). Mirrors
     * {@see \Splicewire\Beam\BeamServiceProvider} — additive registration is idempotent, and a
     * host booting later keeps last-writer override authority.
     */
    protected function bootMorphMap(): void
    {
        Relation::morphMap([
            'silo' => Silo::class,
            'tag' => Tag::class,
        ]);
    }

    /**
     * This package's own authorization, declared on the models via `#[UseCascadePolicy]` and bound
     * here. The package that owns the model owns its policy binding — a host should not have to know
     * that a Silo needs one, any more than it should have to know the model's morph alias.
     *
     * `SiloPolicy`/`TagPolicy` used to be hand-written `extends BaseModelPolicy` classes with a
     * `$defaultModelClass` and NOTHING else — pure ceremony around the cascade machinery. The
     * attribute expresses exactly that, so the classes are deleted rather than moved.
     * {@see CascadePolicyRegistrar::register()} reads the attribute by reflection and binds the Gate
     * exactly as the former `Gate::policy($model, $policy)` calls did — behaviour-identical.
     *
     * NB the attribute only expresses UNCONDITIONAL answers: any ability not named in it falls to
     * `ConfiguredModelPolicy::__call()`, which returns `false`. A model whose rule depends on the
     * INSTANCE (or which adds cascading custom abilities) still needs a real Policy class — which is
     * why beam-embed's `EmbedPolicy` and tower's `ThreadPolicy`/`ModelStatusPolicy` stay classes.
     */
    protected function bootPolicies(): void
    {
        CascadePolicyRegistrar::registerMany([
            Silo::class,
            Tag::class,
        ]);
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
        // pre-dissolution contract). Filterable rides DataFilter::query('tags').
        $tagModel = config('beam.taxonomy.models.tag');
        if (is_string($tagModel) && class_exists($tagModel)) {
            $registry->register(new ParticleResource(
                key: 'tags',
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
                key: 'silos',
                model: $siloModel,
                data: config('beam.taxonomy.data.silo'),
                input: config('beam.taxonomy.input.silo'),
                includes: ['fragments'],
            ));
        }
    }
}
