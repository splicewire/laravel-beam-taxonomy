<?php

declare(strict_types=1);

namespace Splicewire\Beam\Taxonomy\Models\Concerns;

use ArrayAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;

/**
 * HasTags
 * Modeled after (spatie/laravel-tags)[https://github.com/spatie/laravel-tags/blob/main/src/HasTags.php]
 *
 * Moved DOWN from the host's `App\Models\HasTags` into beam-taxonomy (tower-api-dissolution
 * issue 17 P2). The hardcoded `Tag::class` reference is gone: the concrete tag model is resolved
 * from `config('beam-taxonomy.models.tag')` via {@see static::tagModel()}, so a beam site that
 * rebinds its Tag model gets tagging over its own model with no code change.
 */
trait HasTags
{
    protected array $queuedTags = [];

    /**
     * The concrete, host-bound tag model class. Resolved from config so this concern never
     * hardcodes a model FQCN (the extraction's parameterization point).
     *
     * @return class-string
     */
    protected static function tagModel(): string
    {
        return config('beam-taxonomy.models.tag');
    }

    public static function bootHasTags()
    {
        static::created(function (Model $taggableModel) {
            if (count($taggableModel->queuedTags) === 0) {
                return;
            }

            $taggableModel->attachTags($taggableModel->queuedTags);

            $taggableModel->queuedTags = [];
        });

        static::deleted(function (Model $deletedModel) {
            $tags = $deletedModel->tags()->get();

            $deletedModel->detachTags($tags);
        });
    }

    /**
     * tags
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(static::tagModel(), 'taggable');
    }

    public function setTagsAttribute(string|array|ArrayAccess $tags)
    {
        if (! $this->exists) {
            $this->queuedTags = $tags;

            return;
        }

        $this->syncTags($tags);
    }

    public function scopeWithAllTags(
        Builder $query,
        string|array|ArrayAccess $tags,
        ?string $type = null,
    ): Builder {
        $tags = static::convertToTags($tags, $type);

        collect($tags)->each(function ($tag) use ($query) {
            $query->whereHas('tags', function (Builder $query) use ($tag) {
                $query->where('tags.id', $tag->id ?? 0);
            });
        });

        return $query;
    }

    public function scopeWithAnyTags(
        Builder $query,
        string|array|ArrayAccess $tags,
        ?string $type = null,
    ): Builder {
        $tags = static::convertToTags($tags, $type);

        return $query
            ->whereHas('tags', function (Builder $query) use ($tags) {
                $tagIds = collect($tags)->pluck('id');

                $query->whereIn('tags.id', $tagIds);
            });
    }

    public function scopeWithoutTags(
        Builder $query,
        string|array|ArrayAccess $tags,
        ?string $type = null
    ): Builder {
        $tags = static::convertToTags($tags, $type);

        return $query
            ->whereDoesntHave('tags', function (Builder $query) use ($tags) {
                $tagIds = collect($tags)->pluck('id');

                $query->whereIn('tags.id', $tagIds);
            });
    }

    public function attachTags(array|ArrayAccess $tags, ?string $type = null): static
    {
        $tags = static::convertToTags($tags, $type);

        $this->tags()->syncWithoutDetaching($tags->pluck('id')->toArray());

        return $this;
    }

    public function attachTag(string|Model $tag, ?string $type = null)
    {
        return $this->attachTags([$tag], $type);
    }

    public function detachTags(array|ArrayAccess $tags, ?string $type = null): static
    {
        $tags = static::convertToTags($tags, $type);

        collect($tags)
            ->filter()
            ->each(fn ($tag) => $this->tags()->detach($tag));

        return $this;
    }

    public function detachTag(string|Model $tag, ?string $type = null): static
    {
        return $this->detachTags([$tag], $type);
    }

    public function syncTags(string|array|ArrayAccess $tags): static
    {
        if (is_string($tags)) {
            $tags = Arr::wrap($tags);
        }

        $tagModel = static::tagModel();
        $tags = collect($tagModel::findOrCreate($tags));

        $this->tags()->sync($tags->pluck('id')->toArray());

        return $this;
    }

    protected static function convertToTags($values, string|bool|null $type = null)
    {
        $tagModel = static::tagModel();

        return $tagModel::convertToTags($values, $type);
    }
}
