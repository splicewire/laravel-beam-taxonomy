<?php

declare(strict_types=1);

namespace Splicewire\Beam\Taxonomy\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use eloquentFilter\QueryFilter\ModelFilters\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;
use Rushing\PermissionCascade\Concerns\HasVisibility;
use Splicewire\Beam\Taxonomy\Models\Concerns\HasTags;
use Splicewire\Beam\Taxonomy\Models\Concerns\HasUser;
use Splicewire\Beam\Taxonomy\Sync\SiloSyncData;
use Splicewire\Sync\Contracts\SyncLineageResolver;
use Splicewire\Sync\Contracts\Syncable;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;
use Symfony\Component\Uid\Uuid;

/**
 * BeamSilo — the reusable hierarchical taxonomy folder for beam sites.
 *
 * Extracted DOWN from the host's `App\Models\Silo` into beam-taxonomy (tower-api-dissolution
 * issue 17 P2). This base carries the PORTABLE core: uuid + slug + adjacency-list nesting +
 * visibility cascade + name-path + golden-eval markers + the string/id resolution helpers.
 *
 * Since U4a the tenant-sync surface (`Splicewire\Sync\Syncable` + `toSyncData`/`fromSyncPayload`)
 * is implemented HERE natively, over the relocated beam {@see SiloSyncData} — using only
 * beam-taxonomy + beam-sync + foundation types. The one FK a synced silo carries (its parent) is
 * remapped through the {@see SyncLineageResolver} PORT passed in `$config`, so this base never
 * names the tower-tenancy `TenantSyncLineage` store; the tenant TARGET supplies the concrete
 * resolver. `syncDependencies` resolves the parent against `static::class` (the runtime class), so
 * a polymorphic apply on the App subclass keys lineage on the same class it always did.
 *
 * ONE host-coupled band stays on the `App\Models\Silo` subclass in tower-core, so this foundation
 * never reaches UP: the tower relations (`fragments`, `recursiveFragments`, `concepts`, `rules`,
 * `evidence`) which target tower-core models. The deeper Particle-pipeline sync-in bridge is
 * deferred to follow-on issue 19 (design U4).
 */
class Silo extends Model implements Syncable
{
    use Filterable;
    use HasFactory;
    use HasRecursiveRelationships;
    use HasTags;
    use HasUser;
    use HasUuids;
    use HasVisibility;
    use Searchable;
    use Sluggable;

    public function searchableAs(): string
    {
        $tenantId = function_exists('tenant') ? (tenant()?->id ?? 'central') : 'central';

        return "{$tenantId}_silos";
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'name',
        'slug',
        'parent_id',
        'visibility',
        'created_at',
        'updated_at',
    ];

    /**
     * Feed the directory-ACL resolution this silo's ancestors, nearest-first (parent,
     * grandparent, …). The package's HasVisibility stays topology-agnostic; the containment
     * walk lives here, over the staudenmeir adjacency-list. Ancestor `depth` is negative going
     * up, so descending depth yields nearest-first.
     */
    public function visibilityAncestors(): iterable
    {
        if ($this->parent_id === null) {
            return [];
        }

        return $this->ancestors()->orderByDesc('depth')->get()->all();
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
                'unique' => false,
            ],
        ];
    }

    public function getCustomPaths()
    {
        return [
            [
                'name' => 'slug_path',
                'column' => 'slug',
                'separator' => ' > ',
            ],
            [
                'name' => '_name_path',
                'column' => 'name',
                'separator' => ' > ',
            ],
        ];
    }

    public static function boot()
    {
        parent::boot();

        static::saving(function (Model $silo) {
            if ($silo->parent_id) {
                $silo->name_path = $silo->getNamePath();
            } else {
                $silo->name_path = $silo->name;
            }
        });
    }

    public function getNamePath()
    {
        return $this->ancestors()->orderBy('depth')->pluck('name')->push($this->name)->join(' > ');
    }

    private static $whiteListFilter = ['*'];

    public function scopeWhereName(Builder $query, $name)
    {
        $query->addSelect(DB::raw('levenshtein(tags.name, '.DB::escape($name).') as name_diff'));
    }

    public static function findFromString($name)
    {
        return static::where('name_path', 'like', $name)->first();
    }

    public static function findOrCreateFromString($name)
    {
        $silo = static::findFromString($name);
        if ($silo) {
            return $silo;
        }

        // Split it into levels and create it
        $parent = null;
        $levelNames = str($name)->split('/\s*>\s*/');
        foreach ($levelNames as $i => $levelName) {
            // Get existing
            $levelPath = $levelNames->take($i + 1)->join(' > ');
            $silo = static::findFromString($levelPath);

            // Create new one
            if (! $silo) {
                $silo = static::make([
                    'parent_id' => $parent?->id,
                    'name' => $levelName,
                ]);
                $silo->save();
            }
            $parent = $silo;
        }

        return $silo;
    }

    public static function convertToSilos($values)
    {
        if ($values instanceof static) {
            $values = [$values];
        }

        return collect($values)->map(function ($value) {
            if (is_array($value) && data_get($value, 'id')) {
                $tag = static::make($value);
                $tag->exists = true;

                return $tag;
            }
            if (Uuid::isValid($value)) {
                return static::find($value);
            }

            return static::findFromString($value) ?? static::where('slug', $value)->first();
        });
    }

    public static function convertOrCreateToSilos($values)
    {
        if ($values instanceof static) {
            $values = [$values];
        }

        return collect($values)->map(function ($value) {
            if (is_array($value) && data_get($value, 'id')) {
                $tag = static::make($value);
                $tag->exists = true;

                return $tag;
            }
            // A bare id refers to an existing silo — resolve it, don't mint a
            // junk silo named after the UUID. The hybrid fragment editor's
            // silo tree-select sends chosen silos by id.
            if (Uuid::isValid($value)) {
                return static::find($value);
            }

            return static::findOrCreateFromString($value);
        });
    }

    // -------------------------------------------------------------------------
    // Golden-eval marker (eval-golden-packs-and-legibility ticket 03, step 1)
    //
    // Every golden-eval silo carries the reserved `rag-eval-` slug prefix; the vertical is
    // the slug suffix (`rag-eval-food-safety` → `food-safety`). This durable marker is the
    // ONE identification point for "is this owned golden scaffolding vs living corpus?" — if a
    // non-golden silo ever collides with the prefix, swap these for an explicit boolean column
    // and nothing else changes.
    // -------------------------------------------------------------------------
    public const GOLDEN_SLUG_PREFIX = 'rag-eval-';

    /** Golden-eval silos only (reserved slug prefix). */
    public function scopeWhereGolden(Builder $query): void
    {
        $query->where('slug', 'like', static::GOLDEN_SLUG_PREFIX.'%');
    }

    /** Everything BUT golden-eval silos — the living corpus + ordinary scaffolding. */
    public function scopeWhereNotGolden(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->whereNull('slug')->orWhere('slug', 'not like', static::GOLDEN_SLUG_PREFIX.'%');
        });
    }

    public function isGolden(): bool
    {
        return $this->slug !== null && str_starts_with($this->slug, static::GOLDEN_SLUG_PREFIX);
    }

    /** The vertical this golden silo carries (slug suffix), or null if it isn't a golden silo. */
    public function goldenVertical(): ?string
    {
        return $this->isGolden()
            ? substr($this->slug, strlen(static::GOLDEN_SLUG_PREFIX))
            : null;
    }

    /** The reserved golden slug for a vertical (`food-safety` → `rag-eval-food-safety`). */
    public static function goldenSlugFor(string $vertical): string
    {
        return static::GOLDEN_SLUG_PREFIX.$vertical;
    }

    // -------------------------------------------------------------------------
    // Syncable (native — relocated DOWN from App\Models\Silo in issue 17 U4a).
    // The parent FK is remapped through the SyncLineageResolver port from $config,
    // so this base never names the tower-tenancy lineage store.
    // -------------------------------------------------------------------------

    public function toSyncPayload(): array
    {
        return $this->toSyncData()->toArray();
    }

    public function toSyncData(): SiloSyncData
    {
        return new SiloSyncData(
            hash: $this->syncHash(),
            name: $this->name,
            slug: $this->slug,
            sourceParentId: $this->parent_id,
        );
    }

    public static function fromSyncPayload(array $data, array $config = []): static
    {
        $payload = SiloSyncData::from($data);

        // Remap the source parent id to its target-local id through the lineage resolver the
        // target supplied (a tenant target keys this on TenantSyncLineage). Absent a resolver
        // (headless / no-remap apply), the parent is left unresolved.
        $parentId = null;
        if ($payload->sourceParentId) {
            $resolver = $config[SyncLineageResolver::CONFIG_KEY] ?? null;
            if ($resolver instanceof SyncLineageResolver) {
                $parentId = $resolver->resolveTargetId(static::class, $payload->sourceParentId);
            } elseif (is_callable($resolver)) {
                $parentId = $resolver(static::class, $payload->sourceParentId);
            }
        }

        return static::updateOrCreate(
            ['slug' => $payload->slug],
            [
                'name' => $payload->name,
                'parent_id' => $parentId,
            ]
        );
    }

    public function syncHash(): string
    {
        return md5($this->name.'|'.$this->slug);
    }

    public function syncDependencies(): array
    {
        return $this->parent_id
            ? [static::class => [$this->parent_id]]
            : [];
    }
}
