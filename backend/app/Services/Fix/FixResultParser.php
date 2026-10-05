<?php

namespace App\Services\Fix;

use RuntimeException;

/**
 * Reads the JSON answer of a headless /fix-sprint-bugs execution
 * ("continue with PRD-X --worktree … --branch … --json").
 */
class FixResultParser
{
    public function parse(array $envelope): array
    {
        $result = $envelope['result'] ?? null;

        if (($envelope['is_error'] ?? false) === true) {
            throw new RuntimeException('Claude run failed: ' . (is_string($result) ? $result : 'unknown error'));
        }
        if (!is_string($result) || preg_match_all('/```json\s*(.*?)```/s', $result, $matches) === 0) {
            throw new RuntimeException('Claude fix result has no ```json block.');
        }

        $fix = json_decode(trim(end($matches[1])), true);
        if (is_array($fix) && array_is_list($fix)) {
            $fix = $fix[0] ?? null;
        }
        if (!is_array($fix) || !in_array($fix['status'] ?? null, ['implemented', 'blocked'], true)) {
            throw new RuntimeException('Claude fix JSON has an invalid status.');
        }

        if ($fix['status'] === 'implemented') {
            foreach (['commit_message', 'pr_title', 'pr_description'] as $field) {
                if (!is_string($fix[$field] ?? null) || trim($fix[$field]) === '') {
                    throw new RuntimeException("Claude fix JSON is missing {$field}.");
                }
            }
        }

        return $fix;
    }
}
