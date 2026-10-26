<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Schema;
use Laravel\Scout\ScoutServiceProvider;
use Rushing\PermissionCascade\PermissionCascadeServiceProvider;
use Spatie\Sluggable\SluggableServiceProvider;
use Splicewire\Beam\Taxonomy\Models\Silo;
use Splicewire\Beam\Taxonomy\Models\Tag;

/*
 * EP-42 (2026-10-26): spatie/laravel-sluggable 4.0.5 gave HasSlug a resolveRouteBindingQuery, which laravel/framework
 * 13.38's HasUuids also defines. Silo uses both traits, so with those releases the class did not load at all (a PHP
 * fatal at package:discover on every fresh install). Silo binds by its uuid key, never by slug (no route names a slug
 * field, and its getRouteKeyName is the key), and its slug options leave self-healing URLs off, so HasSlug's version
 * would only defer to the parent. Silo takes HasUuids' version: a malformed key is a 404, not a query.
 */
class SiloRouteBindingUser extends User
{
    protected $table = 'users';

    protected $guarded = [];
}

beforeEach(function () {
    config([
        'database.default' => 'testing',
        'database.connections.testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'permission-cascade.user_model' => SiloRouteBindingUser::class,
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

    $this->actingAs(SiloRouteBindingUser::create(['name' => 'Owner']));
    $this->silo = Silo::fromSyncPayload(['hash' => 'h', 'name' => 'Field notes', 'slug' => 'field-notes', 'sourceParentId' => null]);
});

it('binds a route by its uuid key', function () {
    expect((new Silo)->resolveRouteBinding($this->silo->id)?->id)->toBe($this->silo->id);
});

it('answers a malformed key as not found, before any query', function () {
    expect(fn () => (new Silo)->resolveRouteBinding('not-a-uuid'))->toThrow(ModelNotFoundException::class);
});

it('still binds by slug where a route names the slug field', function () {
    expect((new Silo)->resolveRouteBinding('field-notes', 'slug')?->id)->toBe($this->silo->id);
});

it('makes the same choice on Tag, which uses both traits too', function () {
    expect(fn () => (new Tag)->resolveRouteBinding('not-a-uuid'))->toThrow(ModelNotFoundException::class);
});
