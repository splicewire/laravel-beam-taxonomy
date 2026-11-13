# Taxonomy API root

Call `TaxonomyRoutes::mount()` inside the host's authenticated tenant boundary, before site catch-alls. It exports the existing tags index and standard hook/discovery reads at `beam.taxonomy.api_root` (default `beam/taxonomy`, environment override `BEAM_TAXONOMY_API_ROOT`). Route names use `beam.taxonomy.*` so the old host mount stays distinct.

Taxonomy declares the silo resource and owns this same root key. Tower's `SiloRoutes::mount()` exports its specialized SiloController responses and corpus operations at that root, under the root's 2026-11-13 ruling. This preserves the existing wire contract without a Taxonomy-to-Tower dependency or invented generic silo CRUD. Land the taxonomy config with that Tower export.

The host keeps its legacy mount during expansion and adopts the package exports and generated clients separately. ApiRoot is supplied by Beam ADR-0243, available from c583da31; the landing owner resolves that prerequisite. This package does not auto-mount the new export or change host model bindings.
