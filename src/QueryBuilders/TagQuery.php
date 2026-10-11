<?php

namespace Splicewire\Beam\Taxonomy\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Rushing\DataFilters\Query\ResourceQuery;
use Splicewire\Beam\Authorization\RowAuthorization;

class TagQuery extends ResourceQuery
{
    /** The cascade's row population, shared by the registration and filtered list query. */
    public static function rowScope(Builder $query): Builder
    {
        return RowAuthorization::apply($query, $query->getModel()::class);
    }

    protected function defaultSort(): ?string
    {
        return 'name';
    }
}
