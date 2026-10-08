<?php
return [
    'base_url' => rtrim((string) env('AI_BASE_URL', 'https://ark.cn-beijing.volces.com/api/coding/v3'), '/'),
    'api_key' => env('AI_API_KEY', ''),
    'model' => env('AI_MODEL', 'ark-code-latest'),
    'vision_model' => env('AI_VISION_MODEL', ''),
    'timeout' => (int) env('AI_TIMEOUT', 120),
];
