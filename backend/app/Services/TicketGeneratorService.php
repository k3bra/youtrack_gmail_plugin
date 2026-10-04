<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class TicketGeneratorService
{
    private const SYSTEM_PROMPT = 'You turn emails received by the HiJiffy product team into clear, actionable YouTrack tickets.'
        . ' Write in English using concise Markdown.'
        . ' Use only facts stated in the email: never invent causes, fields, statuses, requirements or documentation.'
        . ' When something is unknown, say so explicitly instead of guessing.'
        . ' Ignore greetings, sign-offs, signatures and legal disclaimers.'
        . ' Keep every relevant link, ID, client name, example and step from the email.';

    private const TASK_PROMPT = 'Create a TASK ticket from the email below.'
        . ' Summary: one short sentence (max 100 characters) stating the work to be done,'
        . ' including the client name and ID when the email mentions them.'
        . ' Description: use exactly these Markdown headings, in this order:'
        . ' "## Context" (who reported it, affected client and feature),'
        . ' "## Problem" (what is wrong or what is being requested),'
        . ' "## Evidence" (bullet list of links, IDs, examples and steps from the email, or "None provided."),'
        . ' "## Expected Behavior" (what should happen instead),'
        . ' "## Acceptance Criteria" (checklist of verifiable outcomes, each line starting with "- [ ] ").'
        . ' Labels: up to 3 short lowercase topic labels, such as the integration or feature involved.';

    private const SPIKE_PROMPT = 'Create a SPIKE (investigation) ticket from the email below.'
        . ' Summary: one short question or investigation topic (max 100 characters),'
        . ' including the client name and ID when the email mentions them.'
        . ' Description: use exactly these Markdown headings, in this order:'
        . ' "## Context" (who reported it, affected client and feature),'
        . ' "## Questions to Answer" (bullet list of the concrete questions the investigation must answer),'
        . ' "## Known Facts" (bullet list of what the email establishes),'
        . ' "## Unknowns" (bullet list of what is not yet known),'
        . ' "## References" (bullet list of links and IDs from the email, or "None provided.").'
        . ' Focus on questions: do not propose solutions and do not add acceptance criteria.'
        . ' Labels: up to 3 short lowercase topic labels, such as the integration or feature involved.';

    private const REQUIRED_SECTIONS = [
        'task' => ['Context', 'Problem', 'Evidence', 'Expected Behavior', 'Acceptance Criteria'],
        'spike' => ['Context', 'Questions to Answer', 'Known Facts', 'Unknowns', 'References'],
    ];

    private const OUTPUT_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'summary' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'labels' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
        ],
        'required' => ['summary', 'description', 'labels'],
        'additionalProperties' => false,
    ];

    private const MAX_LABELS = 3;

    public function __construct(private OpenAiService $openAiService)
    {
    }

    public function fromEmail(array $payload): array
    {
        $type = $this->normalizeType($payload['type'] ?? null);

        $email = $payload['email'] ?? null;
        if (!is_array($email)) {
            throw new InvalidArgumentException('Email payload is required.');
        }

        $subject = $email['subject'] ?? null;
        $from = $email['from'] ?? null;
        $body = $email['body'] ?? null;
        $threadUrl = $email['threadUrl'] ?? null;

        if (!is_string($subject)) {
            throw new InvalidArgumentException('Email subject is required.');
        }
        if (!is_string($body)) {
            throw new InvalidArgumentException('Email body is required.');
        }
        $from = is_string($from) ? $from : '';
        $threadUrl = is_string($threadUrl) ? $threadUrl : '';

        $userPrompt = $this->buildUserPrompt($type, $subject, $from, $body, $threadUrl);

        $output = $this->openAiService->requestStructured(
            self::SYSTEM_PROMPT,
            $userPrompt,
            'youtrack_ticket',
            self::OUTPUT_SCHEMA
        );

        Log::info('OpenAI output: ' . json_encode($output));

        $output = $this->normalizeOutput($output);
        $this->validateOutput($output, $type);

        return $output;
    }

    public function fromManual(array $payload): array
    {
        $this->normalizeType($payload['type'] ?? null);

        $summary = $payload['summary'] ?? null;
        $description = $payload['description'] ?? null;

        if (!is_string($summary) || trim($summary) === '') {
            throw new InvalidArgumentException('Summary is required.');
        }
        if (!is_string($description) || trim($description) === '') {
            throw new InvalidArgumentException('Description is required.');
        }

        return [
            'summary' => trim($summary),
            'description' => trim($description),
            'labels' => $this->normalizeLabels($payload['labels'] ?? []),
        ];
    }

    private function normalizeType(mixed $type): string
    {
        if (!is_string($type)) {
            throw new InvalidArgumentException('Ticket type is required.');
        }

        $type = strtolower($type);
        if (!in_array($type, ['task', 'spike'], true)) {
            throw new InvalidArgumentException('Ticket type must be task or spike.');
        }

        return $type;
    }

    private function buildUserPrompt(
        string $type,
        string $subject,
        string $from,
        string $body,
        string $threadUrl
    ): string {
        $prompt = $type === 'spike' ? self::SPIKE_PROMPT : self::TASK_PROMPT;

        $emailBlock = "Subject:\n{$subject}\n\n"
            . "From:\n{$from}\n\n"
            . "Body:\n{$body}\n\n"
            . "Thread URL:\n{$threadUrl}";

        return $prompt . "\n\n" . $emailBlock;
    }

    /**
     * Structured outputs should always return a string description, but if the model
     * returns sections as an object we render them as Markdown instead of discarding them.
     */
    private function normalizeOutput(array $output): array
    {
        if (is_array($output['description'] ?? null)) {
            $sections = [];
            foreach ($output['description'] as $heading => $content) {
                $text = is_array($content) ? implode("\n", array_map('strval', $content)) : (string) $content;
                $sections[] = '## ' . $heading . "\n" . trim($text);
            }
            $output['description'] = implode("\n\n", $sections);
        }

        if (is_string($output['summary'] ?? null)) {
            $output['summary'] = trim($output['summary']);
        }
        if (is_string($output['description'] ?? null)) {
            $output['description'] = trim($output['description']);
        }

        $output['labels'] = $this->normalizeLabels($output['labels'] ?? []);

        return $output;
    }

    private function normalizeLabels(mixed $labels): array
    {
        if (!is_array($labels)) {
            return [];
        }

        $normalized = [];
        foreach ($labels as $label) {
            if (is_string($label) && trim($label) !== '') {
                $normalized[] = strtolower(trim($label));
            }
        }

        return array_slice(array_values(array_unique($normalized)), 0, self::MAX_LABELS);
    }

    private function validateOutput(array $output, string $type): void
    {
        if (!is_string($output['summary'] ?? null) || $output['summary'] === '') {
            throw new RuntimeException('AI did not generate a ticket summary. Please try again.');
        }
        if (!is_string($output['description'] ?? null) || $output['description'] === '') {
            throw new RuntimeException('AI did not generate a ticket description. Please try again.');
        }

        $missing = array_filter(
            self::REQUIRED_SECTIONS[$type],
            static fn (string $section): bool => stripos($output['description'], $section) === false
        );

        if ($missing !== []) {
            throw new RuntimeException(
                'AI description is missing sections: ' . implode(', ', $missing) . '. Please try again.'
            );
        }
    }
}
