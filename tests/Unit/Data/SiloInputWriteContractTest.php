<?php

use Splicewire\Beam\Taxonomy\Data\SiloInputData;

/**
 * The three states a PATCH body can be in, and which of them `SiloInputData` can tell apart.
 *
 * `parentId` is the field where the distinction is real: the property's own `#[Description]` already
 * promises "*Parent silo id to nest under, or null for a top-level silo*", and until the `Optional`
 * conversion that promise could not be kept on an UPDATE — an explicit `null` was indistinguishable
 * from an omitted field and was dropped, so a nested silo could never be moved back to the top level
 * through this DTO.
 */
it('leaves parent_id alone when the field is absent', function () {
    $attributes = SiloInputData::from(['name' => 'Policies'])->toModelAttributes();

    expect($attributes)->not->toHaveKey('parent_id');
});

it('writes a null parent_id when the field is present and null, un-nesting the silo', function () {
    $attributes = SiloInputData::from(['name' => 'Policies', 'parentId' => null])->toModelAttributes();

    expect($attributes)->toHaveKey('parent_id')
        ->and($attributes['parent_id'])->toBeNull();
});

it('writes a supplied parent_id', function () {
    $attributes = SiloInputData::from(['parentId' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'])->toModelAttributes();

    expect($attributes['parent_id'])->toBe('9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d');
});

/**
 * The deliberate non-conversion. `silos.name` and `silos.slug` are both NOT NULL in
 * `create_silos_table`, so a "clear" on either is a constraint violation dressed up as an API
 * affordance — they keep the drop-nulls gate, where an explicit null is a harmless no-op.
 */
it('drops nulls for the NOT NULL columns rather than trying to clear them', function () {
    $attributes = SiloInputData::from(['name' => null, 'slug' => null])->toModelAttributes();

    expect($attributes)->toBe([]);
});
