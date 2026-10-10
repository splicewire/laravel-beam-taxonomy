<?php

use Illuminate\Support\Facades\Gate;
use Splicewire\Beam\Authorization\ResourceReadGuard;
use Splicewire\Beam\Particle\ParticleResource;
use Splicewire\Beam\Particle\ParticleResourceRegistry;
use Splicewire\Beam\Taxonomy\BeamTaxonomyServiceProvider;
use Splicewire\Beam\Taxonomy\Data\SiloData;
use Splicewire\Beam\Taxonomy\Data\SiloInputData;
use Splicewire\Beam\Taxonomy\Models\Silo;
use Splicewire\Beam\Taxonomy\QueryBuilders\SiloQuery;
use Splicewire\Beam\Taxonomy\Tests\Fixtures\Tag;

it('registers the tag particle resource over the host-bound model', function () {
    $resource = app(ParticleResourceRegistry::class)->get('tags');

    expect($resource)->toBeInstanceOf(ParticleResource::class)
        ->and($resource->key)->toBe('tags')
        ->and($resource->modelClass())->toBe(Tag::class);
});

it('is a no-op when no taxonomy model is bound', function () {
    config()->set('beam.taxonomy.models.tag', null);

    $registry = new ParticleResourceRegistry;
    app()->instance(ParticleResourceRegistry::class, $registry);

    $provider = new BeamTaxonomyServiceProvider(app());
    $provider->register();
    $provider->boot();

    expect(fn () => $registry->get('tags'))->toThrow(RuntimeException::class);
});

it('projects the configured silo declaration into Frame without replacing its model or shapes', function () {
    config()->set([
        'beam.taxonomy.models.silo' => TaxonomyHostSilo::class,
        'beam.taxonomy.data.silo' => SiloData::class,
        'beam.taxonomy.input.silo' => SiloInputData::class,
    ]);
    $registry = new ParticleResourceRegistry;
    app()->instance(ParticleResourceRegistry::class, $registry);
    $provider = new BeamTaxonomyServiceProvider(app());
    $provider->register();
    $provider->boot();

    $resource = $registry->get('silos');
    $definition = $registry->definition('silos');
    expect($resource->modelClass())->toBe(TaxonomyHostSilo::class)
        ->and($resource->data)->toBe(SiloData::class)
        ->and($resource->input)->toBe(SiloInputData::class)
        ->and($resource->includes)->toBe(['fragments'])
        ->and($definition->nav->label)->toBe('Silos')
        ->and($definition->model)->toBe(TaxonomyHostSilo::class)
        ->and($definition->data)->toBe(SiloData::class)
        ->and($definition->creatable)->toBeTrue()
        ->and($definition->editable)->toBeTrue();
});

// Integrator ruling c353ac01 / orch-lead 17:02Z: the silos registration DECLARES its row scope, the same cascade narrowing
// SiloQuery's base applies, so Beam's read guard admits the cascade's all-row reader (a host that mounts silos without
// SiloQuery, such as splicewire/www, refused its operator) and a member's rows do not change.
it('declares the silo row scope that SiloQuery applies', function () {
    config()->set([
        'beam.taxonomy.models.silo' => TaxonomyHostSilo::class,
        'beam.taxonomy.data.silo' => SiloData::class,
        'beam.taxonomy.input.silo' => SiloInputData::class,
    ]);
    $registry = new ParticleResourceRegistry;
    app()->instance(ParticleResourceRegistry::class, $registry);
    $provider = new BeamTaxonomyServiceProvider(app());
    $provider->register();
    $provider->boot();

    $resource = $registry->get('silos');
    expect(app(ResourceReadGuard::class)->declaresScope($resource))->toBeTrue();

    // This harness does not boot permission-cascade (its ReachResolver), so a plain policy takes RowAuthorization's
    // fail-closed branch on both sides: the comparison pins that the declaration and the base are one narrowing.
    Gate::policy(TaxonomyHostSilo::class, TaxonomyPlainSiloPolicy::class);
    $declared = ($resource->scope)(TaxonomyHostSilo::query(), null);
    expect($declared->toRawSql())->toBe(SiloQuery::rowScope(TaxonomyHostSilo::query())->toRawSql());
});

class TaxonomyHostSilo extends Silo {}

class TaxonomyPlainSiloPolicy {}
