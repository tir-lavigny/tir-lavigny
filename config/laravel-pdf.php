<?php

return [
    'driver' => env('LARAVEL_PDF_DRIVER', 'dompdf'),

    'dompdf' => [
        'is_remote_enabled' => false,
        'chroot' => storage_path(),
        'font_dir' => storage_path('fonts'),
        'font_cache' => storage_path('fonts'),
    ],
];
