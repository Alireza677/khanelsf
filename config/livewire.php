<?php

return [
    // Temporary uploads must remain private even when the application's default disk is public.
    'temporary_file_upload' => ['disk' => 'product_imports'],
];
