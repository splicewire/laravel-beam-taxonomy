<?php

declare(strict_types=1);

namespace Splicewire\Beam\Taxonomy\Policies;

use Rushing\PermissionCascade\Policies\BaseModelPolicy;
use Splicewire\Beam\Taxonomy\Models\Silo;

/**
 * Authorization policy for the beam Silo.
 *
 * Extends the cascade base policy DIRECTLY (`rushing/laravel-permission-cascade`) rather than
 * the host's `App\Policies\BaseModelPolicy` — see {@see TagPolicy} for the rationale
 * (tower-api-dissolution issue 17 P2).
 */
class SiloPolicy extends BaseModelPolicy
{
    public static $defaultModelClass = Silo::class;
}
