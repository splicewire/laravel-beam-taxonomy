<?php

namespace Splicewire\Beam\Taxonomy\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Splicewire\Beam\Taxonomy\Resolution\SourceRef;

/**
 * Framework-free unit tests for the SourceRef value object — the authority-scoped
 * natural key. Pure logic, no DB / testbench (the package testbench can't install dev
 * deps in the co-dev tree). The DB-backed mint/dedup + scope-leak coverage runs in the
 * host suite; see the ticket-05 handback.
 */
class SourceRefTest extends TestCase
{
    public function test_composes_authority_scoped_external_ref(): void
    {
        $ref = new SourceRef('federation:tenant-acme', 'Legal > Contracts');

        $this->assertSame('federation:tenant-acme::Legal > Contracts', $ref->externalRef());
        $this->assertSame('federation:tenant-acme', $ref->authority);
        $this->assertSame('Legal > Contracts', $ref->naturalKey);
    }

    public function test_same_authority_and_key_produce_the_same_external_ref(): void
    {
        $a = new SourceRef('federation:acme', 'Contracts');
        $b = SourceRef::make('federation:acme', 'Contracts');

        $this->assertSame($a->externalRef(), $b->externalRef());
    }

    public function test_different_authorities_with_the_same_key_produce_distinct_external_refs(): void
    {
        // THE SCOPE-LEAK GUARD at the addressing layer: same name_path, different source →
        // different external_ref, so they can never dedup into one row.
        $foreign = new SourceRef('federation:acme', 'Legal > Contracts');
        $local = new SourceRef('local', 'Legal > Contracts');

        $this->assertNotSame($foreign->externalRef(), $local->externalRef());
    }

    public function test_missing_authority_is_illegal(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SourceRef('', 'Legal > Contracts');
    }

    public function test_blank_authority_is_illegal(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SourceRef('   ', 'Legal > Contracts');
    }

    public function test_empty_natural_key_is_illegal(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SourceRef('federation:acme', '');
    }

    public function test_is_local_reflects_the_declared_authority(): void
    {
        $this->assertTrue((new SourceRef('local', 'x'))->isLocal('local'));
        $this->assertFalse((new SourceRef('federation:acme', 'x'))->isLocal('local'));
    }
}
