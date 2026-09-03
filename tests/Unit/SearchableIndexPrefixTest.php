<?php

use Splicewire\Beam\Taxonomy\Models\Silo;
use Splicewire\Beam\Taxonomy\Models\Tag;

/*
 * splicewire-app production-relaunch issue 09. Scout applies `config('scout.prefix')` in exactly one
 * place — the `Searchable` trait's own `searchableAs()` (vendor/laravel/scout/src/Searchable.php:385).
 * Both models here OVERRIDE that method to produce a per-tenant index name, and an override drops the
 * prefix SILENTLY: the index is simply created unprefixed and nothing errors, so SCOUT_PREFIX reads as
 * a set config key with no consumer.
 *
 * That matters because the flagship deploys onto a box whose Meilisearch is shared with another
 * application, where SCOUT_PREFIX is the entire namespacing rule (production-relaunch PLAN.md, "The
 * shared-infra rule"). These tests are the consumer check for that key.
 */

it('prefixes the per-tenant index with scout.prefix', function () {
    config()->set('scout.prefix', 'splicewire_');

    expect((new Tag)->searchableAs())->toBe('splicewire_central_tags')
        ->and((new Silo)->searchableAs())->toBe('splicewire_central_silos');
});

it('is a no-op when no prefix is configured, so an unprefixed estate keeps its index names', function () {
    config()->set('scout.prefix', '');

    expect((new Tag)->searchableAs())->toBe('central_tags')
        ->and((new Silo)->searchableAs())->toBe('central_silos');
});
