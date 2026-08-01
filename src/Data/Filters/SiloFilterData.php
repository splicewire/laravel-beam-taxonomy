<?php

declare(strict_types=1);

namespace Splicewire\Beam\Taxonomy\Data\Filters;

use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Attributes\Includable;
use Rushing\DataFilters\Attributes\Sortable;
use Rushing\DataFilters\Operators\Exact;
use Rushing\DataFilters\Operators\IlikeMatch;
use Rushing\DataFilters\Operators\KeywordFilter;
use Rushing\DataFilters\Operators\NullableExact;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Splicewire\Beam\Taxonomy\Models\Silo;

class SiloFilterData extends Data
{
    public function __construct(
        #[Filterable(Exact::class)]
        #[Sortable]
        public string|Optional|null $id = null,

        #[Filterable(NullableExact::class, name: 'parentId', column: 'parent_id', options: 'silos')]
        public string|Optional|null $parentId = null,

        // filter[name]=rag — case-insensitive substring, the canonical text-search facet
        // (was Exact; a silo is looked up exactly by id/slug, so name is the search field).
        #[Filterable(IlikeMatch::class, mode: 'contains')]
        #[Sortable]
        public string|Optional|null $name = null,

        #[Filterable(Exact::class)]
        #[Sortable]
        public string|Optional|null $slug = null,

        #[Filterable(KeywordFilter::class, model: Silo::class)]
        public string|Optional|null $keywords = null,

        #[Includable]
        public mixed $parent = null,

        #[Includable]
        public mixed $children = null,

        #[Includable]
        public mixed $fragments = null,

        #[Includable(name: 'recursiveFragments')]
        public mixed $recursiveFragments = null,

        #[Sortable(name: 'createdAt', column: 'created_at')]
        public string|Optional|null $createdAt = null,

        #[Sortable(name: 'updatedAt', column: 'updated_at')]
        public string|Optional|null $updatedAt = null,
    ) {}
}
