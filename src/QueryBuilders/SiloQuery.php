<?php

namespace Splicewire\Beam\Taxonomy\QueryBuilders;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Rushing\DataFilters\Query\ResourceQuery;
use Splicewire\Beam\Taxonomy\Models\Silo;
use Splicewire\Beam\Taxonomy\Policies\SiloPolicy;

class SiloQuery extends ResourceQuery
{
    protected function baseQuery(Request $request): Builder
    {
        // Resolve the host-bound Silo model so a beam site that rebinds the model still gets the
        // ownership-scoped, children-counted base query. Falls back to the beam model.
        $model = config('beam-taxonomy.models.silo', Silo::class);

        $query = $model::query();

        (new SiloPolicy)->scopeForUser($query, $request->user() ?? auth()->user());

        return $query->withCount('children');
    }

    protected function defaultSort(): ?string
    {
        return 'name';
    }
}
