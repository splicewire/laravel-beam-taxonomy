<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Splicewire\Beam\Facades\Particle;
use Splicewire\Beam\Taxonomy\Routing\TaxonomyRoutes;

it('exports tags through its package root without reimplementing the specialized silos', function () {
    $this->app->register(Splicewire\Beam\BeamServiceProvider::class);
    $this->app->register(Schemastud\DataSchemas\LaravelDataSchemasServiceProvider::class);
    Route::prefix('legacy')->name('legacy.')->middleware('host')->group(fn () => Particle::mount('tags')->only(['index']));
    Route::middleware('host')->group(fn () => TaxonomyRoutes::mount());
    $routes = app('router')->getRoutes();
    $old = $routes->match(Request::create('/legacy/tags'));
    $new = $routes->match(Request::create('/beam/taxonomy/tags'));
    expect($new->getName())->toBe('beam.taxonomy.tags.index')->and($old->getName())->toBe('legacy.tags.index');
    expect($new->getActionName())->toBe($old->getActionName())->and($new->gatherMiddleware())->toBe($old->gatherMiddleware());
    expect(collect($routes->getRoutes())->contains(fn ($r) => str_starts_with($r->uri(), 'beam/taxonomy/silos')))->toBeFalse();
    config(['beam.taxonomy.api_root' => 'custom/categories']);
    TaxonomyRoutes::mount();
    expect($routes->match(Request::create('/custom/categories/tags'))->getActionName())->toBe($old->getActionName());
});
