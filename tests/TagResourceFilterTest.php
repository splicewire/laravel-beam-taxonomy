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
 * `tags`/`tag` is the first resource converted off a hand-maintained
 * `config('data-filters.resources')` entry onto `#[ResourceFilter]` (data-filters' ADR-0008). These
 * pin what the conversion has to be equivalent to: two keys, one wiring, and a model nobody
 * restated.
 */
beforeEach(function () {
    app()->bind(ResourceModelResolver::class, ParticleResourceModelResolver::class);

    (new AttributedResourceFilterDiscovery(app(ResourceRegistry::class)))
        ->discover(classes: [TagFilterData::class]);
});

it('registers both the canonical key and its plural alias', function () {
    $registry = app(ResourceRegistry::class);

    expect($registry->has('tag'))->toBeTrue()
        ->and($registry->has('tags'))->toBeTrue();
});

it('resolves both keys to identical wiring', function () {
    $canonical = DataFilter::resource('tag');
    $plural = DataFilter::resource('tags');

    expect($plural->data)->toBe($canonical->data)
        ->and($plural->query)->toBe($canonical->query)
        ->and($plural->model)->toBe($canonical->model)
        ->and($canonical->data)->toBe(TagFilterData::class)
        ->and($canonical->query)->toBe(TagQuery::class);
});

it('resolves the model off the tag particle declaration, never restating it', function () {
    // Neither #[ResourceFilter] names a model; the ParticleResource this package registers does.
    // Asserted against the registry rather than a literal, because THAT is the claim: whatever the
    // particle declaration says the model is, is what both filter keys resolve to.
    $declared = app(ParticleResourceRegistry::class)->get('tag')->model;

    expect($declared)->not->toBeNull()
        ->and(DataFilter::resource('tag')->model)->toBe($declared)
        ->and(DataFilter::resource('tags')->model)->toBe($declared);
});

it('builds a working query for either key', function () {
    expect(DataFilter::query('tag'))->toBeInstanceOf(TagQuery::class)
        ->and(DataFilter::query('tags'))->toBeInstanceOf(TagQuery::class);
});
