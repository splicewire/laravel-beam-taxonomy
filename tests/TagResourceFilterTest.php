<?php

use Rushing\DataFilters\Contracts\ResourceModelResolver;
use Rushing\DataFilters\Discovery\AttributedResourceFilterDiscovery;
use Rushing\DataFilters\Facades\DataFilter;
use Rushing\DataFilters\Registry\ResourceRegistry;
use Splicewire\Beam\Particle\ParticleResourceModelResolver;
use Splicewire\Beam\Particle\ParticleResourceRegistry;
use Splicewire\Beam\Taxonomy\Data\Filters\TagFilterData;
use Splicewire\Beam\Taxonomy\QueryBuilders\TagQuery;

/**
 * `tag` is the first resource converted off a hand-maintained
 * `config('data-filters.resources')` entry onto `#[ResourceFilter]` (data-filters' ADR-0008). These
 * pin what the conversion has to be equivalent to: the same wiring the static entry supplied, and a
 * model nobody restated.
 */
beforeEach(function () {
    app()->bind(ResourceModelResolver::class, ParticleResourceModelResolver::class);

    (new AttributedResourceFilterDiscovery(app(ResourceRegistry::class)))
        ->discover(classes: [TagFilterData::class]);
});

it('registers the tag resource from its own declaration', function () {
    expect(app(ResourceRegistry::class)->has('tags'))->toBeTrue();

    $definition = DataFilter::resource('tags');

    expect($definition->data)->toBe(TagFilterData::class)
        ->and($definition->query)->toBe(TagQuery::class);
});

it('resolves the model off the tag particle declaration, never restating it', function () {
    // The #[ResourceFilter] names no model; the ParticleResource this package registers does.
    // Asserted against the registry rather than a literal, because THAT is the claim: whatever the
    // particle declaration says the model is, is what the filter key resolves to.
    $declared = app(ParticleResourceRegistry::class)->get('tags')->model;

    expect($declared)->not->toBeNull()
        ->and(DataFilter::resource('tags')->model)->toBe($declared);
});

it('builds a working query', function () {
    expect(DataFilter::query('tags'))->toBeInstanceOf(TagQuery::class);
});

it('registers no plural alias — nothing consumes one', function () {
    expect(app(ResourceRegistry::class)->has('tags'))->toBeFalse();
});
