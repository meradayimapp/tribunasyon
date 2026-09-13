<?php

return [
    'disk' => env('MEDIA_DISK', 'public'),
    'legacy_disk' => env('MEDIA_LEGACY_DISK', 'public'),

    // R2 keys carry a versioned prefix so legacy public-disk objects can keep
    // serving in place during a gradual, zero-downtime migration.
    'r2_prefix' => env('MEDIA_R2_PREFIX', 'r2/v1'),

    // New uploads always receive a random hash name. Existing keys are never
    // overwritten; replacing media creates a new key and deletes the old key
    // only after the database update succeeds.
    'cache_control' => env('MEDIA_CACHE_CONTROL', 'public, max-age=31536000, immutable'),
];
