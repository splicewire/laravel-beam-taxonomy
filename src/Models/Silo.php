<?php

namespace Splicewire\Beam\Taxonomy\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;
use Rushing\PermissionCascade\Attributes\UseCascadePolicy;
use Rushing\PermissionCascade\Concerns\HasVisibility;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Splicewire\Beam\Taxonomy\Data\SiloData;
use Splicewire\Beam\Taxonomy\Models\Concerns\HasTags;
use Splicewire\Beam\Taxonomy\Models\Concerns\HasUser;
use Splicewire\Beam\Taxonomy\Sync\SiloSyncData;
use Splicewire\Sync\Contracts\SchemaVersionedSyncable;
use Splicewire\Sync\Contracts\SyncLineageResolver;
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
 * ONE host-coupled band is wired onto this model at runtime by the app, so this foundation never
 * reaches UP in SOURCE: the fragment relations (`fragments`, `recursiveFragments`) which target the
 * tower-core `Fragment`. As of issue 17 P3b the `App\Models\Silo` subclass is gone — the app attaches
 * those relations to this beam model via `resolveRelationUsing` in `AppServiceProvider::boot()` (it
 * sits above tower-core, so wiring the host `Fragment` relation from there keeps this SOURCE clean).
 * They are irreducible because the silo data-filter `#[Includable]` surface + `ParticleResource`
 * `includes` eager-load them by name; folding them down awaits the deeper include-surface rehome
 * (follow-on issue 19, design U4).
 */
#[UseCascadePolicy]
class Silo extends Model implements SchemaVersionedSyncable
{
    use HasFactory;
    use HasRecursiveRelationships;
    use HasSlug;
    use HasTags;
    use HasUser;
    use HasUuids;
    use HasVisibility;
    use Searchable;

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
        // Provenance for foreign-only shadow silos (sourced-particles ticket 05). A silo is a
        // governed ACL container, so a foreign silo MUST carry its source authority + external_ref
        // and resolve through AuthorityAwareResolver — never auto-merge into a local silo by
        // name_path (that is a cross-tenant scope leak). Mirrors Fragment.external_ref.
        'external_ref',
        'source_authority',
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

    /**
     * Slug generation, on spatie (api-surface-coherence 19 / ticket 03 §9 — the estate carried two slug
     * libraries and converges on one). A **translation, not a change**: cviebrock declared
     * `'unique' => false`, so `allowDuplicateSlugs()` preserves it — two silos may share a slug, because a
     * Silo is addressed by its `slug_path` ({@see getCustomPaths()}, the adjacency-list ancestry), never by
     * `slug` alone. `doNotGenerateSlugsOnUpdate()` matches cviebrock's stable-once-set default, so a
     * renamed silo keeps its path instead of silently re-slugging every descendant.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->allowDuplicateSlugs()
            ->doNotGenerateSlugsOnUpdate();
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
            schemaId: static::syncSchemaRecordType()::schemaName().'/'.static::syncSchemaRecordType()::schemaVersion(),
        );
    }

    /**
     * The canonical versioned schema DTO for a silo — the reconcile record type the
     * tenant-sync-in bridge migrates an incoming payload against (issue 19).
     */
    public static function syncSchemaRecordType(): string
    {
        return SiloData::class;
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
