<?php

namespace Tests\Feature;

use App\Models\TicketRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TicketAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_KEY = 'test-client-key';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Storage::fake('local');

        config([
            'tickets.client_key' => self::CLIENT_KEY,
            'tickets.youtrack_base_url' => 'https://youtrack.test',
            'tickets.youtrack_token' => 'test-youtrack-token',
            'tickets.youtrack_project_id' => 'PRD',
        ]);
    }

    public function test_image_chunks_are_reassembled_and_uploaded_to_youtrack(): void
    {
        $this->createdTicket('PRD-1');
        Http::fake(['youtrack.test/*' => Http::response(['id' => '8-1', 'name' => 'screenshot.png'])]);

        $image = $this->pngBytes(1500 * 1024);
        $chunks = str_split($image, 1024 * 1024);
        $uploadId = (string) Str::uuid();

        foreach ($chunks as $index => $chunk) {
            $response = $this->postChunk('PRD-1', $uploadId, 'my screenshot!.png', $index, count($chunks), $chunk);
        }

        $response->assertOk()->assertExactJson(['done' => true, 'name' => 'my_screenshot_.png']);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($image): bool {
            return $request->url() === 'https://youtrack.test/api/issues/PRD-1/attachments?fields=id,name'
                && $request->isMultipart()
                && $request->data()[0]['name'] === 'file'
                && $request->data()[0]['filename'] === 'my_screenshot_.png'
                && $request->data()[0]['contents'] === $image;
        });
        $this->assertSame([], Storage::disk('local')->allFiles('attachment-chunks'));
    }

    public function test_partial_upload_waits_for_remaining_chunks(): void
    {
        $this->createdTicket('PRD-1');
        Http::fake();

        $this->postChunk('PRD-1', (string) Str::uuid(), 'a.png', 0, 2, $this->pngBytes(100))
            ->assertOk()
            ->assertExactJson(['done' => false]);

        Http::assertNothingSent();
    }

    public function test_attachments_are_only_allowed_on_tickets_recently_created_by_the_app(): void
    {
        Http::fake();
        TicketRequest::create($this->ticketRecord('PRD-2'))
            ->forceFill(['created_at' => now()->subHours(2)])
            ->save();

        $this->postChunk('PRD-3', (string) Str::uuid(), 'a.png', 0, 1, $this->pngBytes(100))->assertForbidden();
        $this->postChunk('PRD-2', (string) Str::uuid(), 'a.png', 0, 1, $this->pngBytes(100))->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_non_image_content_is_rejected(): void
    {
        $this->createdTicket('PRD-1');
        Http::fake();

        $this->postChunk('PRD-1', (string) Str::uuid(), 'evil.png', 0, 1, "<?php echo 'hi';")
            ->assertStatus(400)
            ->assertJson(['error' => 'Only PNG, JPEG, GIF, WebP and BMP images can be attached.']);

        Http::assertNothingSent();
    }

    public function test_oversized_chunk_is_rejected(): void
    {
        $this->createdTicket('PRD-1');
        Http::fake();

        $this->postChunk('PRD-1', (string) Str::uuid(), 'a.png', 0, 1, str_repeat('a', 1024 * 1024 + 1))
            ->assertStatus(400)
            ->assertJson(['error' => 'Chunk data is invalid or too large.']);
    }

    public function test_finalize_embeds_uploaded_images_in_description(): void
    {
        $this->createdTicket('PRD-1');
        Http::fake(function (Request $request) {
            return $request->method() === 'GET'
                ? Http::response(['description' => "## Context\nSomething broke.\n"])
                : Http::response(['id' => '2-1']);
        });

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/PRD-1/attachments/finalize', ['names' => ['image-1.png', 'error.jpg']])
            ->assertOk();

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://youtrack.test/api/issues/PRD-1?fields=id'
                && $request['description'] === "## Context\nSomething broke.\n\n## Attachments\n![](image-1.png)\n![](error.jpg)";
        });
    }

    public function test_finalize_rejects_unsafe_names(): void
    {
        $this->createdTicket('PRD-1');
        Http::fake();

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/PRD-1/attachments/finalize', ['names' => ['x.png) [click](https://evil.test']])
            ->assertStatus(400);

        Http::assertNothingSent();
    }

    private function postChunk(string $issueId, string $uploadId, string $name, int $index, int $total, string $bytes)
    {
        return $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson("/api/tickets/{$issueId}/attachments/chunks", [
                'uploadId' => $uploadId,
                'name' => $name,
                'index' => $index,
                'total' => $total,
                'data' => base64_encode($bytes),
            ]);
    }

    private function createdTicket(string $issueId): void
    {
        TicketRequest::create($this->ticketRecord($issueId));
    }

    private function ticketRecord(string $issueId): array
    {
        return [
            'request_type' => 'task',
            'email_subject' => 'Subject',
            'email_from' => 'Pedro',
            'email_body' => 'Body',
            'status' => 'success',
            'youtrack_issue_id' => $issueId,
        ];
    }

    private function pngBytes(int $length): string
    {
        $header = "\x89PNG\r\n\x1a\n\0\0\0\rIHDR";

        return $header . str_repeat("\0", max(0, $length - strlen($header)));
    }
}
