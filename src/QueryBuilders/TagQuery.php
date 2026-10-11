<?php

namespace Splicewire\Beam\Taxonomy\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Rushing\DataFilters\Query\ResourceQuery;

class TagQuery extends ResourceQuery
{
    /** Tags are a shared catalog: declare the landed all-row population without narrowing own-view readers. */
    public static function rowScope(Builder $query): Builder
    {
        return $query;
    }

    protected function defaultSort(): ?string
    {
        return 'name';
    }
}
