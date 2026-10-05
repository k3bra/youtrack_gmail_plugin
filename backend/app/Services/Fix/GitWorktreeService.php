<?php

namespace App\Services\Fix;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Git operations for Accept & fix. All work happens in a throwaway worktree off origin/<base>,
 * so the developer's own checkouts (and any work in progress there) are never touched.
 */
class GitWorktreeService
{
    /**
     * Creates <worktree> on a new <branch> from fresh origin/<base>.
     */
    public function prepare(string $repoPath, string $worktreePath, string $branch, string $base): void
    {
        $this->git($repoPath, ['fetch', 'origin', $base, '--quiet']);

        if (trim($this->git($repoPath, ['ls-remote', '--heads', 'origin', $branch])) !== '') {
            throw new RuntimeException("Branch {$branch} already exists on origin — a fix was already pushed.");
        }

        // Leftovers from an earlier failed attempt (never pushed, checked above).
        $this->git($repoPath, ['worktree', 'remove', '--force', $worktreePath], false);
        $this->git($repoPath, ['worktree', 'prune'], false);
        $this->git($repoPath, ['branch', '-D', $branch], false);

        $this->git($repoPath, ['worktree', 'add', $worktreePath, '-b', $branch, "origin/{$base}"]);
    }

    /**
     * @return array{files: list<string>, changed_lines: int}
     */
    public function changes(string $worktreePath): array
    {
        $this->git($worktreePath, ['add', '-A']);

        $files = array_values(array_filter(explode("\n", trim($this->git($worktreePath, ['diff', '--cached', '--name-only'])))));

        $changedLines = 0;
        foreach (explode("\n", trim($this->git($worktreePath, ['diff', '--cached', '--numstat']))) as $line) {
            [$added, $deleted] = array_pad(preg_split('/\s+/', $line), 2, '0');
            $changedLines += (int) $added + (int) $deleted;
        }

        return ['files' => $files, 'changed_lines' => $changedLines];
    }

    public function commit(string $worktreePath, string $message): string
    {
        $this->git($worktreePath, ['commit', '--quiet', '-m', $message]);

        return trim($this->git($worktreePath, ['rev-parse', 'HEAD']));
    }

    public function push(string $worktreePath, string $branch): void
    {
        $this->git($worktreePath, ['push', '--quiet', '-u', 'origin', $branch]);
    }

    public function remove(string $repoPath, string $worktreePath): void
    {
        $this->git($repoPath, ['worktree', 'remove', '--force', $worktreePath], false);
    }

    /**
     * "wonderoute/console" from git@bitbucket.org:wonderoute/console.git or https://…/wonderoute/console.git
     */
    public function bitbucketSlug(string $repoPath): string
    {
        $url = trim($this->git($repoPath, ['remote', 'get-url', 'origin']));

        if (!preg_match('#bitbucket\.org[:/]([^/]+)/([^/]+?)(?:\.git)?$#', $url, $m)) {
            throw new RuntimeException("Origin of {$repoPath} is not a Bitbucket repo.");
        }

        return $m[1] . '/' . $m[2];
    }

    private function git(string $cwd, array $args, bool $mustSucceed = true): string
    {
        $result = Process::path($cwd)->timeout(180)->run(['git', ...$args]);

        if ($mustSucceed && !$result->successful()) {
            throw new RuntimeException('git ' . $args[0] . ' failed: ' . trim($result->errorOutput() ?: $result->output()));
        }

        return $result->output();
    }
}
