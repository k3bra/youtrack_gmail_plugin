<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Runs `claude -p ... --output-format json` from the dev-workspace root and returns the result envelope.
 * Shared by triage and Accept & fix, so both resolve the workspace, auth and environment the same way.
 */
class ClaudeCli
{
    // Deny rules win over allow rules, including ones inherited from dev-workspace's own
    // .claude/settings.local.json (e.g. Bash(git checkout *)). Git writes are the backend's job.
    public const GIT_WRITE_DENY = [
        'Bash(git checkout:*)', 'Bash(git -C * checkout *)',
        'Bash(git reset:*)', 'Bash(git -C * reset *)',
        'Bash(git commit:*)', 'Bash(git -C * commit *)',
        'Bash(git push:*)', 'Bash(git -C * push *)',
        'Bash(git stash:*)', 'Bash(git -C * stash *)',
        'Bash(git clean:*)', 'Bash(git -C * clean *)',
        'Bash(git branch:*)', 'Bash(git -C * branch *)',
        'Bash(git worktree:*)', 'Bash(git -C * worktree *)',
        'Bash(rm:*)',
    ];

    /**
     * Fails fast on missing configuration, before any YouTrack/git work. Returns the workspace path.
     */
    public function preflight(): string
    {
        $this->token();

        return $this->workspacePath();
    }

    public function workspacePath(): string
    {
        $path = config('tickets.triage_workspace_path');
        if (!is_string($path) || $path === '' || !is_dir($path . '/.claude/skills/fix-sprint-bugs')) {
            throw new RuntimeException('TRIAGE_WORKSPACE_PATH must point at dev-workspace (with the fix-sprint-bugs skill).');
        }

        return rtrim($path, '/');
    }

    /**
     * @param  list<string>  $args  everything after the claude binary
     */
    public function run(array $args, int $timeout, string $logContext): array
    {
        $process = Process::path($this->workspacePath())
            ->timeout($timeout)
            ->env($this->env())
            ->run([(string) config('tickets.triage_claude_bin', 'claude'), ...$args]);

        $envelope = json_decode($process->output(), true);
        if (!is_array($envelope)) {
            Log::error('Claude run produced no JSON', [
                'context' => $logContext,
                'exit_code' => $process->exitCode(),
                'stderr' => mb_substr($process->errorOutput(), 0, 2000),
            ]);

            throw new RuntimeException('Claude run failed (exit ' . $process->exitCode() . ').');
        }

        return $envelope;
    }

    /**
     * @return array{session_id: ?string, model: ?string, cost_usd: ?float, duration_ms: ?int, num_turns: ?int}
     */
    public function usage(array $envelope, string $fallbackModel): array
    {
        return [
            'session_id' => $envelope['session_id'] ?? null,
            'model' => array_key_first($envelope['modelUsage'] ?? []) ?? $fallbackModel,
            'cost_usd' => $envelope['total_cost_usd'] ?? null,
            'duration_ms' => $envelope['duration_ms'] ?? null,
            'num_turns' => $envelope['num_turns'] ?? null,
        ];
    }

    /**
     * Drop Claude/Anthropic variables inherited from a parent Claude session (e.g. when the
     * worker was started from the Claude desktop app): they point the CLI at the parent's auth.
     * `false` removes a variable from the inherited environment.
     */
    private function env(): array
    {
        $env = [];
        foreach (array_keys(getenv()) as $name) {
            if (preg_match('/^(CLAUDE|ANTHROPIC)/', $name)) {
                $env[$name] = false;
            }
        }
        $env['CLAUDE_CODE_OAUTH_TOKEN'] = $this->token();

        return $env;
    }

    private function token(): string
    {
        $token = config('tickets.triage_claude_token');
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('CLAUDE_CODE_OAUTH_TOKEN is not set (run `claude setup-token`).');
        }

        return $token;
    }
}
