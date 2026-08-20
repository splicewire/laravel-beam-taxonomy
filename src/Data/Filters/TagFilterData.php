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
 * ONE key, `tags`, matching the `ParticleResource` this package registers. It was `tag` — singular,
 * against a plural `tags` URL — until api-surface-coherence 16 normalised every resource key to
 * plural-kebab and deleted the alias array that had let the two spellings coexist. There is still
 * exactly one declaration: repeatability of `#[ResourceFilter]` is reserved for a declared VARIANT
 * (ticket 10 §4), not for propping up a legacy spelling.
 *
 * No `model:` — beam's resolver reads it off the `ParticleResource` registered under this same key,
 * so the model lives in exactly one place.
 */
#[ResourceFilter(key: 'tags', query: TagQuery::class)]
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
