<?php

namespace Splicewire\Beam\Taxonomy\Models\Concerns;

use Rushing\PermissionCascade\Concerns\HasUser as CascadeHasUser;

/**
 * Ownership concern for the beam-taxonomy models (Tag, Silo).
 *
 * The behaviour — the morphToMany `userable` pivot + owner auto-sync — lives in the package
 * `rushing/laravel-permission-cascade`, with the concrete user model resolved from
 * `config('permission-cascade.user_model')` rather than a hardcoded tenancy switch. Moved DOWN
 * here from the host's `App\Models\HasUser` in tower-api-dissolution issue 17 P2; the host trait
 * is kept as a thin back-compat alias over this one.
 */
trait HasUser
{
    use CascadeHasUser;
}
