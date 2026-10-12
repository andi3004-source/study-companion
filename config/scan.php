<?php

/**
 * "Raba lalu dengar" (scan an object, hear its name). Off by default: while
 * disabled the routes answer 404 and the navigation link is hidden.
 * Photos are processed in memory and never stored.
 */
return [
    'enabled' => (bool) env('SCAN_BENDA_ENABLED', false),

    // Largest accepted image after base64 decoding, in bytes.
    'max_image_bytes' => (int) env('SCAN_BENDA_MAX_IMAGE_BYTES', 4000000),

    // Recognition requests per user per minute.
    'rate_limit_per_minute' => (int) env('SCAN_BENDA_RATE_LIMIT_PER_MINUTE', 6),

    // Seconds to wait for the vision model.
    'timeout' => (int) env('SCAN_BENDA_TIMEOUT', 30),
];
