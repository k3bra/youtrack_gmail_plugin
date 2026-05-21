<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>DocSnitch · YouTrack Tickets</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-page text-text">
        <div class="app-shell min-h-screen">
            <header class="app-header sticky top-0 z-30">
                <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-3 sm:px-8 sm:py-4 lg:px-12">
                    <div class="flex items-center gap-2">
                        <a href="/pms-documents" class="inline-flex items-center gap-3">
                            <span class="brand-mark bg-white text-text">D</span>
                            <span class="text-base font-semibold text-white">DocSnitch</span>
                            <span class="ai-badge">AI</span>
                        </a>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="hidden rounded-lg border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white/70 shadow-sm md:inline-flex">
                            {{ auth()->user()?->email }}
                        </span>
                        <form method="POST" action="/logout">
                            @csrf
                            <button
                                type="submit"
                                class="rounded-lg border border-white/15 bg-white px-3 py-1.5 text-xs font-semibold text-text shadow-sm transition hover:bg-accent-soft hover:text-accent"
                            >
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="mx-auto max-w-7xl px-6 py-8 sm:px-8 sm:py-10 lg:px-12 lg:py-12">
                <div class="flex flex-wrap items-center justify-between gap-5 rounded-lg bg-text p-6 text-white shadow-sm">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/45">YouTrack</p>
                        <h1 class="mt-2 text-2xl font-semibold text-white">Tickets created from analyses</h1>
                        <p class="mt-2 text-sm leading-6 text-white/65">
                            Access the linked document for each ticket.
                        </p>
                    </div>
                    <a
                        class="rounded-lg border border-white/15 bg-white px-4 py-2 text-xs font-semibold text-text shadow-sm transition hover:bg-accent-soft hover:text-accent"
                        href="/pms-documents"
                    >
                        Back to analyses
                    </a>
                </div>

                <div class="mt-6 rounded-lg border border-border bg-card p-6 shadow-sm">
                    @if ($tickets->isEmpty())
                        <p class="text-sm text-text-muted">No tickets created yet.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-border text-sm">
                                <thead class="bg-page text-left text-xs font-semibold uppercase tracking-wider text-text-muted">
                                    <tr>
                                        <th class="px-4 py-3">Ticket</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Type</th>
                                        <th class="px-4 py-3">Document</th>
                                        <th class="px-4 py-3">Created</th>
                                        <th class="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border bg-card">
                                    @foreach ($tickets as $ticket)
                                        @php
                                            $document = $ticket->document;
                                            $docTitle = $document?->title
                                                ?? $document?->original_filename
                                                ?? $document?->source_url
                                                ?? 'Document unavailable';
                                        @endphp
                                        <tr>
                                            <td class="px-4 py-3 font-medium text-text">
                                                <a
                                                    class="underline"
                                                    href="{{ $ticket->issue_url }}"
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    {{ $ticket->issue_id }}
                                                </a>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center rounded-lg border border-border bg-page px-3 py-1 text-xs font-semibold text-text-muted">
                                                    {{ $ticket->issue_status ?? 'Unknown' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-text-muted">
                                                {{ $ticket->issue_type ? ucfirst($ticket->issue_type) : 'Spike' }}
                                            </td>
                                            <td class="px-4 py-3 text-text">
                                                {{ $docTitle }}
                                            </td>
                                            <td class="px-4 py-3 text-text-muted">
                                                {{ $ticket->created_at?->format('Y-m-d H:i') }}
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                @if ($document)
                                                    <a
                                                        class="rounded-lg border border-primary bg-page px-3 py-1.5 text-xs font-semibold text-primary transition hover:border-primary-dark hover:bg-primary-soft hover:text-primary-dark"
                                                        href="/pms-documents/{{ $document->id }}"
                                                    >
                                                        View document
                                                    </a>
                                                @else
                                                    <span class="text-xs text-text-muted">No document</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex items-center justify-between">
                            <a
                                class="rounded-lg border border-primary bg-card px-3 py-1.5 text-xs font-semibold text-primary transition hover:border-primary-dark hover:bg-primary-soft hover:text-primary-dark {{ $tickets->onFirstPage() ? 'pointer-events-none opacity-50' : '' }}"
                                href="{{ $tickets->previousPageUrl() ?? '#' }}"
                            >
                                Previous
                            </a>
                            <p class="text-xs font-semibold text-text-muted">Page {{ $tickets->currentPage() }}</p>
                            <a
                                class="rounded-lg border border-primary bg-card px-3 py-1.5 text-xs font-semibold text-primary transition hover:border-primary-dark hover:bg-primary-soft hover:text-primary-dark {{ $tickets->nextPageUrl() ? '' : 'pointer-events-none opacity-50' }}"
                                href="{{ $tickets->nextPageUrl() ?? '#' }}"
                            >
                                Next
                            </a>
                        </div>
                    @endif
                </div>
            </main>
        </div>
    </body>
</html>
