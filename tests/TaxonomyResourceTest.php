<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Rushing\PermissionCascade\Policies\BaseModelPolicy;
use Splicewire\Beam\Authorization\ResourceReadGuard;
use Splicewire\Beam\Particle\ParticleResource;
use Splicewire\Beam\Particle\ParticleResourceRegistry;
use Splicewire\Beam\Taxonomy\BeamTaxonomyServiceProvider;
use Splicewire\Beam\Taxonomy\Data\SiloData;
use Splicewire\Beam\Taxonomy\Data\SiloInputData;
use Splicewire\Beam\Taxonomy\Models\Silo;
use Splicewire\Beam\Taxonomy\QueryBuilders\SiloQuery;
use Splicewire\Beam\Taxonomy\QueryBuilders\TagQuery;
use Splicewire\Beam\Taxonomy\Tests\Fixtures\Tag;

it('registers the tag particle resource over the host-bound model', function () {
    $resource = app(ParticleResourceRegistry::class)->get('tags');

    expect($resource)->toBeInstanceOf(ParticleResource::class)
        ->and($resource->key)->toBe('tags')
        ->and($resource->modelClass())->toBe(Tag::class);
});

it('is a no-op when no taxonomy model is bound', function () {
    config()->set('beam.taxonomy.models.tag', null);

    $registry = new ParticleResourceRegistry;
    app()->instance(ParticleResourceRegistry::class, $registry);

    $provider = new BeamTaxonomyServiceProvider(app());
    $provider->register();
    $provider->boot();

    expect(fn () => $registry->get('tags'))->toThrow(RuntimeException::class);
});

it('projects the configured silo declaration into Frame without replacing its model or shapes', function () {
    config()->set([
        'beam.taxonomy.models.silo' => TaxonomyHostSilo::class,
        'beam.taxonomy.data.silo' => SiloData::class,
        'beam.taxonomy.input.silo' => SiloInputData::class,
    ]);
    $registry = new ParticleResourceRegistry;
    app()->instance(ParticleResourceRegistry::class, $registry);
    $provider = new BeamTaxonomyServiceProvider(app());
    $provider->register();
    $provider->boot();

    $resource = $registry->get('silos');
    $definition = $registry->definition('silos');
    expect($resource->modelClass())->toBe(TaxonomyHostSilo::class)
        ->and($resource->data)->toBe(SiloData::class)
        ->and($resource->input)->toBe(SiloInputData::class)
        ->and($resource->includes)->toBe(['fragments'])
        ->and($definition->nav->label)->toBe('Silos')
        ->and($definition->model)->toBe(TaxonomyHostSilo::class)
        ->and($definition->data)->toBe(SiloData::class)
        ->and($definition->creatable)->toBeTrue()
        ->and($definition->editable)->toBeTrue();
});

// Integrator ruling c353ac01 / orch-lead 17:02Z: the silos registration DECLARES its row scope, the same cascade narrowing
// SiloQuery's base applies, so Beam's read guard admits the cascade's all-row reader (a host that mounts silos without
// SiloQuery, such as splicewire/www, refused its operator) and a member's rows do not change.
it('declares the silo row scope that SiloQuery applies', function () {
    config()->set([
        'beam.taxonomy.models.silo' => TaxonomyHostSilo::class,
        'beam.taxonomy.data.silo' => SiloData::class,
        'beam.taxonomy.input.silo' => SiloInputData::class,
    ]);
    $registry = new ParticleResourceRegistry;
    app()->instance(ParticleResourceRegistry::class, $registry);
    $provider = new BeamTaxonomyServiceProvider(app());
    $provider->register();
    $provider->boot();

    $resource = $registry->get('silos');
    expect(app(ResourceReadGuard::class)->declaresScope($resource))->toBeTrue();

    // This harness does not boot permission-cascade (its ReachResolver), so a plain policy takes RowAuthorization's
    // fail-closed branch on both sides: the comparison pins that the declaration and the base are one narrowing.
    Gate::policy(TaxonomyHostSilo::class, TaxonomyPlainSiloPolicy::class);
    $declared = ($resource->scope)(TaxonomyHostSilo::query(), null);
    expect($declared->toRawSql())->toBe(SiloQuery::rowScope(TaxonomyHostSilo::query())->toRawSql());
});

// Addendum 04: tags already has model authority through its cascade policy, but its declaration did not carry the
// cascade's row boundary. Pin the missing conjunct to the same one-method seam used by silos.
it('declares the tag row scope that TagQuery applies', function () {
    config()->set('beam.taxonomy.models.tag', Splicewire\Beam\Taxonomy\Models\Tag::class);
    $registry = new ParticleResourceRegistry;
    app()->instance(ParticleResourceRegistry::class, $registry);
    $provider = new BeamTaxonomyServiceProvider(app());
    $provider->register();
    $provider->boot();
    $resource = $registry->get('tags');
    Gate::policy(Splicewire\Beam\Taxonomy\Models\Tag::class, TaxonomyMemberTagPolicy::class);

    expect(app(ResourceReadGuard::class)->policyBound($resource))->toBeTrue()
        ->and(app(ResourceReadGuard::class)->declaresScope($resource))->toBeTrue();

    $declared = ($resource->scope)(Splicewire\Beam\Taxonomy\Models\Tag::query(), null);

    expect($declared->toRawSql())->toBe(TagQuery::rowScope(Splicewire\Beam\Taxonomy\Models\Tag::query())->toRawSql());
});

it('keeps a member tag reader on the same row set after declaration', function () {
    $model = Splicewire\Beam\Taxonomy\Models\Tag::class;
    Schema::dropIfExists('tags');
    Schema::create('tags', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('slug');
        $table->string('type')->nullable();
        $table->timestamps();
    });
    DB::table('tags')->insert([
        ['id' => '10000000-0000-0000-0000-000000000001', 'name' => 'Alpha', 'slug' => 'alpha'],
        ['id' => '10000000-0000-0000-0000-000000000002', 'name' => 'Beta', 'slug' => 'beta'],
    ]);

    $this->actingAs(new User);

    config()->set('beam.taxonomy.models.tag', $model);
    $registry = new ParticleResourceRegistry;
    app()->instance(ParticleResourceRegistry::class, $registry);
    $provider = new BeamTaxonomyServiceProvider(app());
    $provider->register();
    $provider->boot();
    Gate::policy($model, TaxonomyMemberTagPolicy::class);
    $resource = $registry->get('tags');

    $existing = $model::query()->orderBy('name')->pluck('name')->all();
    $declared = ($resource->scope)($model::query(), null)->orderBy('name')->pluck('name')->all();

    expect($existing)->toBe(['Alpha', 'Beta'])
        ->and($declared)->toBe($existing);
});

class TaxonomyHostSilo extends Silo {}

class TaxonomyPlainSiloPolicy {}

class TaxonomyMemberTagPolicy extends BaseModelPolicy
{
    public function viewAny(Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        return true;
    }

    public function scopeForUser($query, $user = null)
    {
        return $query;
    }
}
