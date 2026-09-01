<?php

namespace Splicewire\Beam\Taxonomy\QueryBuilders;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Rushing\DataFilters\Query\ResourceQuery;
use Splicewire\Beam\Authorization\RowAuthorization;
use Splicewire\Beam\Taxonomy\Models\Silo;

class SiloQuery extends ResourceQuery
{
    protected function baseQuery(Request $request): Builder
    {
        // Resolve the host-bound Silo model so a beam site that rebinds the model still gets the
        // ownership-scoped, children-counted base query. Falls back to the beam model.
        $model = config('beam.taxonomy.models.silo', Silo::class);

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
        //
        // The duck-typed `$policy !== null && method_exists($policy, 'scopeForUser')` guard this line
        // replaced returned the query UNSCOPED when it failed — fail-OPEN, and registry-kernel 72's
        // defect promoted to a default. {@see RowAuthorization} fails CLOSED instead, and types the
        // check (`instanceof BaseModelPolicy`) so that "a host bound some other policy class" stops
        // resolving to "allow everything". Measured 2026-08-31 across all 21 `~/Herd` roots: `Silo`
        // exists at 7 and **all 7** bind `ConfiguredModelPolicy`, so this changes nothing anywhere
        // today — it changes what happens the day a host mounts silos without binding a policy, which
        // is precisely the case fail-closed exists to catch.
        $query = RowAuthorization::apply($model::query(), $model);

        return $query->withCount('children');
    }

    protected function defaultSort(): ?string
    {
        return 'name';
    }
}
