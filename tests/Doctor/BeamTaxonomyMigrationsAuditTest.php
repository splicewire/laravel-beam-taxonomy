<?php

namespace Splicewire\Beam\Taxonomy\Tests\Doctor;

use Splicewire\Beam\Doctor\Support\StubMigrationsAudit;
use Splicewire\Beam\Doctor\Testing\AssertsStubMigrations;
use Splicewire\Beam\Taxonomy\Doctor\BeamTaxonomyMigrationsAudit;
use Splicewire\Beam\Taxonomy\Tests\TestCase;

/**
 * beam-taxonomy's own operator check: its migrations must stay publish-only .stub files. Mirrors
 * beam-core's `BeamCoreMigrationsAuditTest` shape — a thin test wrapping the shared
 * {@see StubMigrationsAudit} engine, declaring only "which audit is mine."
 */
class BeamTaxonomyMigrationsAuditTest extends TestCase
{
    use AssertsStubMigrations;

    public function test_beam_taxonomy_migrations_are_publish_only_stubs(): void
    {
        $this->assertMigrationsArePublishOnlyStubs();
    }

    protected function stubMigrationsAuditClass(): string
    {
        return BeamTaxonomyMigrationsAudit::class;
    }
}
