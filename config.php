<?php
// Configuration for Visionary
// Optionally set via environment variables or edit here directly.
return [
    'github_client_id' => getenv('GITHUB_CLIENT_ID') ?: '',
    'github_client_secret' => getenv('GITHUB_CLIENT_SECRET') ?: '',
    // Base URL used for OAuth redirect. Change if not running on localhost:8000
    'base_url' => getenv('VISIONARY_BASE_URL') ?: 'http://localhost:8000',
];
