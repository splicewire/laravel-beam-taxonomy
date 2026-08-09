<?php

namespace Splicewire\Beam\Taxonomy\Doctor;

use Splicewire\Beam\Doctor\Support\StubMigrationsAudit;
use Splicewire\Beam\Taxonomy\BeamTaxonomyServiceProvider;

class BeamTaxonomyMigrationsAudit extends StubMigrationsAudit
{
    protected function packageName(): string
    {
        return 'splicewire/laravel-beam-taxonomy';
    }

    protected function serviceProviderClass(): string
    {
        return BeamTaxonomyServiceProvider::class;
    }
}
