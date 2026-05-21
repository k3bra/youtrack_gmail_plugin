<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_confirmation_mismatch_returns_command_failure(): void
    {
        $this->artisan('users:create admin@example.com')
            ->expectsQuestion('Password', 'StrongPassword123')
            ->expectsQuestion('Confirm password', 'DifferentPassword123')
            ->expectsOutput('The password confirmation does not match.')
            ->assertFailed();
    }
}
