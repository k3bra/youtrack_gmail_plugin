<?php

namespace App\Services;

use InvalidArgumentException;

class PmsDocumentAnalysisService
{
    private const SYSTEM_PROMPT = 'You analyze PMS API documentation.'
        . ' Analyze only the provided text.'
        . ' Do not invent features that are not supported by the text.'
        . ' Use reasonable inference from labels, examples, and common abbreviations to map fields.'
        . ' If information is missing entirely, mark it as false.'
        . ' Output must be valid JSON and nothing else.';

    private const USER_PROMPT_INTRO = 'Review the documentation and return JSON matching this schema exactly:'
        . ' {'
        . '"has_get_reservations_endpoint": boolean,'
        . '"get_reservations_endpoint": string | null,'
        . '"has_get_availability_endpoint": boolean,'
        . '"get_availability_endpoint": string | null,'
        . '"supports_webhooks": boolean,'
        . '"webhook_details": string | null,'
        . '"fields": {'
        . '"check_in_date": {'
        . '"available": boolean,'
        . '"source_label": string | null'
        . '},'
        . '"checkout_date": {'
        . '"available": boolean,'
        . '"source_label": string | null'
        . '},'
        . '"first_name": {'
        . '"available": boolean,'
        . '"source_label": string | null'
        . '},'
        . '"last_name": {'
        . '"available": boolean,'
        . '"source_label": string | null'
        . '},'
        . '"reservation_id": {'
        . '"available": boolean,'
        . '"source_label": string | null'
        . '},'
        . '"mobile_phone": {'
        . '"available": boolean,'
        . '"source_label": string | null'
        . '},'
        . '"email": {'
        . '"available": boolean,'
        . '"source_label": string | null'
        . '},'
        . '"reservation_status": {'
        . '"available": boolean,'
        . '"source_label": string | null,'
        . '"values": string[]'
        . '}'
        . '},'
        . '"availability_fields": {'
        . '"room_name": {'
        . '"available": boolean,'
        . '"source_label": string | null'
        . '},'
        . '"room_image": {'
        . '"available": boolean,'
        . '"source_label": string | null'
        . '},'
        . '"price": {'
        . '"available": boolean,'
        . '"source_label": string | null'
        . '},'
        . '"currency": {'
        . '"available": boolean,'
        . '"source_label": string | null'
        . '}'
        . '},'
        . '"optional_fields": string[],'
        . '"notes": string[]'
        . ' }'
        . ' Rules:'
        . ' - If a GET reservations endpoint is not explicitly documented, set has_get_reservations_endpoint to false.'
        . ' - Booking engine mode will be provided; when booking engine mode is false, set has_get_availability_endpoint'
        . ' to false, get_availability_endpoint to null, and all availability_fields.available to false.'
        . ' - If a GET availability endpoint is not explicitly documented, set has_get_availability_endpoint to false.'
        . ' - If any field is not documented or inferable from labels/examples, set available to false and source_label to null.'
        . ' - If reservation status values are not listed, set values to [].'
        . ' - If has_get_reservations_endpoint is false, set get_reservations_endpoint to null.'
        . ' - If has_get_availability_endpoint is false, set get_availability_endpoint to null.'
        . ' - If supports_webhooks is false, set webhook_details to null.'
        . ' - If available is false, source_label must be null.'
        . ' - If a field appears as a key in a response example payload, treat that as documented and use the key as source_label.'
        . ' - Match labels case-insensitively and ignore separators like spaces, underscores, and hyphens.'
        . ' - Ignore leading or trailing punctuation/symbols such as "@", "#", "*", "-", "•", ":", and "." when matching labels.'
        . ' - Labels may appear with a prefix symbol (e.g., "@telefono"). Match them without the symbol, but keep the exact original'
        . ' label (including the prefix) in source_label.'
        . ' - optional_fields must list any other reservation fields documented (in response examples, field tables, or'
        . ' parameter lists) that are not part of the required fields list; use the exact labels or keys as shown.'
        . ' - Include common reservation metadata fields when documented, such as "modified_date_time", "create_date_time",'
        . ' "currency", "room_type_id", "room_type_name", "room_id", or "room_name" (use the exact label from the document).'
        . ' - If the response example contains keys not in the required fields list, include those keys in optional_fields.'
        . ' - Do not include required fields or reservation status in optional_fields.'
        . ' - If no additional reservation fields are documented, set optional_fields to [].'
        . ' - If a label is not in the explicit list but clearly refers to the same concept (abbreviation,'
        . ' language variant, or common shorthand), map it to the closest field and use the exact label as source_label.'
        . ' - When a label is ambiguous but reasonably likely to match, prefer mapping it rather than leaving it unavailable.'
        . ' - Identify labels in any language. Map common variants:'
        . ' first_name can be labeled "first name", "name", "name1", "apelido1", or "nombre";'
        . ' last_name can be labeled "last name", "name2", "apelido2", "apellido", or "surname";'
        . ' mobile_phone can be labeled "phone", "telephone", "tel", "telefono", "teléfono", "movil", "móvil", or "celular";'
        . ' check_in_date can be labeled "check-in", "check in", "checkin", "check in date", "check-in date",'
        . ' "check_in", "check_in_date", "checkindate", "arrival", "arrival date", or "fecha de entrada";'
        . ' checkout_date can be labeled "check-out", "check out", "checkout", "check out date", "check-out date",'
        . ' "check_out", "check_out_date", "checkoutdate", "departure", "departure date", or "fecha de salida";'
        . ' reservation_id can be labeled "reservation id", "reservation number", "booking id", "booking number",'
        . ' "id de reserva", or "numero de reserva";'
        . ' email can be labeled "email", "e-mail", "correo", "correo electronico", "correo electrónico", or "mail";'
        . ' reservation_status can be labeled "status", "reservation status", "reservation_status", "state", "estado", or "situacao";'
        . ' room_name can be labeled "room name", "room_name", "room", "room description", "description", "room_type_name",'
        . ' or "room type";'
        . ' room_image can be labeled "room image", "room_image", "image", "image_url", "photo", or "room_photo";'
        . ' price can be labeled "price", "rate", "amount", "room_price", "price_per_night", or "total_price";'
        . ' currency can be labeled "currency", "currency_code", "currency_iso", or "iso_currency";'
        . ' - For source_label, return the exact label text from the documentation.'
        . ' Respond with JSON only.';

    private const EXAMPLE_PROMPT_INTRO = 'Review the documentation and return JSON matching this schema exactly:'
        . ' {'
        . '"get_reservations_example": {'
        . '"format": string | null,'
        . '"payload": string | null'
        . '}'
        . ' }'
        . ' Rules:'
        . ' - If a GET reservations response example is documented, include a successful response example in get_reservations_example.'
        . ' - If multiple examples are documented, prefer the success response and ignore error examples.'
        . ' - get_reservations_example.format must be a lowercase format label when payload is provided.'
        . ' - Use "json" or "xml" when the example is clearly JSON or XML; otherwise use the documented format name (e.g., "yaml", "text").'
        . ' - If no example is documented, set get_reservations_example.format and payload to null.'
        . ' - Return the payload exactly as shown (no edits) when possible.'
        . ' Respond with JSON only.';

    public function __construct(private OpenAiService $openAiService)
    {
    }

    public function analyze(string $text, bool $isBookingEngine = false): array
    {
        if (trim($text) === '') {
            throw new InvalidArgumentException('Document text is empty.');
        }

        $modeLine = $isBookingEngine ? 'Booking engine mode: true.' : 'Booking engine mode: false.';
        $userPrompt = self::USER_PROMPT_INTRO . "\n\n" . $modeLine . "\n\nDocumentation Text:\n" . $text;

        $output = $this->openAiService->requestJson(self::SYSTEM_PROMPT, $userPrompt);

        \Log::info('OpenAI output: ' . json_encode($output));
        $this->validateOutput($output);
        $this->normalizeOptionalFields($output);
        $this->normalizeBookingEngineOutput($output, $isBookingEngine);

        return $output;
    }

    public function analyzeExample(string $text): array
    {
        if (trim($text) === '') {
            throw new InvalidArgumentException('Document text is empty.');
        }

        $userPrompt = self::EXAMPLE_PROMPT_INTRO . "\n\nDocumentation Text:\n" . $text;

        $output = $this->openAiService->requestJson(self::SYSTEM_PROMPT, $userPrompt);

        \Log::info('OpenAI example output: ' . json_encode($output));
        $this->validateExampleOutput($output);

        return $output;
    }

    private function validateOutput(array $output): void
    {
        $this->assertExactKeys($output, [
            'has_get_reservations_endpoint',
            'get_reservations_endpoint',
            'has_get_availability_endpoint',
            'get_availability_endpoint',
            'supports_webhooks',
            'webhook_details',
            'fields',
            'availability_fields',
            'optional_fields',
            'notes',
        ], 'root');

        $this->assertBoolean($output['has_get_reservations_endpoint'], 'has_get_reservations_endpoint');
        $this->assertBoolean($output['has_get_availability_endpoint'], 'has_get_availability_endpoint');
        $this->assertBoolean($output['supports_webhooks'], 'supports_webhooks');
        $this->assertNullableString($output['get_reservations_endpoint'], 'get_reservations_endpoint');
        $this->assertNullableString($output['get_availability_endpoint'], 'get_availability_endpoint');
        $this->assertNullableString($output['webhook_details'], 'webhook_details');
        $this->assertStringArray($output['optional_fields'], 'optional_fields');
        $this->assertStringArray($output['notes'], 'notes');

        if ($output['has_get_reservations_endpoint'] === false && $output['get_reservations_endpoint'] !== null) {
            throw new InvalidArgumentException('get_reservations_endpoint must be null when has_get_reservations_endpoint is false.');
        }

        if ($output['has_get_availability_endpoint'] === false && $output['get_availability_endpoint'] !== null) {
            throw new InvalidArgumentException('get_availability_endpoint must be null when has_get_availability_endpoint is false.');
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

        if (!is_array($output['availability_fields'])) {
            throw new InvalidArgumentException('availability_fields must be an object.');
        }

        $availabilityFields = $output['availability_fields'];
        $this->assertExactKeys($availabilityFields, [
            'room_name',
            'room_image',
            'price',
            'currency',
        ], 'availability_fields');

        foreach ([
            'room_name',
            'room_image',
            'price',
            'currency',
        ] as $field) {
            $this->assertField($availabilityFields[$field], "availability_fields.{$field}");
        }
    }

    private function normalizeOptionalFields(array &$output): void
    {
        $optional = $output['optional_fields'] ?? [];
        if (!is_array($optional)) {
            $output['optional_fields'] = [];
            return;
        }

        $requiredLabels = $this->getRequiredFieldLabels($output['fields'] ?? []);
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

    private function normalizeBookingEngineOutput(array &$output, bool $isBookingEngine): void
    {
        if ($isBookingEngine) {
            return;
        }

        $output['has_get_availability_endpoint'] = false;
        $output['get_availability_endpoint'] = null;

        if (!is_array($output['availability_fields'] ?? null)) {
            $output['availability_fields'] = [];
        }

        foreach (['room_name', 'room_image', 'price', 'currency'] as $key) {
            $output['availability_fields'][$key] = [
                'available' => false,
                'source_label' => null,
            ];
        }
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
