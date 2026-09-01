<?php

namespace Splicewire\Beam\Taxonomy\Sync;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Splicewire\Beam\Taxonomy\Models\Silo;
use Splicewire\Beam\Taxonomy\Models\Tag;

/**
 * Attach an input DTO's `silos` + `tags` to a record — the shared write half of the taxonomy pivots
 * this package owns (`taggables`, `siloables`).
 *
 * ## Why it is here and not where it was
 *
 * It was a **private method on `App\Providers\ParticleServiceProvider`** at the flagship, called from
 * three `afterWrite` closures (`agents`, `context-scopes`, `threads`) as `$this->syncTaxonomyPivots(…)`.
 * That single `$this` is what pinned all three declarations to that provider: the attributed
 * declaration path resolves conventions by `method_exists($class, $method)` and calls them
 * **statically**, so a `public static function afterWrite()` on a Data class has no `$this` to reach,
 * and the three resources could not become attributed declarations or move down to the tier that owns
 * them (particle-manifest-repatriation ticket 07).
 *
 * ⚠️ The provider's own comment asserted that its inline declarations contain *"ZERO `App\`
 * references … not one of these 16 closures is host-coupled"*, and it was cited as evidence that only
 * authorship — not coupling — was blocking the descent. The grep behind it was true and the
 * conclusion was not: `$this` is a host reference that is not spelled `App\`. Corrected in place
 * rather than deleted, because the measurement was right and only its scope was wrong.
 *
 * ## Why THIS package
 *
 * Three homes were available and two are worse:
 *
 * - **A hook on each Data class.** `AgentInputData`, `ContextScopeInputData` and `ThreadInputData`
 *   live in `splicewire/tower`, and `agents` alone diverges (`$createMissing`). Putting the sync on
 *   the DTOs writes the same body three times in a package that does not own either pivot table —
 *   restatement, which is the exact shape this map exists to retire.
 * - **A beam write-pipeline hook.** The pipeline runs for every resource, so this would have to
 *   duck-type `->silos`/`->tags` on every input DTO in the estate and fire on the ones that happen to
 *   have them. A cross-cutting implicit behaviour is a much larger promise than three explicit calls.
 * - **Here.** `Silo`, `Tag`, `HasSilos` and `HasTags` are all declared in
 *   `splicewire/laravel-beam-taxonomy`, and it publishes the `tags`/`taggables` + `silos`/`siloables`
 *   migrations. The package that owns the pivot owns the code that writes it — the same rule this
 *   package already applies to its models' morph aliases and policies.
 *
 * ## The two models are read from config, not named
 *
 * `beam.taxonomy.models.{tag,silo}` is this package's host-binding seam and
 * {@see \Splicewire\Beam\Taxonomy\BeamTaxonomyServiceProvider::registerResources()} already reads it.
 * The extracted code named the base classes directly; every static it calls is written `static::`, so
 * resolving the configured class is what the call sites *meant*. At the flagship the config names the
 * base classes, so this is behaviour-identical there and correct at a host that subclasses.
 *
 * ## Behaviour is preserved exactly, including the parts that look like bugs
 *
 * `$createMissing` is the one divergence axis and it is per-caller: `agents` mints absent silos/tags
 * for an actor who can `create` them and pre-filters unresolved tags before attaching; `context-scopes`
 * and `threads` never create and attach the raw input. Both the `->filter()` on the create path and its
 * ABSENCE on the convert-only path are carried over verbatim — `convertToTags()` can yield nulls for
 * names it cannot resolve, so the two paths genuinely differ in what reaches `attachTags()`. Changing
 * that is a behaviour change, and this is not the ticket for it.
 */
class TaxonomyPivotSync
{
    public function __construct(
        protected AuthFactory $auth,
        protected Gate $gate,
        protected Config $config,
    ) {}

    /**
     * Sync `$input`'s taxonomy arms onto `$model`.
     *
     * A null arm means "not supplied" and is left untouched — an omitted key must not clear existing
     * pivot rows, which is why both arms are guarded on `!== null` rather than on emptiness.
     *
     * @param  Model  $model  the record being written; must use this package's `HasTags`/`HasSilos`
     *                        traits, which is where `attachTags()` and `silos()` come from. Typed as
     *                        `Model` rather than an interface because those traits declare no
     *                        contract to type against — worth stating, since a subject without them
     *                        fatals here rather than at the declaration.
     * @param  object  $input  the resource InputData; both `->silos` and `->tags` are read
     * @param  bool  $createMissing  mint absent silos/tags when the actor may create them
     */
    public function sync(Model $model, object $input, bool $createMissing = false): void
    {
        // ⚠️ `!== null`, deliberately, and NOT `($input->tags ?? null) !== null`. The null-coalescing
        // form reads like harmless defensiveness and is a behaviour change: an input DTO that does
        // not declare the property used to raise, and would now silently skip the arm. Review caught
        // it. A subject that cannot answer `->tags` is a wiring error at the call site, and the loud
        // version of that is the one that gets fixed.
        if ($input->tags !== null) {
            $model->attachTags($this->tags($input->tags, $createMissing));
        }

        if ($input->silos !== null) {
            $model->silos()->sync($this->silos($input->silos, $createMissing)->pluck('id'));
        }
    }

    /**
     * The tag arm. On the create path unresolved entries are dropped (`->filter()`); on the
     * convert-only path the raw input is handed to `attachTags()` untouched, exactly as before.
     */
    protected function tags(mixed $values, bool $createMissing): Collection
    {
        if (! $createMissing) {
            return collect($values);
        }

        $model = $this->tagModel();

        $tags = $this->may('create', $model)
            ? $model::convertOrCreateToTags($values)
            : $model::convertToTags($values);

        return $tags->filter();
    }

    /** The silo arm. Always converted; `$createMissing` only decides whether absent names are minted. */
    protected function silos(mixed $values, bool $createMissing): Collection
    {
        $model = $this->siloModel();

        return ($createMissing && $this->may('create', $model))
            ? $model::convertOrCreateToSilos($values)
            : $model::convertToSilos($values);
    }

    /**
     * May the acting user create one of these?
     *
     * ⚠️ Guest-safe by the EXPLICIT null check, not by the Gate. The extracted code read
     * `Auth::user()?->can(...)`, where the null-safe operator short-circuited to null and the ternary
     * took the convert-only branch; the null check below is that short-circuit, spelled out. An
     * earlier docblock here credited `Gate::forUser(null)->allows()` with the behaviour, which
     * describes code that is not in this method — review caught it. Effect is unchanged: a
     * queue/console write with no authenticated user converts, it does not mint.
     *
     * ⚠️ One genuine difference from the extracted code, stated because it is invisible: the ability
     * is checked against the CONFIGURED model class, where the original named the base class. At the
     * flagship those are the same class. At a host that binds a subclass the policy resolution now
     * follows the model the write will actually touch, which is the answer the call site meant.
     */
    protected function may(string $ability, string $model): bool
    {
        $user = $this->auth->guard()->user();

        return $user !== null && $this->gate->forUser($user)->allows($ability, $model);
    }

    /** @return class-string<Tag> */
    protected function tagModel(): string
    {
        $model = $this->config->get('beam.taxonomy.models.tag');

        return is_string($model) && class_exists($model) ? $model : Tag::class;
    }

    /** @return class-string<Silo> */
    protected function siloModel(): string
    {
        $model = $this->config->get('beam.taxonomy.models.silo');

        return is_string($model) && class_exists($model) ? $model : Silo::class;
    }
}
