<?php

namespace Splicewire\Beam\Taxonomy\Routing;

use Illuminate\Routing\Router;
use Splicewire\Beam\Facades\Particle;
use Splicewire\Beam\Routing\ApiRoot;

/** Tags only: Tower's SiloRoutes owns the specialized silo wire contract at this same root. */
final class TaxonomyRoutes
{
    public static function mount(): void
    {
        ApiRoot::mount(app(Router::class), 'beam.taxonomy.api_root', 'taxonomy', fn (Router $router) => $router->name('beam.taxonomy.')->group(fn () => Particle::mount('tags')->only(['index'])));
    }
}
