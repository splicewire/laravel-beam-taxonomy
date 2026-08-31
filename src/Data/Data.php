<?php

namespace Splicewire\Beam\Taxonomy\Data;

use Splicewire\Beam\Data\BeamData;

/**
 * Base data class for the beam-taxonomy DTO cone (Tag/Silo).
 *
 * It was originally a character-for-character clone of the host's `Splicewire\Tower\Data\Data` base,
 * carried here so the host base could subclass it without behaviour change across the extraction
 * (tower-api-dissolution issue 17 P2). The clone was never re-pointed once
 * {@see BeamData} landed, and it silently diverged: the clone's `toResponse()` was a bare
 * `new JsonResponse`, so this cone rendered UNDEFENDED while every other DTO under the seam got
 * {@see \Splicewire\Beam\Data\RendersJsonSafely} for free — the same asymmetry
 * `api-surface-coherence 109` was written about, one package over. Tower's copy has since collapsed
 * to `extends BeamData`; this one now matches, which is what makes the seam one seam.
 *
 * Kept as a named subclass rather than deleted: it is this package's own DTO vocabulary, and the
 * three classes under it (`TagData`, `SiloData`, `SiloInputData`) name it, not beam's base.
 */
class Data extends BeamData {}
