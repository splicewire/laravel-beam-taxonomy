<?php

use Splicewire\Beam\Particle\ParticleResource;
use Splicewire\Beam\Particle\ParticleResourceRegistry;
use Splicewire\Beam\Taxonomy\BeamTaxonomyServiceProvider;
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
