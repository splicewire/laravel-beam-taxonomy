<?php

namespace Splicewire\Beam\Taxonomy\Policies;

use Rushing\PermissionCascade\Policies\BaseModelPolicy;
use Splicewire\Beam\Taxonomy\Models\Tag;

/**
 * Authorization policy for the beam Tag.
 *
 * Extends the cascade base policy DIRECTLY (`rushing/laravel-permission-cascade`) rather than
 * the host's `Splicewire\Tower\Policies\BaseModelPolicy` — the host base is an unmodified subclass of this
 * same cascade base (reconciled in the compliance-package ticket 12), so extending the
 * foundation is behaviour-identical while keeping beam-taxonomy from reaching UP into the host
 * policy tier (tower-api-dissolution issue 17 P2).
 */
class TagPolicy extends BaseModelPolicy
{
    public static $defaultModelClass = Tag::class;
}
