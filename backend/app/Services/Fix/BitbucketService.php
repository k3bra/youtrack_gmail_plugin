<?php

namespace App\Services\Fix;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Opens draft pull requests with the same credentials the dev-workspace skills use
 * (~/.bitbucket-rest-cli-config.json, needs write:pullrequest:bitbucket).
 */
class BitbucketService
{
    public function createDraftPullRequest(string $slug, string $branch, string $base, string $title, string $description): string
    {
        [$username, $password] = $this->credentials();

        $response = Http::withBasicAuth($username, $password)
            ->acceptJson()
            ->post("https://api.bitbucket.org/2.0/repositories/{$slug}/pullrequests", [
                'title' => $title,
                'description' => $description,
                'draft' => true,
                'source' => ['branch' => ['name' => $branch]],
                'destination' => ['branch' => ['name' => $base]],
                'close_source_branch' => true,
            ]);

        if ($response->status() !== 201) {
            throw new RuntimeException('Bitbucket PR creation failed (HTTP ' . $response->status() . '): '
                . ($response->json('error.message') ?? $response->body()));
        }

        $url = $response->json('links.html.href');
        if (!is_string($url) || $url === '') {
            throw new RuntimeException('Bitbucket returned no PR link.');
        }

        return $url;
    }

    private function credentials(): array
    {
        $path = (string) config('tickets.bitbucket_config');
        $config = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $username = $config['auth']['username'] ?? null;
        $password = $config['auth']['appPassword'] ?? null;

        if (!is_string($username) || $username === '' || !is_string($password) || $password === '') {
            throw new RuntimeException("Bitbucket credentials missing in {$path} (auth.username / auth.appPassword).");
        }

        return [$username, $password];
    }
}
