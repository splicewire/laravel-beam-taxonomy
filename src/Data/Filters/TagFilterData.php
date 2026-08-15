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
 * Two declarations, because the attribute is repeatable and that is how an alias key is expressed:
 * `tag` is the canonical key, `tags` the plural the frame's list shell asks for. Both carry the same
 * `query`, so both resolve to identical wiring — exactly what the old static `$aliases` array
 * produced by copying one entry onto a second key.
 *
 * Note which one is `resource:`. The plural is the app-facing key, but `resource:` names the key the
 * MODEL is resolved under, and the `ParticleResource` this package registers is keyed `tag`. Neither
 * declaration states `model:` at all — beam's resolver reads it off that particle declaration, so
 * the model lives in exactly one place.
 */
#[ResourceFilter(key: 'tag', query: TagQuery::class)]
#[ResourceFilter(key: 'tags', resource: 'tag', query: TagQuery::class)]
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
