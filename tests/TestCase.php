<?php

namespace Splicewire\Beam\Taxonomy\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\DataFilters\ServiceProvider as DataFiltersServiceProvider;
use Spatie\QueryBuilder\QueryBuilderServiceProvider;
use Splicewire\Beam\Particle\ParticleResourceRegistry;
use Splicewire\Beam\Taxonomy\BeamTaxonomyServiceProvider;
use Splicewire\Beam\Taxonomy\Tests\Fixtures\Tag;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            // data-filters, so the Resource Registry the package's `#[ResourceFilter]`
            // declarations register into actually exists under test.
            QueryBuilderServiceProvider::class,
            DataFiltersServiceProvider::class,
            BeamTaxonomyServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Stand up beam-core's registry singleton and bind this test's fixture
        // model as the host would, so the provider's guarded registration fires.
        $app->singleton(ParticleResourceRegistry::class);
        $app['config']->set('beam-taxonomy.models.tag', Tag::class);
        $app['config']->set('beam-taxonomy.data.tag', null);
    }
}
