<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_root_redirects_to_plugin_tickets(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/plugin-tickets');
    }

    public function test_authenticated_root_redirects_to_plugin_tickets(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertRedirect('/plugin-tickets');
    }

    public function test_authenticated_login_redirects_to_pms_documents(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/login');

        $response->assertRedirect('/pms-documents');
    }
}
