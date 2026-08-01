<?php

namespace Splicewire\Beam\Taxonomy\QueryBuilders;

use Rushing\DataFilters\Query\ResourceQuery;

class TagQuery extends ResourceQuery
{
    protected function defaultSort(): ?string
    {
        return 'name';
    }
}
