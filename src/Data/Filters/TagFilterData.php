<?php

namespace Splicewire\Beam\Taxonomy\Data\Filters;

use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Attributes\ResourceFilter;
use Rushing\DataFilters\Attributes\Sortable;
use Rushing\DataFilters\Operators\Exact;
use Rushing\DataFilters\Operators\IlikeMatch;
use Rushing\DataFilters\Operators\KeywordFilter;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Splicewire\Beam\Taxonomy\Models\Tag;
use Splicewire\Beam\Taxonomy\QueryBuilders\TagQuery;

/**
 * The first resource in the fleet to register itself through `#[ResourceFilter]` (data-filters'
 * ADR-0008) rather than through a hand-maintained `config('data-filters.resources')` entry — the
 * reference example for converting the remaining legacy-aliased resources.
 *
 * ONE key, `tag`, matching the `ParticleResource` this package registers. The app's static registry
 * used to carry a plural `tags` entry with a `tag => tags` alias, and the conversion could have
 * reproduced that pair as two declarations (repeatability is exactly how `#[ResourceFilter]`
 * expresses an alias). It doesn't, because nothing consumed the plural: the live path is
 * `DataFilter::query('tag')`, and `Route::particleResource('tags', 'tag', …)` is a plural URL path
 * over the singular key, not a second registry key. The frame list shell's plural-key convention is
 * real for other resources, but no list UI mounts a tags facets bar. A key kept only so a demo can
 * resolve it is the same dead wiring this effort exists to remove — the alias mechanism is proven in
 * data-filters' own suite instead.
 *
 * No `model:` — beam's resolver reads it off the `ParticleResource` registered under this same key,
 * so the model lives in exactly one place.
 */
#[ResourceFilter(key: 'tag', query: TagQuery::class)]
class TagFilterData extends Data
{
    public function __construct(
        #[Filterable(Exact::class)]
        public string|Optional|null $id = null,

        #[Filterable(Exact::class)]
        public string|Optional|null $slug = null,

        #[Filterable(IlikeMatch::class, mode: 'prefix')]
        #[Sortable]
        public string|Optional|null $name = null,

        #[Filterable(KeywordFilter::class, model: Tag::class)]
        public string|Optional|null $keywords = null,

        #[Sortable(name: 'createdAt', column: 'created_at')]
        public string|Optional|null $createdAt = null,

        #[Sortable(name: 'updatedAt', column: 'updated_at')]
        public string|Optional|null $updatedAt = null,
    ) {}
}
