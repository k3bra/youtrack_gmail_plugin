<?php

namespace Tests\Unit;

use App\Services\TriageResultParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TriageResultParserTest extends TestCase
{
    private const ALL_GATES_PASS = [
        'root_cause_pinned' => true,
        'fix_is_local' => true,
        'no_shared_behaviour_change' => true,
        'callers_checked' => true,
        'no_product_decision_needed' => true,
    ];

    public function test_parses_last_json_block_and_ignores_surrounding_text(): void
    {
        $result = "Triage is nearly done.\n\n```json\n" . json_encode([$this->analysis('blocked', 'low')]) . "\n```";

        $analysis = (new TriageResultParser())->parse(['is_error' => false, 'result' => $result]);

        $this->assertSame('blocked', $analysis['verdict']);
        $this->assertSame('PRD-6526', $analysis['ticket']);
        $this->assertArrayNotHasKey('verdict_overridden', $analysis);
    }

    public function test_uses_the_last_block_when_there_are_several(): void
    {
        $result = "```json\n{\"draft\": true}\n```\nFinal:\n```json\n"
            . json_encode([$this->analysis('fixable', 'high')]) . "\n```";

        $analysis = (new TriageResultParser())->parse(['result' => $result]);

        $this->assertSame('fixable', $analysis['verdict']);
    }

    public function test_keeps_fixable_when_every_gate_passes(): void
    {
        $analysis = (new TriageResultParser())->parse($this->envelope($this->analysis('fixable', 'medium')));

        $this->assertSame('fixable', $analysis['verdict']);
        $this->assertSame('medium', $analysis['confidence']);
        $this->assertSame('Change the condition', $analysis['planned_fix']);
    }

    public function test_overrides_fixable_to_blocked_when_a_gate_fails(): void
    {
        $gates = ['no_shared_behaviour_change' => false] + self::ALL_GATES_PASS;

        $analysis = (new TriageResultParser())->parse($this->envelope($this->analysis('fixable', 'medium', $gates)));

        $this->assertSame('blocked', $analysis['verdict']);
        $this->assertSame('low', $analysis['confidence']);
        $this->assertNull($analysis['planned_fix']);
        $this->assertTrue($analysis['verdict_overridden']);
    }

    public function test_overrides_fixable_when_gates_are_missing(): void
    {
        $analysis = $this->analysis('fixable', 'high');
        unset($analysis['gates']);

        $parsed = (new TriageResultParser())->parse($this->envelope($analysis));

        $this->assertSame('blocked', $parsed['verdict']);
    }

    public function test_overrides_fixable_with_low_confidence(): void
    {
        $analysis = (new TriageResultParser())->parse($this->envelope($this->analysis('fixable', 'low')));

        $this->assertSame('blocked', $analysis['verdict']);
        $this->assertTrue($analysis['verdict_overridden']);
    }

    public function test_rejects_a_gate_that_is_truthy_but_not_true(): void
    {
        $gates = ['callers_checked' => 'yes'] + self::ALL_GATES_PASS;

        $analysis = (new TriageResultParser())->parse($this->envelope($this->analysis('fixable', 'high', $gates)));

        $this->assertSame('blocked', $analysis['verdict']);
    }

    public function test_throws_when_claude_run_errored(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OAuth session expired');

        (new TriageResultParser())->parse([
            'is_error' => true,
            'result' => 'Failed to authenticate: OAuth session expired and could not be refreshed',
        ]);
    }

    public function test_throws_when_result_has_no_json_block(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no ```json block');

        (new TriageResultParser())->parse(['result' => 'I stopped at Step 0.']);
    }

    public function test_throws_on_invalid_verdict(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid verdict');

        (new TriageResultParser())->parse($this->envelope($this->analysis('maybe', 'high')));
    }

    private function envelope(array $analysis): array
    {
        return ['is_error' => false, 'result' => "```json\n" . json_encode([$analysis]) . "\n```"];
    }

    private function analysis(string $verdict, string $confidence, array $gates = self::ALL_GATES_PASS): array
    {
        return [
            'ticket' => 'PRD-6526',
            'summary' => 'Image-only messages do not open the conversation.',
            'gates' => $gates,
            'verdict' => $verdict,
            'confidence' => $confidence,
            'planned_fix' => 'Change the condition',
            'open_questions' => [],
        ];
    }
}
