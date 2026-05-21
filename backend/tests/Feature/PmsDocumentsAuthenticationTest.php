<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PmsDocumentsAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_for_pms_documents(): void
    {
        $this->get('/pms-documents')
            ->assertRedirect('/login');
    }

    public function test_login_page_is_available_to_guests(): void
    {
        $this->withoutVite();

        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in');
    }

    public function test_user_can_login_and_is_redirected_to_pms_documents(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ])
            ->assertRedirect('/pms-documents');

        $this->assertAuthenticated();
    }
}
