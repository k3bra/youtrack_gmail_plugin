<?php

namespace App\Services;

use InvalidArgumentException;

class PmsDocumentAnalysisService
{
    public function __construct(private OpenAiService $openAiService)
    {
    }

    public function analyze(string $text, bool $isBookingEngine = false): array
    {
        if (trim($text) === '') {
            throw new InvalidArgumentException('Document text is empty.');
        }

        if ($isBookingEngine) {
            $userPrompt = $this->getPrompt('booking_engine_prompt') . "\n\nDocumentation Text:\n" . $text;
            $output = $this->openAiService->requestJson($this->getPrompt('system_prompt'), $userPrompt);

            \Log::info('OpenAI output: ' . json_encode($output));
            $this->validateBookingEngineOutput($output);
            $this->normalizeOptionalFields($output, $this->getAvailabilityFieldLabels($output['availability_fields'] ?? []));

            return $output;
        }

        $userPrompt = $this->getPrompt('pms_prompt') . "\n\nDocumentation Text:\n" . $text;

        $output = $this->openAiService->requestJson($this->getPrompt('system_prompt'), $userPrompt);

        \Log::info('OpenAI output: ' . json_encode($output));
        $this->validatePmsOutput($output);
        $this->normalizeOptionalFields($output, $this->getRequiredFieldLabels($output['fields'] ?? []));

        return $output;
    }

    public function analyzeExample(string $text, bool $isBookingEngine = false): array
    {
        if (trim($text) === '') {
            throw new InvalidArgumentException('Document text is empty.');
        }

        if ($isBookingEngine) {
            return [
                'get_reservations_example' => [
                    'format' => null,
                    'payload' => null,
                ],
            ];
        }

        $userPrompt = $this->getPrompt('example_prompt') . "\n\nDocumentation Text:\n" . $text;

        $output = $this->openAiService->requestJson($this->getPrompt('system_prompt'), $userPrompt);

        \Log::info('OpenAI example output: ' . json_encode($output));
        $this->validateExampleOutput($output);

        return $output;
    }

    private function validatePmsOutput(array $output): void
    {
        $this->assertExactKeys($output, [
            'has_get_reservations_endpoint',
            'get_reservations_endpoint',
            'supports_webhooks',
            'webhook_details',
            'fields',
            'optional_fields',
            'notes',
        ], 'root');

        $this->assertBoolean($output['has_get_reservations_endpoint'], 'has_get_reservations_endpoint');
        $this->assertBoolean($output['supports_webhooks'], 'supports_webhooks');
        $this->assertNullableString($output['get_reservations_endpoint'], 'get_reservations_endpoint');
        $this->assertNullableString($output['webhook_details'], 'webhook_details');
        $this->assertStringArray($output['optional_fields'], 'optional_fields');
        $this->assertStringArray($output['notes'], 'notes');

        if ($output['has_get_reservations_endpoint'] === false && $output['get_reservations_endpoint'] !== null) {
            throw new InvalidArgumentException('get_reservations_endpoint must be null when has_get_reservations_endpoint is false.');
        }

        if ($output['supports_webhooks'] === false && $output['webhook_details'] !== null) {
            throw new InvalidArgumentException('webhook_details must be null when supports_webhooks is false.');
        }

        if (!is_array($output['fields'])) {
            throw new InvalidArgumentException('fields must be an object.');
        }

        $fields = $output['fields'];
        $this->assertExactKeys($fields, [
            'check_in_date',
            'checkout_date',
            'first_name',
            'last_name',
            'reservation_id',
            'mobile_phone',
            'email',
            'reservation_status',
        ], 'fields');

        foreach ([
            'check_in_date',
            'checkout_date',
            'first_name',
            'last_name',
            'reservation_id',
            'mobile_phone',
            'email',
        ] as $field) {
            $this->assertField($fields[$field], "fields.{$field}");
        }

        if (!is_array($fields['reservation_status'])) {
            throw new InvalidArgumentException('fields.reservation_status must be an object.');
        }

        $reservationStatus = $fields['reservation_status'];
        $this->assertExactKeys($reservationStatus, ['available', 'source_label', 'values'], 'fields.reservation_status');
        $this->assertBoolean($reservationStatus['available'], 'fields.reservation_status.available');
        $this->assertNullableString($reservationStatus['source_label'], 'fields.reservation_status.source_label');
        $this->assertStringArray($reservationStatus['values'], 'fields.reservation_status.values');

        if ($reservationStatus['available'] === false && $reservationStatus['values'] !== []) {
            throw new InvalidArgumentException('fields.reservation_status.values must be empty when unavailable.');
        }

        if ($reservationStatus['available'] === false && $reservationStatus['source_label'] !== null) {
            throw new InvalidArgumentException('fields.reservation_status.source_label must be null when unavailable.');
        }

        if ($reservationStatus['available'] === true && $reservationStatus['source_label'] === null) {
            throw new InvalidArgumentException('fields.reservation_status.source_label must be set when available.');
        }
    }

    private function validateBookingEngineOutput(array $output): void
    {
        $this->assertExactKeys($output, [
            'has_get_availability_endpoint',
            'get_availability_endpoint',
            'availability_fields',
            'optional_fields',
            'notes',
        ], 'root');

        $this->assertBoolean($output['has_get_availability_endpoint'], 'has_get_availability_endpoint');
        $this->assertNullableString($output['get_availability_endpoint'], 'get_availability_endpoint');
        $this->assertStringArray($output['optional_fields'], 'optional_fields');
        $this->assertStringArray($output['notes'], 'notes');

        if ($output['has_get_availability_endpoint'] === false && $output['get_availability_endpoint'] !== null) {
            throw new InvalidArgumentException('get_availability_endpoint must be null when has_get_availability_endpoint is false.');
        }

        if (!is_array($output['availability_fields'])) {
            throw new InvalidArgumentException('availability_fields must be an object.');
        }

        $availabilityFields = $output['availability_fields'];
        $this->assertExactKeys($availabilityFields, [
            'room_description',
            'room_image',
            'price',
            'currency',
        ], 'availability_fields');

        foreach ([
            'room_description',
            'room_image',
            'price',
            'currency',
        ] as $field) {
            $this->assertField($availabilityFields[$field], "availability_fields.{$field}");
        }
    }

    private function getPrompt(string $key): string
    {
        $prompt = config("pms_analysis.{$key}");

        if (!is_string($prompt) || trim($prompt) === '') {
            throw new InvalidArgumentException("Prompt config missing: {$key}");
        }

        return $prompt;
    }

    private function normalizeOptionalFields(array &$output, array $requiredLabels): void
    {
        $optional = $output['optional_fields'] ?? [];
        if (!is_array($optional)) {
            $output['optional_fields'] = [];
            return;
        }

        $requiredNormalized = [];

        foreach ($requiredLabels as $label) {
            $normalized = $this->normalizeLabel($label);
            if ($normalized !== '') {
                $requiredNormalized[$normalized] = true;
            }
        }

        $filtered = [];

        foreach ($optional as $value) {
            if (!is_string($value)) {
                continue;
            }
            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }
            $normalized = $this->normalizeLabel($trimmed);
            if ($normalized !== '' && isset($requiredNormalized[$normalized])) {
                continue;
            }
            $filtered[$trimmed] = true;
        }

        $output['optional_fields'] = array_values(array_keys($filtered));
    }

    private function getRequiredFieldLabels(array $fields): array
    {
        $labels = [];
        $requiredKeys = [
            'check_in_date',
            'checkout_date',
            'first_name',
            'last_name',
            'reservation_id',
            'mobile_phone',
            'email',
            'reservation_status',
        ];

        foreach ($requiredKeys as $key) {
            $entry = $fields[$key] ?? null;
            if (!is_array($entry)) {
                continue;
            }
            $label = $entry['source_label'] ?? null;
            if (is_string($label) && $label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    private function getAvailabilityFieldLabels(array $fields): array
    {
        $labels = [];
        $requiredKeys = [
            'room_description',
            'room_image',
            'price',
            'currency',
        ];

        foreach ($requiredKeys as $key) {
            $entry = $fields[$key] ?? null;
            if (!is_array($entry)) {
                continue;
            }
            $label = $entry['source_label'] ?? null;
            if (is_string($label) && $label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    private function normalizeLabel(string $value): string
    {
        $cleaned = strtolower($value);
        $cleaned = preg_replace('/^[^a-z0-9]+/i', '', $cleaned);
        $cleaned = preg_replace('/[^a-z0-9]+/i', '', $cleaned);

        return is_string($cleaned) ? $cleaned : '';
    }

    private function assertExactKeys(array $payload, array $keys, string $context): void
    {
        $payloadKeys = array_keys($payload);
        sort($payloadKeys);
        $expected = $keys;
        sort($expected);

        if ($payloadKeys !== $expected) {
            throw new InvalidArgumentException("{$context} keys do not match required schema.");
        }
    }

    private function assertBoolean(mixed $value, string $field): void
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException("{$field} must be a boolean.");
        }
    }

    private function assertNullableString(mixed $value, string $field): void
    {
        if ($value === null) {
            return;
        }

        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("{$field} must be a non-empty string or null.");
        }
    }

    private function assertStringArray(mixed $value, string $field): void
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("{$field} must be an array.");
        }

        foreach ($value as $entry) {
            if (!is_string($entry) || trim($entry) === '') {
                throw new InvalidArgumentException("{$field} entries must be non-empty strings.");
            }
        }
    }

    private function assertExample(mixed $value, string $field): void
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("{$field} must be an object.");
        }

        $this->assertExactKeys($value, ['format', 'payload'], $field);

        $this->assertNullableString($value['format'], "{$field}.format");

        if ($value['payload'] !== null && (!is_string($value['payload']) || trim($value['payload']) === '')) {
            throw new InvalidArgumentException("{$field}.payload must be a non-empty string or null.");
        }

        if ($value['payload'] === null && $value['format'] !== null) {
            throw new InvalidArgumentException("{$field}.format must be null when payload is null.");
        }

        if ($value['payload'] !== null && $value['format'] === null) {
            throw new InvalidArgumentException("{$field}.format must be set when payload is provided.");
        }
    }

    private function validateExampleOutput(array $output): void
    {
        $this->assertExactKeys($output, ['get_reservations_example'], 'root');
        $this->assertExample($output['get_reservations_example'], 'get_reservations_example');
    }

    private function assertField(mixed $value, string $field): void
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("{$field} must be an object.");
        }

        $this->assertExactKeys($value, ['available', 'source_label'], $field);
        $this->assertBoolean($value['available'], "{$field}.available");
        $this->assertNullableString($value['source_label'], "{$field}.source_label");

        if ($value['available'] === false && $value['source_label'] !== null) {
            throw new InvalidArgumentException("{$field}.source_label must be null when unavailable.");
        }

        if ($value['available'] === true && $value['source_label'] === null) {
            throw new InvalidArgumentException("{$field}.source_label must be set when available.");
        }
    }
}
