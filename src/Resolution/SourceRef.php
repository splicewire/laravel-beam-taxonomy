<?php

namespace Splicewire\Beam\Taxonomy\Resolution;

use InvalidArgumentException;

/**
 * A source-scoped natural key: `(authority, naturalKey)` — the addressing unit for
 * authority-aware entity resolution (sourced-particles ticket 05).
 *
 * `authority` is WHO says this entity exists (the source of record — a foreign tenant,
 * a conduit provider, or the local tenant itself). `naturalKey` is the entity's stable
 * identity WITHIN that authority (a tag's name/slug, a silo's `name_path`).
 *
 * The two compose into a single opaque `externalRef()` string — the provenance column
 * that mirrors `Fragment.external_ref` — which is the idempotent dedup key the partial
 * unique index enforces. Same `(authority, naturalKey)` → same `external_ref` → one row;
 * a DIFFERENT authority with the SAME naturalKey → a DIFFERENT `external_ref` → a DISTINCT
 * row. That is the whole correctness gate: a foreign silo can never collapse into a local
 * silo that merely shares a `name_path`, because their authorities differ (cross-tenant
 * scope leak, MAP §Merge-on-materialize).
 *
 * A ref with no declared authority is ILLEGAL — resolution by bare name/name_path is
 * exactly the leak we forbid, so an empty authority throws rather than silently degrading
 * to a name lookup.
 */
class SourceRef
{
    /** The separator between authority and natural key in the composed external_ref. */
    public const SEPARATOR = '::';

    public string $authority;

    public string $naturalKey;

    public function __construct(string $authority, string $naturalKey)
    {
        $authority = trim($authority);
        $naturalKey = trim($naturalKey);

        if ($authority === '') {
            throw new InvalidArgumentException(
                'A SourceRef requires a declared authority; resolution by bare natural key is illegal '
                .'(it is the cross-tenant scope leak the authority gate forbids).'
            );
        }

        if ($naturalKey === '') {
            throw new InvalidArgumentException('A SourceRef requires a non-empty natural key.');
        }

        $this->authority = $authority;
        $this->naturalKey = $naturalKey;
    }

    public static function make(string $authority, string $naturalKey): static
    {
        return new static($authority, $naturalKey);
    }

    /**
     * The composed provenance string stored in the `external_ref` column — authority-scoped,
     * so it is unique per source and dedups idempotently under the partial unique index.
     */
    public function externalRef(): string
    {
        return $this->authority.self::SEPARATOR.$this->naturalKey;
    }

    /**
     * Whether this ref names the LOCAL authority — a marker some callers use to decide
     * between the folksonomy name-union path (local tags) and the foreign-only mint path.
     */
    public function isLocal(string $localAuthority): bool
    {
        return $this->authority === trim($localAuthority);
    }
}
