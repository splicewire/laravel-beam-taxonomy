<?php

namespace Splicewire\Beam\Taxonomy\Data;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Example;
use Spatie\LaravelData\Optional;

/**
 * The silo WRITE shape — the `input:` slot of the `silos` particle resource
 * ({@see \Splicewire\Beam\Taxonomy\BeamTaxonomyServiceProvider::registerParticleResources()}), which
 * the host's `SiloController` resolves from the registry for its inherited write verbs.
 *
 * ## The three input states, and which fields can tell them apart
 *
 * A write body can say three different things about a field: it can be *absent* ("leave it alone"),
 * *present-and-null* ("clear it"), or *present with a value*. A promoted property written
 * `public ?T $x = null` can only ever express TWO of them — `DefaultValuesDataPipe` checks
 * `hasDefaultValue` BEFORE `type->isOptional`, so the declared default wins and an absent field
 * arrives as `null`, indistinguishable from a submitted one. On a `!== null` gate the two collapse,
 * and the collapse is one-directional: the column can be set and can never be cleared.
 *
 *   - **`parentId`** is `string|Optional|null` with NO `= null` default (the default is the sentinel
 *     itself). Removing the `= null` is the whole fix; putting it back makes the `Optional` arm
 *     unreachable again. Absent ⇒ untouched · present-and-null ⇒ written as null, un-nesting the
 *     silo to the top level · value ⇒ written. This is the field where the distinction is real,
 *     because its own `#[Description]` already promises exactly that null semantic and — on an
 *     UPDATE — the DTO could not keep the promise.
 *   - **`name` and `slug`** stay on the drop-nulls gate. Both are NOT NULL in `create_silos_table`,
 *     so "clear" is not an affordance being withheld; it is a constraint violation. Dropping the
 *     null is the correct no-op.
 *   - **`children`** is not a column at all — it is consumed by the child-creation hook, never by
 *     {@see toModelAttributes()} — so there is nothing here to clear.
 */
#[Description('Payload for creating or updating a silo.')]
class SiloInputData extends Data
{
    public function __construct(
        #[Example('Policies')]
        public ?string $name = null,
        #[Example('policies')]
        public ?string $slug = null,
        /**
         * Nullable in the column and CLEARABLE — see the class docblock. `string|Optional|null` with no
         * `= null` default, so an absent field is the `Optional` sentinel and an explicit null is a real
         * null that reaches `parent_id`. Do not restore the default.
         */
        #[Description('Parent silo id to nest under, or null for a top-level silo. Omit the field entirely to leave the current parent untouched.')]
        public string|Optional|null $parentId = new Optional,
        /** @var array[]|null */
        #[Description('Child silos to create beneath this one.')]
        public ?array $children = null,
    ) {}

    /**
     * The write map the beam write seam ({@see \Splicewire\Beam\Http\Particle\ParticleController::toAttributes()})
     * reads.
     *
     * Two gates, deliberately: the NOT NULL columns drop their nulls, so a partial write never clobbers a
     * field the caller did not name; `parent_id` is gated on PRESENCE instead, because an explicit null
     * there is a caller moving the silo to the top level and that is the one thing the null-dropping gate
     * cannot express.
     *
     * Explicit per-field checks, never `get_object_vars`, which would leak `Optional` sentinels onto the
     * write.
     *
     * @return array<string, mixed>
     */
    public function toModelAttributes(): array
    {
        $map = [
            'name' => 'name',
            'slug' => 'slug',
        ];

        $attrs = [];
        foreach ($map as $prop => $column) {
            if ($this->$prop !== null) {
                $attrs[$column] = $this->$prop;
            }
        }

        // Absent ⇒ leave the column alone. Present ⇒ write it, INCLUDING a null, which un-nests the
        // silo to the top level. See the property's note.
        if (! $this->parentId instanceof Optional) {
            $attrs['parent_id'] = $this->parentId;
        }

        return $attrs;
    }
}
