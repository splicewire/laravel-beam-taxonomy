<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Splicewire\Beam\Taxonomy\Models\Silo;
use Splicewire\Beam\Taxonomy\Models\Tag;

beforeEach(function () {
    config([
        'database.default' => 'testing',
        'database.connections.testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
    ]);
});

function taxonomySharedMigrations(): array
{
    return array_map(fn ($stem) => require __DIR__.'/../database/migrations/shared/'.$stem.'.php.stub', [
        'create_tags_table', 'create_silos_table',
    ]);
}

it('preserves the default physical schema exactly', function () {
    foreach (taxonomySharedMigrations() as $migration) {
        $migration->up();
    }

    $actual = array_map(fn ($row) => (array) $row, DB::select("SELECT type, name, tbl_name, sql FROM sqlite_master WHERE tbl_name IN ('tags','taggables','silos','siloables') ORDER BY type, name"));
    expect($actual)->toBe(json_decode(file_get_contents(__DIR__.'/Fixtures/default-taxonomy-schema.json'), true, flags: JSON_THROW_ON_ERROR));
});

it('creates and rolls back only the configured taxonomy tables', function () {
    foreach (['tags', 'taggables', 'silos', 'siloables'] as $name) {
        config(['beam.taxonomy.tables.'.$name => 'beam_'.$name]);
    }

    $migrations = taxonomySharedMigrations();
    foreach ($migrations as $migration) {
        $migration->up();
    }

    foreach (['tags', 'taggables', 'silos', 'siloables'] as $name) {
        expect(Schema::hasTable('beam_'.$name))->toBeTrue()
            ->and(Schema::hasTable($name))->toBeFalse();
    }
    expect((new Tag)->getTable())->toBe('beam_tags')
        ->and((new Silo)->getTable())->toBe('beam_silos');
    expect(Schema::getForeignKeys('beam_taggables')[0]['foreign_table'])->toBe('beam_tags')
        ->and(Schema::getForeignKeys('beam_siloables')[0]['foreign_table'])->toBe('beam_silos');

    foreach (array_reverse($migrations) as $migration) {
        $migration->down();
    }
    foreach (['tags', 'taggables', 'silos', 'siloables'] as $name) {
        expect(Schema::hasTable('beam_'.$name))->toBeFalse();
    }
});

class TaxonomyTableHost extends Illuminate\Database\Eloquent\Model
{
    use Splicewire\Beam\Taxonomy\Models\Concerns\HasSilos;
    use Splicewire\Beam\Taxonomy\Models\Concerns\HasTags;
    use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

    protected $table = 'hosts';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];
}

it('uses configured tables for model reads, morphs, recursive silos and tag filters', function () {
    foreach (['tags', 'taggables', 'silos', 'siloables'] as $name) {
        config(['beam.taxonomy.tables.'.$name => 'beam_'.$name]);
    }
    config(['beam.taxonomy.models.tag' => Tag::class]);
    foreach (taxonomySharedMigrations() as $migration) {
        $migration->up();
    }
    Schema::create('hosts', function (Illuminate\Database\Schema\Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('parent_id')->nullable();
    });
    $tagId = '00000000-0000-4000-8000-000000000001';
    $siloId = '00000000-0000-4000-8000-000000000002';
    $hostId = '00000000-0000-4000-8000-000000000003';
    DB::table('beam_tags')->insert(['id' => $tagId, 'name' => 'Shared', 'slug' => 'shared']);
    DB::table('beam_silos')->insert(['id' => $siloId, 'name' => 'Folder', 'slug' => 'folder']);
    $host = TaxonomyTableHost::create(['id' => $hostId]);
    $tag = Tag::findOrFail($tagId);
    $silo = Silo::findOrFail($siloId);
    $host->attachTag($tag);
    $host->silos()->attach($silo);

    expect($host->tags->modelKeys())->toBe([$tagId])
        ->and($host->silos->modelKeys())->toBe([$siloId])
        ->and($host->recursiveSilos->modelKeys())->toBe([$siloId])
        ->and(TaxonomyTableHost::withAllTags([$tag])->pluck('id')->all())->toBe([$hostId])
        ->and(TaxonomyTableHost::withAnyTags([$tag])->pluck('id')->all())->toBe([$hostId])
        ->and(TaxonomyTableHost::withoutTags([$tag])->count())->toBe(0);
    $host->detachTag($tag);
    expect($host->tags()->count())->toBe(0);
});

it('targets only configured tables when applying and rolling back provenance', function () {
    foreach (['tags', 'taggables', 'silos', 'siloables'] as $name) {
        config(['beam.taxonomy.tables.'.$name => 'beam_'.$name]);
    }
    foreach (taxonomySharedMigrations() as $migration) {
        $migration->up();
    }
    // A host's existing Spatie tables are outside this package's configured namespace.
    foreach (['tags', 'silos'] as $name) {
        Schema::create($name, fn (Illuminate\Database\Schema\Blueprint $table) => $table->id());
        DB::table($name)->insert(['id' => 7]);
    }
    $migration = require __DIR__.'/../database/migrations/tenant/add_external_ref_to_taxonomy_tables.php.stub';
    $migration->up();
    foreach (['tags', 'silos'] as $name) {
        expect(Schema::hasColumn('beam_'.$name, 'external_ref'))->toBeTrue()
            ->and(Schema::hasColumn($name, 'external_ref'))->toBeFalse()
            ->and(DB::table($name)->pluck('id')->all())->toBe([7]);
    }
    $migration->down();
    foreach (['tags', 'silos'] as $name) {
        expect(Schema::hasColumn('beam_'.$name, 'external_ref'))->toBeFalse()
            ->and(DB::table($name)->pluck('id')->all())->toBe([7]);
    }
});

class TaxonomyTableUser extends Illuminate\Foundation\Auth\User
{
    protected $table = 'users';

    protected $guarded = [];
}

it('creates native tags and silo ancestry beside a hosts existing integer tags', function () {
    foreach (['tags', 'taggables', 'silos', 'siloables'] as $name) {
        config(['beam.taxonomy.tables.'.$name => 'beam_'.$name]);
    }
    config([
        'beam.taxonomy.models.tag' => Tag::class,
        'permission-cascade.user_model' => TaxonomyTableUser::class,
        'permission-cascade.grant_model' => null,
        'scout.driver' => null,
    ]);
    $this->app->register(Rushing\PermissionCascade\PermissionCascadeServiceProvider::class);
    $this->app->register(Laravel\Scout\ScoutServiceProvider::class);
    $this->app->register(Spatie\Sluggable\SluggableServiceProvider::class);
    Schema::create('users', function (Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
    Schema::create('userables', function (Illuminate\Database\Schema\Blueprint $table) {
        $table->unsignedBigInteger('user_id');
        $table->uuid('userable_id');
        $table->string('userable_type');
        $table->unique(['user_id', 'userable_id', 'userable_type']);
    });
    Schema::create('tags', function (Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->json('name');
        $table->json('slug');
    });
    DB::table('tags')->insert(['id' => 7, 'name' => '{"en":"Host tag"}', 'slug' => '{"en":"host-tag"}']);
    $originalSchema = Schema::getColumns('tags');
    $originalRows = DB::table('tags')->get()->toArray();
    $this->actingAs(TaxonomyTableUser::create(['name' => 'Owner']));
    $migrations = taxonomySharedMigrations();
    foreach ($migrations as $migration) {
        $migration->up();
    }
    $tag = Tag::create(['name' => 'Beam tag']);
    $root = Silo::create(['name' => 'Root']);
    $child = Silo::create(['name' => 'Child', 'parent_id' => $root->id]);
    $child->attachTag($tag);

    expect($tag->slug)->toBe('beam-tag')
        ->and($tag->user()->get()->modelKeys())->toBe([auth()->id()])
        ->and($child->fresh()->name_path)->toBe('Root > Child')
        ->and($child->ancestors()->pluck('id')->all())->toBe([$root->id])
        ->and($child->tags->modelKeys())->toBe([$tag->id]);
    foreach (array_reverse($migrations) as $migration) {
        $migration->down();
    }
    expect(Schema::getColumns('tags'))->toBe($originalSchema)
        ->and(DB::table('tags')->get()->toArray())->toEqual($originalRows);
});
