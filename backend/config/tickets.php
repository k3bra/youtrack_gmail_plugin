<?php

return [
    'client_key' => env('CLIENT_KEY'),
    'openai_key' => env('OPENAI_API_KEY'),
    'openai_model' => env('OPENAI_MODEL', 'gpt-5-mini'),
    'youtrack_token' => env('YOUTRACK_TOKEN'),
    'youtrack_base_url' => env('YOUTRACK_BASE_URL'),
    'youtrack_project_id' => env('YOUTRACK_PROJECT_ID'),
    'youtrack_agile_name' => env('YOUTRACK_AGILE_NAME', 'Product Sprint'),
    'reply_signature' => env('REPLY_SIGNATURE'),
];
