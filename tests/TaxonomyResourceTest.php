<?php

use Splicewire\Beam\Particle\ParticleResource;
use Splicewire\Beam\Particle\ParticleResourceRegistry;
use Splicewire\Beam\Taxonomy\Tests\Fixtures\Tag;

it('registers the tag particle resource over the host-bound model', function () {
    $resource = app(ParticleResourceRegistry::class)->get('tag');

    expect($resource)->toBeInstanceOf(ParticleResource::class)
        ->and($resource->key)->toBe('tag')
        ->and($resource->model)->toBe(Tag::class);
});

it('is a no-op when no taxonomy model is bound', function () {
    config()->set('beam-taxonomy.models.tag', null);

    $registry = new ParticleResourceRegistry;
    app()->instance(ParticleResourceRegistry::class, $registry);

    (new Splicewire\Beam\Taxonomy\BeamTaxonomyServiceProvider(app()))->boot();

    expect(fn () => $registry->get('tag'))->toThrow(RuntimeException::class);
});
