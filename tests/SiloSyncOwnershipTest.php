<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravel\Scout\ScoutServiceProvider;
use Rushing\PermissionCascade\PermissionCascadeServiceProvider;
use Spatie\Sluggable\SluggableServiceProvider;
use Splicewire\Beam\Taxonomy\Models\Silo;
use Splicewire\Beam\Taxonomy\QueryBuilders\SiloQuery;

class SiloImportUser extends User
{
    protected $table = 'users';

    protected $guarded = [];
}

beforeEach(function () {
    config([
        'database.default' => 'testing',
        'database.connections.testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'permission-cascade.user_model' => SiloImportUser::class,
        'permission-cascade.grant_model' => null,
        'scout.driver' => null,
    ]);
    $this->app->register(PermissionCascadeServiceProvider::class);
    $this->app->register(ScoutServiceProvider::class);
    $this->app->register(SluggableServiceProvider::class);

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
    Schema::create('silos', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('slug');
        $table->uuid('parent_id')->nullable();
        $table->string('name_path')->nullable();
        $table->string('visibility')->nullable();
        $table->timestamps();
    });
    Schema::create('userables', function (Blueprint $table) {
        $table->unsignedBigInteger('user_id');
        $table->uuid('userable_id');
        $table->string('userable_type');
        $table->unique(['user_id', 'userable_id', 'userable_type']);
    });

    // Both members hold the same own-row entitlement; neither may list all silos.
    Gate::define('silo.own.view', fn (SiloImportUser $user) => true);
    $this->owner = SiloImportUser::create(['name' => 'Target owner']);
    $this->other = SiloImportUser::create(['name' => 'Unrelated member']);
    $this->payload = ['hash' => 'source-hash', 'name' => 'Imported silo', 'slug' => 'imported-silo', 'sourceParentId' => null];
});

it('lists an imported silo for the configured owner but not an unrelated member', function () {
    // Scaffold imports may run without a logged-in actor.
    $silo = Silo::fromSyncPayload($this->payload, ['user_id' => $this->owner->id]);

    $this->actingAs($this->owner);
    expect(SiloQuery::rowScope(Silo::query())->pluck('id')->all())->toBe([$silo->id]);

    $this->actingAs($this->other);
    expect(SiloQuery::rowScope(Silo::query())->pluck('id')->all())->toBe([])
        ->and($silo->fresh()->user()->pluck('users.id')->all())->toBe([$this->owner->id]);
});

it('replaces an existing owner with exactly the configured import owner', function () {
    $this->actingAs($this->other);
    $existing = Silo::fromSyncPayload($this->payload);
    expect($existing->fresh()->user()->pluck('users.id')->all())->toBe([$this->other->id]);

    $imported = Silo::fromSyncPayload($this->payload, ['user_id' => $this->owner->id]);
    expect($imported->id)->toBe($existing->id)
        ->and($imported->fresh()->user()->pluck('users.id')->all())->toBe([$this->owner->id]);
    expect(SiloQuery::rowScope(Silo::query())->pluck('id')->all())->toBe([]);
    $this->actingAs($this->owner);
    expect(SiloQuery::rowScope(Silo::query())->pluck('id')->all())->toBe([$imported->id]);
});

it('preserves existing ownership when import config has no user id', function () {
    $this->actingAs($this->owner);
    $silo = Silo::fromSyncPayload($this->payload);
    $this->actingAs($this->other);
    $imported = Silo::fromSyncPayload($this->payload);

    expect($imported->id)->toBe($silo->id)
        ->and($imported->fresh()->user()->pluck('users.id')->all())->toBe([$this->owner->id]);
});
