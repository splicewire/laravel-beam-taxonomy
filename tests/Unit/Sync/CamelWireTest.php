<?php

use Splicewire\Beam\Taxonomy\Sync\SiloSyncData;
use Splicewire\Beam\Taxonomy\Sync\TagSyncData;

it('publishes taxonomy sync payloads in camel case with the declared hash', function (): void {
    $silo = (new SiloSyncData('silo-hash', 'Silo', 'silo', 'parent', 'silo/1'))->toArray();
    $tag = (new TagSyncData('tag-hash', 'Tag', 'tag', 'kind', 'tag/1'))->toArray();

    expect($silo)->toMatchArray([
        'hash' => 'silo-hash',
        'sourceParentId' => 'parent',
        'schemaId' => 'silo/1',
    ])->not->toHaveKeys(['_hash', 'source_parent_id', '_schema'])
        ->and($tag)->toMatchArray([
            'hash' => 'tag-hash',
            'schemaId' => 'tag/1',
        ])->not->toHaveKeys(['_hash', '_schema']);
});
