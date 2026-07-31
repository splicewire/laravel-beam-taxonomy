# Beam Taxonomy

The reusable **Tag / Silo** (BeamTag / BeamSilo) taxonomy surface for beam sites.

Ships taxonomy resources as declared `ParticleResource`s registered into beam-core's
`ParticleResourceRegistry`, so a host gets a schema-typed CRUD surface with **no controller
code** — mounted via `Route::particleResource($uri, $key)`.

Concrete models and read-Data classes are **host-bound via config**
(`config/beam-taxonomy.php`), so this foundation-tier package never depends up on a host's
model tier.

## Resources

| Key   | Model (host-bound)              | Status                                   |
| ----- | ------------------------------- | ---------------------------------------- |
| `tag` | `beam-taxonomy.models.tag`      | read-only (index) — dissolved from `TagController` |
| `silo`| `beam-taxonomy.models.silo`     | pending (issue 05)                        |

## Mount

```php
Route::particleResource('tags', 'tag', ['only' => ['index']]);
```

> Provenance: extracted during `tower-api-dissolution` (issue 02) as the reference DISSOLVE
> pattern. The physical BeamTag/BeamSilo model-cone relocation into this package is a
> sequenced follow-on (issue 05); the config seam is already correct.
