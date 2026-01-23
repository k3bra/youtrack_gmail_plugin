<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>YouTrack Issue Viewer</title>
        <meta name="client-key" content="{{ config('tickets.client_key') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-page text-text">
        <div
            class="min-h-screen bg-page"
            x-data="youtrackIssueViewer()"
            x-cloak
            x-init="init($el)"
            data-issue-id="{{ $issueId }}"
        >
            <header class="border-b border-border bg-card">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-3 sm:px-8 sm:py-4">
                    <a href="/pms-documents" class="inline-flex items-center gap-2">
                        <span class="text-base font-semibold text-text">DocSnitch</span>
                        <span class="rounded-full bg-accent-soft px-2 py-0.5 text-[10px] font-semibold text-accent bg-accent-soft/70 text-accent/80">
                            AI
                        </span>
                    </a>
                </div>
            </header>

            <main class="mx-auto max-w-6xl px-6 py-8 sm:px-8 sm:py-10">
                <div class="flex flex-col gap-6 lg:grid lg:grid-cols-[1.6fr_0.9fr]">
                    <section class="rounded-2xl border border-border bg-card p-6">
                        <p class="text-xs uppercase tracking-[0.2em] text-text-muted">Issue</p>
                        <h1 class="mt-2 text-2xl font-semibold text-text" x-text="issue?.summary || 'Loading issue...'"></h1>
                        <p class="mt-1 text-sm text-text-muted" x-text="issue?.id ? `#${issue.id}` : ''"></p>

                        <div class="mt-6">
                            <h2 class="text-sm font-semibold text-text">Description</h2>
                            <div class="mt-3 rounded-xl border border-border bg-page p-4 text-sm text-text whitespace-pre-wrap">
                                <span x-show="isLoading">Loading description...</span>
                                <span x-show="!isLoading && issue?.description" x-text="issue.description"></span>
                                <span x-show="!isLoading && !issue?.description">No description provided.</span>
                            </div>
                        </div>

                        <p class="mt-4 text-sm text-rose-600" x-show="errorMessage" x-text="errorMessage"></p>
                    </section>

                    <aside class="rounded-2xl border border-border bg-card p-6">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-text">Details</h2>
                            <span class="text-xs text-text-muted" x-text="issue?.project?.key ? `${issue.project.key}` : ''"></span>
                        </div>

                        <div class="mt-4 space-y-4">
                            <template x-for="field in fieldRows" :key="field.label">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.2em] text-text-muted" x-text="field.label"></p>
                                    <p class="mt-1 text-sm font-medium text-text" x-text="field.value"></p>
                                </div>
                            </template>
                            <p class="text-sm text-text-muted" x-show="!isLoading && fieldRows.length === 0">
                                No additional fields available.
                            </p>
                        </div>
                    </aside>
                </div>
            </main>
        </div>
    </body>
</html>
