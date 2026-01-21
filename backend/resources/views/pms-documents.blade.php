<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>PMS API Document Analyzer</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-page text-text">
        <div class="min-h-screen bg-page" x-data="pmsDocuments()" x-cloak x-init="initPage()">
            <header class="border-b border-border bg-card">
                <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-3 sm:px-8 sm:py-4 lg:px-12">
                    <div class="flex items-center gap-2">
                        <a href="/pms-documents" class="inline-flex items-center gap-2">
                            <span class="text-base font-semibold text-text">DocSnitch</span>
                            <span class="rounded-full bg-accent-soft px-2 py-0.5 text-[10px] font-semibold text-accent bg-accent-soft/70 text-accent/80">
                                AI
                            </span>
                        </a>
                    </div>
                    <div class="hidden md:block">
                        <span class="rounded-full border border-border bg-page px-3 py-1 text-[10px] font-semibold text-text-muted">
                            Version 1.0
                        </span>
                    </div>
                </div>
            </header>

            <main class="mx-auto max-w-7xl px-6 py-8 sm:px-8 sm:py-10 lg:px-12 lg:py-12">
                <template x-if="!analysis">
                    <section class="grid gap-8 lg:grid-cols-[1.1fr_0.9fr]">
                        <div class="rounded-2xl border border-border bg-card shadow-md overflow-hidden">
                            <div class="bg-gradient-to-br from-primary-soft to-card px-6 pt-6 pb-5 from-primary-soft/70 px-5 pt-5 pb-4">
                                <h2 class="text-lg font-semibold text-text">Upload PMS documentation</h2>
                                <p class="mt-2 text-sm text-text-muted">
                                    PDF only. Text is extracted locally before analysis.
                                </p>

                                <div
                                    class="mt-6 rounded-2xl border-2 border-dashed px-6 py-10 text-center transition flex flex-col items-center justify-center py-8 mt-5 px-5 py-7"
                                    :class="dropZoneClasses"
                                    @dragenter.prevent="isDragging = true"
                                    @dragleave.prevent="isDragging = false"
                                    @dragover.prevent
                                    @drop.prevent="handleDrop($event)"
                                >
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-page text-text-muted">
                                        <span class="text-xl">PDF</span>
                                    </div>
                                    <p class="mt-4 text-sm font-medium text-text">
                                        Drop PDF here
                                    </p>
                                    <p class="mt-2 text-xs text-text-muted">
                                        or click to browse
                                    </p>
                                    <input
                                        x-ref="fileInput"
                                        type="file"
                                        accept="application/pdf"
                                        class="mt-4 block w-full cursor-pointer text-sm text-text-muted file:mr-4 file:rounded-full file:border file:border-primary file:bg-card file:px-4 file:py-2 file:text-xs file:font-semibold file:text-primary hover:file:bg-primary-soft file:border-border file:bg-page file:text-text-muted hover:file:bg-primary-soft/70"
                                        @change="handleFileInput($event)"
                                    >
                                </div>
                            </div>

                            <div class="border-t border-border bg-card px-6 py-5 px-5 py-4">
                                <div class="flex flex-wrap items-end justify-between gap-4">
                                    <div class="flex-1 rounded-xl border border-border bg-page px-4 py-3 py-2">
                                        <p class="text-xs uppercase tracking-[0.2em] text-text-muted">Selected file</p>
                                        <p class="mt-2 text-sm font-medium text-text" x-text="fileName ? fileName : 'No file selected'"></p>
                                    </div>
                                    <button
                                        class="rounded-full bg-primary px-6 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:cursor-not-allowed disabled:bg-primary-soft disabled:text-text-muted self-end"
                                        :disabled="!file || isUploading || isAnalyzing"
                                        @click="analyzeDocument"
                                        x-text="analyzeButtonLabel"
                                    ></button>
                                </div>

                                <p
                                    x-show="errorMessage"
                                    class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-600"
                                    x-text="errorMessage"
                                ></p>
                            </div>
                        </div>

                        <aside class="space-y-6">
                            <div class="rounded-2xl border border-border bg-card p-6 p-5 bg-page">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="text-sm font-semibold text-text">Recent analyses</h3>
                                    <button
                                        class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-text-muted transition hover:border-primary hover:text-primary"
                                        @click="loadHistory"
                                        :disabled="isLoadingHistory"
                                    >
                                        Refresh
                                    </button>
                                </div>
                                <p class="mt-4 text-sm text-text-muted text-xs" x-show="isLoadingHistory">Loading history...</p>
                                <p class="mt-4 text-sm text-rose-600" x-show="historyError" x-text="historyError"></p>
                                <p
                                    class="mt-4 text-sm text-text-muted text-xs"
                                    x-show="!isLoadingHistory && !historyError && history.length === 0"
                                >
                                    No parsed documents yet.
                                </p>
                                <ul class="mt-4 divide-y divide-border" x-show="history.length">
                                    <template x-for="entry in history" :key="entry.id">
                                        <li class="-mx-2 flex items-center justify-between gap-4 rounded-lg px-2 py-3 transition hover:bg-primary-soft">
                                            <div>
                                                <p class="text-sm font-semibold text-text" x-text="entry.original_filename"></p>
                                                <p class="text-xs text-text-muted" x-text="formatHistoryMeta(entry)"></p>
                                            </div>
                                            <button
                                                class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-text-muted transition hover:border-primary hover:text-primary"
                                                @click="loadHistoryItem(entry.id, true)"
                                            >
                                                View
                                            </button>
                                        </li>
                                    </template>
                                </ul>
                                <div class="mt-4 flex items-center justify-between gap-3" x-show="history.length">
                                    <button
                                        class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-text-muted transition hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:border-border disabled:text-text-muted disabled:opacity-50"
                                        :disabled="!historyPrevPage"
                                        @click="changeHistoryPage(historyPrevPage)"
                                    >
                                        Previous
                                    </button>
                                    <p class="text-xs font-semibold text-text-muted" x-text="`Page ${historyPage}`"></p>
                                    <button
                                        class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-text-muted transition hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:border-border disabled:text-text-muted disabled:opacity-50"
                                        :disabled="!historyNextPage"
                                        @click="changeHistoryPage(historyNextPage)"
                                    >
                                        Next
                                    </button>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-border bg-page p-6 p-5">
                                <h3 class="text-sm font-semibold text-text">What this tool checks</h3>
                                <ul class="mt-4 space-y-3 text-sm text-text-muted text-xs">
                                    <li class="flex items-start gap-2">
                                        <span class="mt-2 h-2 w-2 rounded-full bg-primary"></span>
                                        <span>GET reservations endpoint detection</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="mt-2 h-2 w-2 rounded-full bg-primary"></span>
                                        <span>Webhook support confirmation</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="mt-2 h-2 w-2 rounded-full bg-primary"></span>
                                        <span>Reservation field coverage and status values</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="rounded-2xl border border-border bg-page p-6 p-5">
                                <h3 class="text-sm font-semibold text-text">Processing steps</h3>
                                <ol class="mt-4 space-y-3 text-sm text-text-muted text-xs">
                                    <li class="flex items-start gap-2">
                                        <span class="mt-2 h-2 w-2 rounded-full bg-accent"></span>
                                        <span>Extract PDF text locally.</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="mt-2 h-2 w-2 rounded-full bg-accent"></span>
                                        <span>Normalize and clean the content.</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="mt-2 h-2 w-2 rounded-full bg-accent"></span>
                                        <span>Send text to OpenAI for semantic analysis.</span>
                                    </li>
                                </ol>
                            </div>
                        </aside>
                    </section>
                </template>

                <template x-if="analysis">
                    <section class="space-y-8">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.2em] text-text-muted">Analysis complete</p>
                                <h2 class="mt-2 text-2xl font-semibold text-text">Reservation capability report</h2>
                                <p class="mt-2 text-sm text-text-muted">
                                    Review the extracted capabilities below. Missing information is flagged as unavailable.
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <button
                                    class="rounded-full border border-border px-4 py-2 text-sm font-semibold text-text-muted transition hover:border-primary hover:text-primary print-hidden"
                                    @click="exportPdf"
                                >
                                    Export to PDF
                                </button>
                                <button
                                    class="rounded-full border border-border px-4 py-2 text-xs font-semibold text-text-muted transition hover:border-primary hover:text-primary print-hidden"
                                    @click="openTicketModal"
                                >
                                    Create YouTrack ticket
                                </button>
                                <button
                                    class="rounded-full border border-border px-5 py-2 text-sm font-semibold text-text-muted transition hover:border-primary hover:text-primary print-hidden"
                                    @click="reset"
                                >
                                    Analyze another document
                                </button>
                            </div>
                        </div>
                        <p class="text-sm text-rose-600" x-show="ticketError" x-text="ticketError"></p>
                        <p class="text-sm text-emerald-700" x-show="ticketResult">
                            Ticket created:
                            <a
                                class="font-semibold underline"
                                target="_blank"
                                rel="noreferrer"
                                :href="ticketResult?.url"
                                x-text="ticketResult?.issueId"
                            ></a>
                        </p>

                        <div
                            class="fixed inset-0 z-50 flex items-center justify-center bg-text/60 px-4 py-6 print-hidden"
                            x-show="isTicketModalOpen"
                            x-transition.opacity
                            @keydown.escape.window="closeTicketModal"
                        >
                            <div
                                class="w-full max-w-5xl rounded-2xl bg-card p-6 shadow-xl"
                                @click.outside="closeTicketModal"
                            >
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.2em] text-text-muted">Spike ticket</p>
                                        <h3 class="mt-2 text-lg font-semibold text-text">Review and edit before sending</h3>
                                    </div>
                                    <button
                                        class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-text-muted transition hover:border-primary hover:text-primary"
                                        @click="closeTicketModal"
                                    >
                                        Close
                                    </button>
                                </div>

                                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-text-muted">Markdown</p>
                                        <textarea
                                            class="mt-3 h-80 w-full resize-none rounded-xl border border-border bg-page p-4 text-sm text-text focus:border-primary focus:outline-none focus:ring-0"
                                            x-model="ticketDraft"
                                        ></textarea>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-text-muted">Preview</p>
                                        <div class="mt-3 h-80 overflow-y-auto rounded-xl border border-border bg-card p-4">
                                            <div x-html="ticketPreviewHtml"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                                    <p class="text-sm text-rose-600" x-show="ticketError" x-text="ticketError"></p>
                                    <div class="flex items-center gap-3">
                                        <button
                                            class="rounded-full border border-border px-4 py-2 text-xs font-semibold text-text-muted transition hover:border-primary hover:text-primary"
                                            @click="closeTicketModal"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            class="rounded-full border border-primary px-4 py-2 text-xs font-semibold text-primary transition hover:bg-primary-soft disabled:cursor-not-allowed disabled:border-border disabled:text-text-muted"
                                            :disabled="isCreatingTicket"
                                            @click="createTicket"
                                            x-text="isCreatingTicket ? 'Creating...' : 'Create YouTrack ticket'"
                                        ></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                                <p class="text-xs uppercase tracking-[0.2em] text-text-muted">GET reservations</p>
                                <div class="mt-3 flex items-center gap-3">
                                    <span
                                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="analysis.has_get_reservations_endpoint ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                                        x-text="analysis.has_get_reservations_endpoint ? 'Yes' : 'No'"
                                    ></span>
                                    <span class="text-sm text-text-muted" x-text="analysis.get_reservations_endpoint || 'Endpoint not documented'"></span>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                                <p class="text-xs uppercase tracking-[0.2em] text-text-muted">Webhooks</p>
                                <div class="mt-3 flex items-center gap-3">
                                    <span
                                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="analysis.supports_webhooks ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                                        x-text="analysis.supports_webhooks ? 'Yes' : 'No'"
                                    ></span>
                                    <span class="text-sm text-text-muted" x-text="analysis.webhook_details || 'No webhook details documented'"></span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="text-xs uppercase tracking-[0.2em] text-text-muted">GET reservations example</p>
                                <div class="flex items-center gap-3">
                                    <span
                                        class="rounded-full border border-border bg-page px-3 py-1 text-xs font-semibold uppercase tracking-[0.15em] text-text-muted"
                                        x-show="exampleFormat"
                                        x-text="exampleFormat"
                                    ></span>
                                    <button
                                        class="rounded-full border border-border px-4 py-2 text-xs font-semibold text-text-muted transition hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:border-border disabled:text-text-muted disabled:opacity-50 print-hidden"
                                        :disabled="isLoadingExample || analysis.has_get_reservations_endpoint === false"
                                        @click="fetchExample"
                                        x-text="isLoadingExample ? 'Loading...' : 'Load example'"
                                    ></button>
                                </div>
                            </div>
                            <p
                                class="mt-3 text-sm text-text-muted"
                                x-show="analysis.has_get_reservations_endpoint === false"
                            >
                                No GET reservations endpoint documented.
                            </p>
                            <p
                                class="mt-3 text-sm text-text-muted"
                                x-show="analysis.has_get_reservations_endpoint !== false && !exampleFetched && !isLoadingExample"
                            >
                                Click “Load example” to fetch the response payload.
                            </p>
                            <p
                                class="mt-3 text-sm text-text-muted"
                                x-show="analysis.has_get_reservations_endpoint !== false && exampleFetched && !examplePayload && !exampleError"
                            >
                                No example response documented.
                            </p>
                            <p
                                class="mt-3 text-sm text-rose-600"
                                x-show="exampleError"
                                x-text="exampleError"
                            ></p>
                            <details
                                class="mt-4"
                                x-show="examplePayload"
                                x-ref="exampleDetails"
                            >
                                <summary class="cursor-pointer text-sm font-semibold text-text">
                                    View example payload
                                </summary>
                                <pre class="mt-3 overflow-x-auto rounded-xl border border-border bg-page p-4 text-xs text-text-muted"><code class="syntax-highlight" x-html="highlightedExamplePayload"></code></pre>
                            </details>
                        </div>

                        <div class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="text-xs uppercase tracking-[0.2em] text-text-muted">Tickets</p>
                                <button
                                    class="rounded-full border border-border px-3 py-1 text-xs font-semibold text-text-muted transition hover:border-primary hover:text-primary print-hidden"
                                    @click="loadTickets(true)"
                                    :disabled="isLoadingTickets"
                                >
                                    Refresh status
                                </button>
                            </div>
                            <p class="mt-3 text-sm text-text-muted" x-show="isLoadingTickets">Loading tickets...</p>
                            <p class="mt-3 text-sm text-rose-600" x-show="ticketsError" x-text="ticketsError"></p>
                            <p
                                class="mt-3 text-sm text-text-muted"
                                x-show="!isLoadingTickets && !ticketsError && tickets.length === 0"
                            >
                                No tickets created yet.
                            </p>
                            <ul class="mt-4 divide-y divide-border" x-show="tickets.length">
                                <template x-for="ticket in tickets" :key="ticket.id">
                                    <li class="-mx-2 flex flex-wrap items-center justify-between gap-3 rounded-lg px-2 py-3 transition hover:bg-primary-soft">
                                        <div>
                                            <p class="text-sm font-semibold text-text">
                                                <a
                                                    class="underline"
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    :href="ticket.issue_url"
                                                    x-text="ticket.issue_id"
                                                ></a>
                                            </p>
                                            <p class="text-xs text-text-muted" x-text="formatTicketMeta(ticket)"></p>
                                        </div>
                                        <span class="rounded-full border border-border bg-page px-3 py-1 text-xs font-semibold text-text-muted">
                                            <span x-text="ticket.issue_status || 'Unknown'"></span>
                                        </span>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        <div class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                            <h3 class="text-sm font-semibold text-text">Required fields</h3>
                            <div class="mt-4 overflow-hidden rounded-xl border border-border">
                                <table class="min-w-full divide-y divide-border text-sm">
                                    <thead class="bg-page text-left text-xs font-semibold uppercase tracking-wider text-text-muted">
                                        <tr>
                                            <th class="px-4 py-3">Field</th>
                                            <th class="px-4 py-3">Available</th>
                                            <th class="px-4 py-3">Source label</th>
                                            <th class="px-4 py-3">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border bg-card">
                                        <template x-for="row in fieldRows" :key="row.key">
                                            <tr :class="row.available ? 'bg-card' : 'bg-rose-50'">
                                                <td class="px-4 py-3 font-medium text-text" x-text="row.label"></td>
                                                <td class="px-4 py-3">
                                                    <span
                                                        class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold"
                                                        :class="row.available ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                                                        x-text="row.available ? 'Yes' : 'No'"
                                                    ></span>
                                                </td>
                                                <td class="px-4 py-3 text-text-muted" x-text="row.sourceLabel || 'Not documented'"></td>
                                                <td class="px-4 py-3 text-text-muted" x-text="row.notes"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                            <div class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                                <h3 class="text-sm font-semibold text-text">Reservation status values</h3>
                                <p
                                    class="mt-2 text-sm text-text-muted"
                                    x-text="analysis.fields.reservation_status.available && analysis.fields.reservation_status.values.length ? 'Documented status values' : 'No status values documented'"
                                ></p>
                                <template x-if="analysis.fields.reservation_status.values.length">
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <template x-for="status in analysis.fields.reservation_status.values" :key="status">
                                            <span
                                                class="rounded-full border border-border bg-page px-3 py-1 text-xs font-semibold text-text"
                                                x-text="status"
                                            ></span>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <div class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                                <h3 class="text-sm font-semibold text-text">Analyst notes</h3>
                                <p x-show="analysis.notes.length === 0" class="mt-2 text-sm text-text-muted">
                                    No additional notes provided.
                                </p>
                                <ul x-show="analysis.notes.length > 0" class="mt-3 space-y-2 text-sm text-text-muted">
                                    <template x-for="note in analysis.notes" :key="note">
                                        <li>• <span x-text="note"></span></li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </section>
                </template>
            </main>
        </div>
    </body>
</html>
