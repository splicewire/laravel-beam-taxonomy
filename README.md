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
| `tag` | `beam.taxonomy.models.tag`      | read-only (index) — dissolved from `TagController` |
| `silo`| `beam.taxonomy.models.silo`     | pending (issue 05)                        |

## Mount

```php
Route::particleResource('tags', 'tag', ['only' => ['index']]);
```

> Provenance: extracted during `tower-api-dissolution` (issue 02) as the reference DISSOLVE
> pattern. The physical BeamTag/BeamSilo model-cone relocation into this package is a
> sequenced follow-on (issue 05); the config seam is already correct.

### Physical table names

Hosts that already own unrelated taxonomy tables can set these keys in their
`config/beam/taxonomy.php` before publishing and running the migrations:

```php
 'tables' => [
     'tags' => 'beam_tags',
     'taggables' => 'beam_taggables',
     'silos' => 'beam_silos',
     'siloables' => 'beam_siloables',
 ],
```

The defaults remain `tags`, `taggables`, `silos` and `siloables`. The models,
relations and published migrations use the configured names; resource keys and
search index names stay unchanged. Explicit model `$table` overrides still take
precedence. This setting does not rename tables or move existing data. Publish
the package's native migration stubs after selecting the names, and keep the
configuration stable throughout migration and rollback.
