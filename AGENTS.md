> You are in **splicewire/laravel-beam-taxonomy** — the reusable Tag/Silo taxonomy surface for beam sites.

A Laravel package providing the `BeamTag` / `BeamSilo` taxonomy surface, shipped as declared
`ParticleResource`s registered into beam-core's `ParticleResourceRegistry` — a host gets
schema-typed CRUD with no controller code. Concrete models and read-Data classes are host-bound
via config (`config/beam-taxonomy.php`), so this foundation-tier package never depends up on a
host's model tier.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.
