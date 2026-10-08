<?php
return [
    'bucket'     => env('COS_BUCKET', ''),
    'region'     => env('COS_REGION', ''),
    'secret_id'  => env('COS_SECRET_ID', ''),
    'secret_key' => env('COS_SECRET_KEY', ''),
    'prefix'     => trim((string) env('COS_PREFIX', 'memo'), '/'),
    'url_ttl'    => 900,
];
