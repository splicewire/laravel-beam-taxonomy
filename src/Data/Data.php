<?php

namespace Splicewire\Beam\Taxonomy\Data;

use Illuminate\Http\JsonResponse;
use Spatie\LaravelData\Data as SpatieData;

/**
 * Base data class for the beam-taxonomy DTO cone (Tag/Silo). Adds a bare
 * `toResponse`/`toResponseArray` seam over spatie's Data — the same shape the host's
 * `Splicewire\Tower\Data\Data` base carried before the extraction, so the host base can subclass this
 * without behaviour change (tower-api-dissolution issue 17 P2).
 */
class Data extends SpatieData
{
    public function toResponseArray(): array
    {
        return $this->toArray();
    }

    public function toResponse($request): JsonResponse
    {
        return new JsonResponse($this->toResponseArray());
    }
}
