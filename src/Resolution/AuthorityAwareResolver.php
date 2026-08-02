<?php

namespace Splicewire\Beam\Taxonomy\Resolution;

use Illuminate\Database\Eloquent\Model;
use Splicewire\Beam\Taxonomy\Models\Silo;
use Splicewire\Beam\Taxonomy\Models\Tag;

/**
 * Authority-aware `(authority, naturalKey) → entity` resolver (sourced-particles ticket 05).
 *
 * This is the merge-on-materialize seam: before a foreign association is LINKED, it is
 * reconciled against LOCAL identity by its source-scoped natural key — never by a bare
 * name / `name_path`. Ticket 07's silo/tag materialize calls this.
 *
 * The correctness contract (MAP §Merge-on-materialize):
 *   - Same `(authority, naturalKey)` → the SAME local entity (idempotent get-or-create,
 *     backed by the partial unique index on `external_ref`).
 *   - DIFFERENT authorities → DISTINCT entities, even when their natural keys collide. A
 *     foreign silo that merely shares a `name_path` with a local silo does NOT merge into
 *     it — that collapse is a cross-tenant scope leak on a governed ACL container.
 *   - A ref with no authority is illegal ({@see SourceRef} throws on construction).
 *
 * Resolution keys STRICTLY on the composed `external_ref`. It deliberately does NOT fall
 * back to `Silo::findFromString()` / `Tag::findFromString()` on a miss — that fallthrough
 * is the leak. A miss mints a foreign-only shadow entity stamped with provenance.
 *
 * Tags are folksonomy: for the LOCAL authority a caller may prefer name-union
 * ({@see Tag::findOrCreate}) instead; this resolver is the FOREIGN mint/dedup path. Silos
 * are governed containers: they must ALWAYS route through here for any non-local authority.
 */
class AuthorityAwareResolver
{
    /**
     * Resolve-or-create the local Tag for a source ref. Foreign-only tags mint with
     * `external_ref` + `source_authority`; the partial unique index dedups re-mints.
     *
     * @param  array<string,mixed>  $attributes  extra columns to stamp on a fresh mint (e.g. name, type)
     */
    public function resolveTag(SourceRef $ref, array $attributes = []): Tag
    {
        return $this->resolveByExternalRef($this->tagModel(), $ref, $attributes + [
            'name' => $attributes['name'] ?? $ref->naturalKey,
        ]);
    }

    /**
     * Resolve-or-create the local Silo for a source ref — the BLOCKING sub-gate. Keys on
     * `external_ref` only; a `name_path` collision with a differently-authoritied silo does
     * NOT merge (distinct authorities → distinct rows).
     *
     * @param  array<string,mixed>  $attributes  extra columns to stamp on a fresh mint (e.g. name)
     */
    public function resolveSilo(SourceRef $ref, array $attributes = []): Silo
    {
        return $this->resolveByExternalRef($this->siloModel(), $ref, $attributes + [
            'name' => $attributes['name'] ?? $ref->naturalKey,
        ]);
    }

    /**
     * The generic seam: resolve-or-create any external-ref-bearing model by its source ref.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<string,mixed>  $attributes
     */
    public function resolve(string $modelClass, SourceRef $ref, array $attributes = []): Model
    {
        return $this->resolveByExternalRef($modelClass, $ref, $attributes);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string,mixed>  $attributes
     */
    protected function resolveByExternalRef(string $modelClass, SourceRef $ref, array $attributes): Model
    {
        $externalRef = $ref->externalRef();

        $existing = $modelClass::query()->where('external_ref', $externalRef)->first();
        if ($existing) {
            return $existing;
        }

        // Foreign-only shadow mint — stamped with provenance so a re-resolve dedups.
        return $modelClass::create($attributes + [
            'external_ref' => $externalRef,
            'source_authority' => $ref->authority,
        ]);
    }

    /** @return class-string<Tag> */
    protected function tagModel(): string
    {
        $bound = function_exists('config') ? config('beam.taxonomy.models.tag') : null;

        return is_string($bound) && class_exists($bound) ? $bound : Tag::class;
    }

    /** @return class-string<Silo> */
    protected function siloModel(): string
    {
        $bound = function_exists('config') ? config('beam.taxonomy.models.silo') : null;

        return is_string($bound) && class_exists($bound) ? $bound : Silo::class;
    }
}
