<?php

namespace Splicewire\Beam\Taxonomy\Models;

use ArrayAccess;
use Cviebrock\EloquentSluggable\Sluggable;
use eloquentFilter\QueryFilter\ModelFilters\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;
use Splicewire\Beam\Taxonomy\Data\TagData;
use Splicewire\Beam\Taxonomy\Models\Concerns\HasUser;
use Splicewire\Beam\Taxonomy\Sync\TagSyncData;
use Splicewire\Sync\Contracts\SchemaVersionedSyncable;
use Symfony\Component\Uid\Uuid;

/**
 * BeamTag — the reusable tag model for beam sites.
 *
 * Modeled after (spatie/laravel-tags)[https://github.com/spatie/laravel-tags/blob/main/src/Tag.php].
 *
 * Extracted DOWN from the host's `App\Models\Tag` into beam-taxonomy (tower-api-dissolution
 * issue 17 P2). Since U4a the tenant-sync surface (`Splicewire\Sync\Syncable` +
 * `toSyncData`/`fromSyncPayload`) is implemented HERE natively, over the relocated beam
 * {@see TagSyncData} — using only beam-taxonomy + beam-sync + foundation types, never reaching UP
 * into tower. A tag has no synced FKs, so its apply is a plain slug/type upsert. The
 * tenant-specific TARGET (`Splicewire\Tenancy\Sync\TenantSyncTarget`) drives this polymorphically;
 * the deeper Particle-pipeline sync-in bridge is deferred to follow-on issue 19.
 */
class Tag extends Model implements SchemaVersionedSyncable
{
    use Filterable;
    use HasFactory;
    use HasUser;
    use HasUuids;
    use Searchable;
    use Sluggable;

    public function searchableAs(): string
    {
        $tenantId = function_exists('tenant') ? (tenant()?->id ?? 'central') : 'central';

        return "{$tenantId}_tags";
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
        'type',
        'created_at',
        'updated_at',
    ];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    private static $whiteListFilter = ['*'];

    public function scopeWithNameDiff(Builder $query, string $name)
    {
        $query->withStringDiff('name', $name);
    }

    public static function findFromString(string $name, string|bool|null $type = null)
    {
        $query = static::query();
        $query->where(fn ($q) => $q->where('name', 'ilike', $name)
            ->orWhere('slug', 'ilike', $name)
        );
        if ($type !== true) {
            $query->where('type', $type);
        }

        return $query->first();
    }

    public static function findOrCreate(
        string|array|ArrayAccess $values,
        string|bool|null $type = null
    ): Collection|static {
        $tags = collect($values)->map(function ($value) use ($type) {
            if ($value instanceof self) {
                return $value;
            } elseif (is_array($value) && data_get($value, 'name')) {
                $value = data_get($value, 'name');
            }

            return static::findOrCreateFromString($value, $type);
        });

        return is_string($values) ? $tags->first() : $tags;
    }

    protected static function findOrCreateFromString(string $name, ?string $type = null)
    {
        $tag = static::findFromString($name, $type);

        if (! $tag) {
            $tag = static::create([
                'name' => $name,
                'type' => $type,
            ]);
        }

        return $tag;
    }

    public static function convertToTags($values, string|bool|null $type = null)
    {
        if ($values instanceof static) {
            $values = [$values];
        }

        return collect($values)->map(function ($value) use ($type) {
            if ($value instanceof static) {
                if ($type === true || $value->type == $type) {
                    return $value;
                }
            } elseif (is_array($value) && data_get($value, 'id')) {
                $tag = static::make($value);
                $tag->exists = true;

                return $tag;
            }

            return Uuid::isValid($value) ? static::where('type', $type)->find($value) : static::findFromString($value, $type);
        });
    }

    public static function convertOrCreateToTags($values, string|bool|null $type = null)
    {
        if ($values instanceof static) {
            $values = [$values];
        }

        return collect($values)->map(function ($value) use ($type) {
            if ($value instanceof static) {
                if ($type === true || $value->type == $type) {
                    return $value;
                }
            } elseif (is_array($value) && data_get($value, 'id')) {
                $tag = static::make($value);
                $tag->exists = true;

                return $tag;
            } elseif (is_array($value) && data_get($value, 'name')) {
                return static::findOrCreateFromString(data_get($value, 'name'), $type);
            }

            return static::findOrCreateFromString($value, $type);
        });
    }

    // -------------------------------------------------------------------------
    // Syncable (native — relocated DOWN from App\Models\Tag in issue 17 U4a).
    // Uses only beam-taxonomy/beam-sync/foundation types; a tag carries no synced FKs.
    // -------------------------------------------------------------------------

    public function toSyncPayload(): array
    {
        return $this->toSyncData()->toArray();
    }

    public function toSyncData(): TagSyncData
    {
        return new TagSyncData(
            hash: $this->syncHash(),
            name: $this->name,
            slug: $this->slug,
            type: $this->type,
            schemaId: static::syncSchemaRecordType()::schemaName().'/'.static::syncSchemaRecordType()::schemaVersion(),
        );
    }

    /**
     * The canonical versioned schema DTO for a tag — the reconcile record type the
     * tenant-sync-in bridge migrates an incoming payload against (issue 19).
     */
    public static function syncSchemaRecordType(): string
    {
        return TagData::class;
    }

    public static function fromSyncPayload(array $data, array $config = []): static
    {
        $payload = TagSyncData::from($data);

        return static::updateOrCreate(
            ['slug' => $payload->slug, 'type' => $payload->type],
            ['name' => $payload->name]
        );
    }

    public function syncHash(): string
    {
        return md5($this->name.'|'.$this->slug.'|'.$this->type);
    }

    public function syncDependencies(): array
    {
        return [];
    }
}
