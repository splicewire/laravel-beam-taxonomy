<?php

namespace Splicewire\Beam\Taxonomy\QueryBuilders;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Rushing\DataFilters\Query\ResourceQuery;
use Splicewire\Beam\Taxonomy\Models\Silo;

class SiloQuery extends ResourceQuery
{
    protected function baseQuery(Request $request): Builder
    {
        // Resolve the host-bound Silo model so a beam site that rebinds the model still gets the
        // ownership-scoped, children-counted base query. Falls back to the beam model.
        $model = config('beam.taxonomy.models.silo', Silo::class);

        $query = $model::query();

        // ASK the Gate for whatever policy is bound to the resolved model rather than naming a concrete
        // class. `Splicewire\Beam\Taxonomy\Policies\SiloPolicy` was DELETED by this package's own
        // `89055b9` ("beam-taxonomy owns its morph aliases and its authorization") — it was
        // `extends BaseModelPolicy` plus a `$defaultModelClass` and nothing else, which is exactly what
        // `#[UseCascadePolicy]` on {@see Silo} now expresses — and this call site was left behind,
        // fataling every FILTERED silo read with `Class ... not found` while the unfiltered index
        // stayed green. The row-level scope is NOT lost: `CascadePolicyRegistrar` binds a
        // `ConfiguredModelPolicy extends BaseModelPolicy`, and `scopeForUser` is BaseModelPolicy's own
        // method, so the same cascade scope runs. Reaching through the Gate is also what makes the
        // host-rebound model honest — a site that repoints `beam.taxonomy.models.silo` gets ITS
        // policy, not the beam model's. Identical repair, identical cause, to
        // `Splicewire\Tower\QueryBuilders\FragmentQuery` (deleted `FragmentPolicy`).
        $policy = Gate::getPolicyFor($model);

        if ($policy !== null && method_exists($policy, 'scopeForUser')) {
            $policy->scopeForUser($query, $request->user() ?? auth()->user());
        }

        return $query->withCount('children');
    }

    protected function defaultSort(): ?string
    {
        return 'name';
    }
}
