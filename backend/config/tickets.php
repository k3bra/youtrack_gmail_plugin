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

    // AI triage: runs `claude -p /fix-sprint-bugs` against the local dev-workspace checkout.
    'triage_workspace_path' => env('TRIAGE_WORKSPACE_PATH'),
    'triage_claude_bin' => env('TRIAGE_CLAUDE_BIN', 'claude'),
    'triage_claude_token' => env('CLAUDE_CODE_OAUTH_TOKEN'),
    'triage_model' => env('TRIAGE_MODEL', 'sonnet'),
    'triage_max_turns' => (int) env('TRIAGE_MAX_TURNS', 30),
    'triage_timeout' => (int) env('TRIAGE_TIMEOUT', 600),

    // Automatic triage of tickets tagged ai-fix (tickets:triage-ai-fix, scheduled every 10 min).
    // Each tagged ticket is triaged once; at most triage_auto_max new ones per check.
    'triage_auto_enabled' => (bool) env('TRIAGE_AUTO_ENABLED', true),
    'triage_auto_query' => env('TRIAGE_AUTO_QUERY', 'project: PRD tag: ai-fix #Unresolved'),
    'triage_auto_max' => (int) env('TRIAGE_AUTO_MAX', 3),

    // Accept & fix: resumes the triage session on Opus to write the fix in a git worktree,
    // then the backend commits, pushes and opens a draft PR on Bitbucket.
    'fix_model' => env('FIX_MODEL', 'opus'),
    'fix_max_turns' => (int) env('FIX_MAX_TURNS', 60),
    'fix_timeout' => (int) env('FIX_TIMEOUT', 1500),
    'fix_base_branch' => env('FIX_BASE_BRANCH', 'develop'),
    'fix_repos' => ['console', 'hijiffy_web', 'chatbot-campaigns', 'widget'],
    'fix_max_changed_lines' => (int) env('FIX_MAX_CHANGED_LINES', 300),
    'bitbucket_config' => env('BITBUCKET_CONFIG', ($_SERVER['HOME'] ?? getenv('HOME')) . '/.bitbucket-rest-cli-config.json'),
];
