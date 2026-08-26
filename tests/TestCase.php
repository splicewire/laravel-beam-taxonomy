<?php

namespace Splicewire\Beam\Taxonomy\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\DataFilters\ServiceProvider as DataFiltersServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelData\Mappers\CamelCaseMapper;
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

            // Testbench does NOT auto-discover: a package harness boots exactly what this
            // method names, while `src/` freely imports anything it can autoload. This package
            // requires `spatie/laravel-data` directly and its whole `src/Data` + `src/Sync` cone
            // extends `Spatie\LaravelData\Data`, but without this line `config('data')` is NULL
            // inside this suite and every `validateAndCreate()` dies with
            // `ErrorException: Trying to access array offset on null` — a FATAL, not a failure,
            // so the DTOs could never be asserted on at all. Measured here before the fix
            // (`config('data') === null` true; SiloInputData::validateAndCreate() fatalled).
            // api-surface-coherence tickets 84 / 85.
            LaravelDataServiceProvider::class,

            BeamTaxonomyServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Stand up beam-core's registry singleton and bind this test's fixture
        // model as the host would, so the provider's guarded registration fires.
        $app->singleton(ParticleResourceRegistry::class);
        $app['config']->set('beam.taxonomy.models.tag', Tag::class);
        $app['config']->set('beam.taxonomy.data.tag', null);

        // Booting LaravelDataServiceProvider alone would be a FALSE GREEN. The package ships
        // `name_mapping_strategy.input => null`, but the only host that runs this code
        // (`~/Herd/splicewire-app/config/data.php`) sets it to CamelCaseMapper — the one semantic
        // delta between the two files. A DTO hydrates fine under testbench defaults and can still
        // stop mapping under the host's mapper, so the harness mirrors the HOST, not the package
        // default. api-surface-coherence ticket 85.
        $app['config']->set('data.name_mapping_strategy.input', CamelCaseMapper::class);

        // Structure caching points at a path that does not exist under testbench, and a cached
        // reflection analysis carried across runs is exactly what a harness should not hold.
        $app['config']->set('data.structure_caching.enabled', false);
    }
}
