<?php

$defaultManifest = env('APP_ENV') === 'testing'
    ? base_path('tests/Fixtures/modules.test.json')
    : base_path('modules.json');

return [
    'path' => base_path('modules'),

    'manifest' => env('MODULES_MANIFEST', $defaultManifest),
];
