<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class TicketGeneratorService
{
    private const SYSTEM_PROMPT = 'You are a technical assistant that converts emails into structured tickets.'
        . ' You MUST output valid JSON and nothing else.'
        . ' The output MUST match the provided schema exactly.';

    private const SPIKE_PROMPT = 'Create a SPIKE ticket from the email.'
        . ' Summary format: [Spike][Integration] Short question.'
        . ' Description MUST include sections in this exact order: Context, Goals, Reservation Status Mapping, Field Mapping,'
        . ' Questions to Answer, Unknowns, References.'
        . ' Goals MUST mention PMS integration and Campaign Manager compatibility.'
        . ' If PMS supports webhooks, Goals MUST mention webhook support and Campaign Manager events; if not, do not mention'
        . ' webhooks or events.'
        . ' Reservation Status Mapping MUST list only statuses found in the documentation and state if mapping is partial or complete.'
        . ' Field Mapping MUST list available required fields and explicitly mention missing fields, based only on the documentation.'
        . ' References MUST mention the uploaded PMS API documentation PDF (e.g., "Based on the PMS API documentation provided (PDF)").'
        . ' Rules: Focus on questions. Do NOT propose solutions. No acceptance criteria.'
        . ' Do NOT assume webhook support. Do NOT invent statuses or fields. Do NOT generalize goals.'
        . ' Output must be valid JSON with keys: summary, description, labels.'
        . ' No markdown or additional text.';

    private const TASK_PROMPT = 'Create a TASK ticket from the email.'
        . ' Summary format: [Task][Integration] Clear action.'
        . ' Description MUST include sections in this exact order: Context, Goals, Reservation Status Mapping, Field Mapping,'
        . ' Expected Behavior, Acceptance Criteria, References.'
        . ' Goals MUST mention PMS integration and Campaign Manager compatibility.'
        . ' If PMS supports webhooks, Goals MUST mention webhook support and Campaign Manager events; if not, do not mention'
        . ' webhooks or events.'
        . ' Reservation Status Mapping MUST list only statuses found in the documentation and state if mapping is partial or complete.'
        . ' Field Mapping MUST list available required fields and explicitly mention missing fields, based only on the documentation.'
        . ' References MUST mention the uploaded PMS API documentation PDF (e.g., "Based on the PMS API documentation provided (PDF)").'
        . ' Rules: Must be implementable. No open questions.'
        . ' Do NOT assume webhook support. Do NOT invent statuses or fields. Do NOT generalize goals.'
        . ' Output must be valid JSON with keys: summary, description, labels.'
        . ' No markdown or additional text.';

    public function __construct(private OpenAiService $openAiService)
    {
    }

    public function fromEmail(array $payload): array
    {
        $type = $payload['type'] ?? null;
        if (!is_string($type)) {
            throw new InvalidArgumentException('Ticket type is required.');
        }

        $type = strtolower($type);
        if (!in_array($type, ['task', 'spike'], true)) {
            throw new InvalidArgumentException('Ticket type must be task or spike.');
        }

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

        $userPrompt = $this->buildUserPrompt(
            $type,
            $subject,
            $from,
            $body,
            $threadUrl
        );

        $output = $this->openAiService->generateTicket(self::SYSTEM_PROMPT, $userPrompt);


        Log::info('OpenAI output: ' . json_encode($output));
        $this->validateOutput($output, $type);

        return $output;
    }

    public function fromManual(array $payload): array
    {
        $type = $payload['type'] ?? null;
        if (!is_string($type)) {
            throw new InvalidArgumentException('Ticket type is required.');
        }

        $type = strtolower($type);
        if (!in_array($type, ['task', 'spike'], true)) {
            throw new InvalidArgumentException('Ticket type must be task or spike.');
        }

        $summary = $payload['summary'] ?? null;
        $description = $payload['description'] ?? null;

        if (!is_string($summary) || trim($summary) === '') {
            throw new InvalidArgumentException('Summary is required.');
        }
        if (!is_string($description) || trim($description) === '') {
            throw new InvalidArgumentException('Description is required.');
        }

        return [
            'summary' => $summary,
            'description' => $description,
            'labels' => [],
        ];
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

    private function validateOutput(array $output, string $type): void
    {
        foreach (['summary', 'description', 'labels'] as $field) {
            if (!array_key_exists($field, $output)) {
                throw new InvalidArgumentException("OpenAI output missing required field: {$field}.");
            }
        }

        if (!is_string($output['summary']) || trim($output['summary']) === '') {
            throw new InvalidArgumentException('OpenAI output summary must be a non-empty string.');
        }
        if (!is_string($output['description']) || trim($output['description']) === '') {
            throw new InvalidArgumentException('OpenAI output description must be a non-empty string.');
        }
        if (!is_array($output['labels'])) {
            throw new InvalidArgumentException('OpenAI output labels must be an array.');
        }

        foreach ($output['labels'] as $label) {
            if (!is_string($label) || trim($label) === '') {
                throw new InvalidArgumentException('OpenAI output labels must be non-empty strings.');
            }
        }

        $summaryPrefix = $type === 'spike' ? '[Spike][Integration]' : '[Task][Integration]';
        if (strpos($output['summary'], $summaryPrefix) !== 0) {
            throw new InvalidArgumentException("OpenAI output summary must start with {$summaryPrefix}.");
        }

        $requiredSections = $type === 'spike'
            ? ['Context', 'Questions to Answer', 'Unknowns', 'References']
            : ['Context', 'Expected Behavior', 'Acceptance Criteria', 'References'];

        foreach ($requiredSections as $section) {
            if (strpos($output['description'], $section) === false) {
                throw new InvalidArgumentException("OpenAI output description missing section: {$section}.");
            }
        }
    }
}
