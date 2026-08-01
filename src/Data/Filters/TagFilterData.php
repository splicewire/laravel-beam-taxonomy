<?php

declare(strict_types=1);

namespace Splicewire\Beam\Taxonomy\Data\Filters;

use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Attributes\Sortable;
use Rushing\DataFilters\Operators\Exact;
use Rushing\DataFilters\Operators\IlikeMatch;
use Rushing\DataFilters\Operators\KeywordFilter;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Splicewire\Beam\Taxonomy\Models\Tag;

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
