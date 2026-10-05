<?php

namespace App\Services;

use RuntimeException;

/**
 * Turns the `claude -p --output-format json` envelope of a /fix-sprint-bugs --json run into
 * a triage analysis, and enforces the gate rules instead of trusting the model's verdict.
 */
class TriageResultParser
{
    public const GATES = [
        'root_cause_pinned',
        'fix_is_local',
        'no_shared_behaviour_change',
        'callers_checked',
        'no_product_decision_needed',
    ];

    private const VERDICTS = ['fixable', 'blocked', 'error'];
    private const CONFIDENCES = ['high', 'medium', 'low'];

    public function parse(array $envelope): array
    {
        $result = $envelope['result'] ?? null;

        if (($envelope['is_error'] ?? false) === true) {
            throw new RuntimeException('Claude run failed: ' . (is_string($result) ? $result : 'unknown error'));
        }
        if (!is_string($result) || $result === '') {
            throw new RuntimeException('Claude run returned no result.');
        }

        $analysis = $this->lastJsonBlock($result);
        $verdict = $analysis['verdict'] ?? null;
        $confidence = $analysis['confidence'] ?? null;

        if (!in_array($verdict, self::VERDICTS, true)) {
            throw new RuntimeException('Triage JSON has an invalid verdict.');
        }
        if ($verdict !== 'error' && !in_array($confidence, self::CONFIDENCES, true)) {
            throw new RuntimeException('Triage JSON has an invalid confidence.');
        }

        if ($verdict === 'fixable' && (!$this->allGatesPass($analysis['gates'] ?? null) || $confidence === 'low')) {
            $analysis['verdict'] = 'blocked';
            $analysis['confidence'] = 'low';
            $analysis['planned_fix'] = null;
            $analysis['verdict_overridden'] = true;
        }

        return $analysis;
    }

    private function lastJsonBlock(string $result): array
    {
        if (preg_match_all('/```json\s*(.*?)```/s', $result, $matches) === 0) {
            throw new RuntimeException('Claude result has no ```json block.');
        }

        $decoded = json_decode(trim(end($matches[1])), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Triage JSON block is not valid JSON.');
        }

        // The skill always emits an array with one object per ticket; we triage one ticket.
        $analysis = array_is_list($decoded) ? ($decoded[0] ?? null) : $decoded;
        if (!is_array($analysis)) {
            throw new RuntimeException('Triage JSON block is empty.');
        }

        return $analysis;
    }

    private function allGatesPass(mixed $gates): bool
    {
        if (!is_array($gates)) {
            return false;
        }

        foreach (self::GATES as $gate) {
            if (($gates[$gate] ?? null) !== true) {
                return false;
            }
        }

        return true;
    }
}
