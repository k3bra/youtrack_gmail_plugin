<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_guest_root_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_root_redirects_to_pms_documents(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertRedirect('/pms-documents');
    }

    public function test_authenticated_login_redirects_to_pms_documents(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/login');

        $response->assertRedirect('/pms-documents');
    }
}
