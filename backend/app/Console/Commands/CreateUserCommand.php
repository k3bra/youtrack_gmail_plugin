<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateUserCommand extends Command
{
    protected $signature = 'users:create
        {email : The user email address}
        {--name= : The user display name}
        {--password= : The password to set}
        {--generate-password : Generate a secure password}
        {--force : Update the user if the email already exists}';

    protected $description = 'Create a user that can log in to the backend';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $name = (string) ($this->option('name') ?: Str::before($email, '@'));
        $password = $this->resolvePassword();

        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'password' => $password,
        ], [
            'email' => ['required', 'email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existingUser = User::where('email', $email)->first();

        if ($existingUser && ! $this->option('force')) {
            $this->error('A user with this email already exists. Use --force to update it.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ],
        );

        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated').' user: '.$user->email);

        if ($this->option('generate-password')) {
            $this->warn('Generated password: '.$password);
        }

        return self::SUCCESS;
    }

    private function resolvePassword(): string
    {
        if ($this->option('generate-password')) {
            return $this->generatePassword();
        }

        if ($this->option('password')) {
            return (string) $this->option('password');
        }

        $password = (string) $this->secret('Password');
        $confirmation = (string) $this->secret('Confirm password');

        if ($password !== $confirmation) {
            $this->error('The password confirmation does not match.');

            return '';
        }

        return $password;
    }

    private function generatePassword(): string
    {
        $lowercaseLetters = 'abcdefghijkmnopqrstuvwxyz';
        $uppercaseLetters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $letters = $lowercaseLetters.$uppercaseLetters;
        $numbers = '23456789';
        $symbols = '!@#$%^&*';
        $characters = $letters.$numbers.$symbols;

        $password = [
            $lowercaseLetters[random_int(0, strlen($lowercaseLetters) - 1)],
            $uppercaseLetters[random_int(0, strlen($uppercaseLetters) - 1)],
            $numbers[random_int(0, strlen($numbers) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];

        while (count($password) < 18) {
            $password[] = $characters[random_int(0, strlen($characters) - 1)];
        }

        shuffle($password);

        return implode('', $password);
    }
}
