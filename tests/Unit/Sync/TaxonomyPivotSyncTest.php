<?php

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Splicewire\Beam\Taxonomy\Models\Silo;
use Splicewire\Beam\Taxonomy\Models\Tag;
use Splicewire\Beam\Taxonomy\Sync\TaxonomyPivotSync;

trait RecordsPivotConversions
{
    public static array $conversionCalls = [];

    private static function resolveValues($values, bool $create): Collection
    {
        static::$conversionCalls[] = $create ? 'create' : 'convert';

        return collect($values)->map(fn ($value) => $value instanceof static
            ? $value
            : ($value === 'known' || $create ? (new static)->forceFill(['id' => $value === 'known' ? 'known-id' : 'created-id']) : null));
    }
}

class PivotSyncTag extends Tag
{
    use RecordsPivotConversions;

    public static function convertToTags($values, string|bool|null $type = null)
    {
        return static::resolveValues($values, false);
    }

    public static function convertOrCreateToTags($values, string|bool|null $type = null)
    {
        return static::resolveValues($values, true);
    }
}

class PivotSyncSilo extends Silo
{
    use RecordsPivotConversions;

    public static function convertToSilos($values)
    {
        return static::resolveValues($values, false);
    }

    public static function convertOrCreateToSilos($values)
    {
        return static::resolveValues($values, true);
    }
}

class PivotSyncSubject extends Model
{
    public array $tagCalls = [];

    public array $siloCalls = [];

    public function attachTags($tags): void
    {
        $this->tagCalls[] = collect($tags)->all();
    }

    public function silos(): object
    {
        return new class($this)
        {
            public function __construct(private PivotSyncSubject $subject) {}

            public function sync($ids): void
            {
                $this->subject->siloCalls[] = collect($ids)->all();
            }
        };
    }
}

beforeEach(function () {
    PivotSyncTag::$conversionCalls = [];
    PivotSyncSilo::$conversionCalls = [];
    config(['beam.taxonomy.models.tag' => PivotSyncTag::class, 'beam.taxonomy.models.silo' => PivotSyncSilo::class]);
    $this->pivotAuth = Mockery::mock(AuthFactory::class);
    $this->pivotGate = Mockery::mock(Gate::class);
    $this->sync = new TaxonomyPivotSync($this->pivotAuth, $this->pivotGate, app('config'));
    $this->subject = new PivotSyncSubject;
});

it('forwards only resolved configured models and ids on the convert-only path', function (string $arm, array $values, array $expected) {
    $input = (object) ['tags' => null, 'silos' => null];
    $input->{$arm} = $values;
    $this->sync->sync($this->subject, $input);

    if ($arm === 'tags') {
        $rows = $this->subject->tagCalls[0];
        foreach ($rows as $row) {
            expect($row)->toBeInstanceOf(PivotSyncTag::class);
        }
        expect(collect($rows)->pluck('id')->all())->toBe($expected)
            ->and(PivotSyncTag::$conversionCalls)->toBe(['convert']);
    } else {
        expect($this->subject->siloCalls)->toBe([$expected])
            ->and(PivotSyncSilo::$conversionCalls)->toBe(['convert']);
    }
})->with([
    'mixed tags' => ['tags', ['known', 'missing'], ['known-id']],
    'unknown tags' => ['tags', ['missing'], []],
    'mixed silos' => ['silos', ['known', 'missing'], ['known-id']],
    'unknown silos' => ['silos', ['missing'], []],
]);

it('leaves null arms untouched and forwards explicit empty arms', function () {
    $this->sync->sync($this->subject, (object) ['tags' => null, 'silos' => null]);
    expect($this->subject->tagCalls)->toBe([])->and($this->subject->siloCalls)->toBe([])
        ->and(PivotSyncTag::$conversionCalls)->toBe([])->and(PivotSyncSilo::$conversionCalls)->toBe([]);

    $this->sync->sync($this->subject, (object) ['tags' => [], 'silos' => []]);
    expect($this->subject->tagCalls)->toBe([[]])->and($this->subject->siloCalls)->toBe([[]]);
});

it('mints only when requested and the authenticated actor may create both configured models', function (string $authority, bool $permitted) {
    $guard = Mockery::mock(Guard::class);
    $this->pivotAuth->shouldReceive('guard')->twice()->andReturn($guard);
    $actor = $authority === 'guest' ? null : Mockery::mock(Authenticatable::class);
    $guard->shouldReceive('user')->twice()->andReturn($actor);
    if ($actor !== null) {
        $this->pivotGate->shouldReceive('forUser')->with($actor)->twice()->andReturnSelf();
        foreach ([PivotSyncTag::class, PivotSyncSilo::class] as $model) {
            $this->pivotGate->shouldReceive('allows')->with('create', $model)->once()->andReturn($permitted);
        }
    }
    $this->sync->sync($this->subject, (object) ['tags' => ['missing'], 'silos' => ['missing']], createMissing: true);
    $expected = $permitted ? ['created-id'] : [];
    expect(collect($this->subject->tagCalls[0])->pluck('id')->all())->toBe($expected)
        ->and($this->subject->siloCalls)->toBe([$expected])
        ->and(PivotSyncTag::$conversionCalls)->toBe([$permitted ? 'create' : 'convert'])
        ->and(PivotSyncSilo::$conversionCalls)->toBe([$permitted ? 'create' : 'convert']);
})->with(['permitted' => ['member', true], 'denied' => ['member', false], 'guest' => ['guest', false]]);
