<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Host-bound taxonomy models
    |--------------------------------------------------------------------------
    |
    | The taxonomy ParticleResource declarations name their concrete model +
    | read Data class by config, so a host binds its own Tag/Silo models
    | without this package depending UP on the host's model tier. A beam site
    | that has no taxonomy model simply leaves these null and the resources
    | are not registered (the provider guards on class_exists).
    |
    | This app's defaults point at the tower-core models; the BeamTag/BeamSilo
    | model-cone extraction into this package is a sequenced follow-on
    | (.scratch/tower-api-dissolution issue 05) — the seam is already correct,
    | so that later move is a config default change, not a consumer edit.
    */

    'models' => [
        'tag' => \App\Models\Tag::class,
        'silo' => \App\Models\Silo::class,
    ],

    'data' => [
        'tag' => \App\Data\TagData::class,
        'silo' => \App\Data\SiloData::class,
    ],
];
