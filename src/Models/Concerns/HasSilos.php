<?php

namespace Splicewire\Beam\Taxonomy\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Splicewire\Beam\Taxonomy\Models\Silo;

trait HasSilos
{
    public function silos(): MorphToMany
    {
        return $this->morphToMany(Silo::class, 'siloable');
    }

    public function recursiveSilos()
    {
        return $this->morphToManyOfDescendantsAndSelf(Silo::class, 'siloable');
    }

    public function scopeWhereInSilos(Builder $query, Silo|Collection $silos, $deep = true)
    {
        if ($deep) {
            $silos = Silo::whereIn('id', $silos->pluck('id'))->with('descendants')->get()->flatMap(function ($silo) {
                return $silo->descendants->pluck('id')->push($silo->id);
            });
        } else {
            $silos = $silos->pluck('id');
        }

        return $query->whereHas('silos', fn ($q) => $q->whereIn('id', $silos));
    }
}
