<?php

declare(strict_types=1);

namespace Splicewire\Beam\Taxonomy\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Splicewire\Beam\Particle\ParticleResourceRegistry;
use Splicewire\Beam\Taxonomy\BeamTaxonomyServiceProvider;
use Splicewire\Beam\Taxonomy\Tests\Fixtures\Tag;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [BeamTaxonomyServiceProvider::class];
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
