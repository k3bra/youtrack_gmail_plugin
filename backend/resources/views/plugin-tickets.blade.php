<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>Plugin Tickets</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap">
        {{-- Same terminal look as the Gmail plugin form: blue = actions, pink = prompts, purple = flags. --}}
        <style>
            *, *::before, *::after { box-sizing: border-box; }
            html { color-scheme: dark; }
            body { margin: 0; min-height: 100vh; background: #0B0D10; color: #D7D9DE; font-family: "JetBrains Mono", "SF Mono", Menlo, Consolas, ui-monospace, monospace; font-size: 13px; line-height: 1.55; -webkit-font-smoothing: antialiased; }
            a { color: #2EA8FF; text-decoration: none; }
            a:hover { color: #8FD0FF; }
            a:focus-visible { outline: 2px solid #2EA8FF; outline-offset: 1px; }
            .dim { color: #6A6D75; }
            .muted { color: #9A9EA6; }
            .prompt { color: #FF4F9A; }
            .flag { color: #A796FF; }
            .bright { color: #F4F5F7; }
            .badge { display: inline-flex; flex: 0 0 auto; width: 20px; height: 20px; align-items: center; justify-content: center; background: #FF318C; color: #0B0D10; font-weight: 700; font-size: 10px; box-shadow: 3px 3px 0 #6B57FF; }

            .page { max-width: 1200px; margin: 0 auto; padding: 32px 24px; }
            .window { background: #16171B; border: 1px solid #34363D; box-shadow: 0 18px 48px rgba(0, 0, 0, 0.5); }
            .titlebar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 14px; background: #1E1F24; border-bottom: 1px solid #34363D; color: #9A9EA6; }
            .titlebar-left { display: flex; align-items: center; gap: 12px; min-width: 0; }
            .body { display: flex; flex-direction: column; gap: 18px; padding: 18px 22px; }
            .cmd { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
            .chip { display: inline-block; padding: 2px 10px; border: 1px solid #45474F; color: #2EA8FF; }
            .chip:hover { border-color: #2EA8FF; }
            .chip[aria-current="page"] { background: #2EA8FF; border-color: #2EA8FF; color: #0B0D10; font-weight: 700; }

            .table-wrap { overflow-x: auto; border: 1px solid #34363D; }
            table { width: 100%; border-collapse: collapse; }
            th { padding: 8px 12px; text-align: left; font-weight: 400; color: #A796FF; background: #1B1C21; border-bottom: 1px solid #34363D; white-space: nowrap; }
            td { padding: 10px 12px; vertical-align: top; border-bottom: 1px dashed #34363D; }
            tbody tr:last-child td { border-bottom: none; }
            tbody tr:hover td { background: #1B1C21; }
            .nowrap { white-space: nowrap; }
            .summary { min-width: 260px; max-width: 460px; }
            .summary-title { color: #F4F5F7; }
            .subject { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 460px; }
            .from { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .error { margin-top: 4px; color: #FF9A9A; white-space: pre-wrap; word-break: break-word; }

            .tag { display: inline-block; padding: 0 6px; font-weight: 700; color: #0B0D10; }
            .tag-ok { background: #2EA8FF; }
            .tag-err { background: #FF6B6B; }
            .flash { display: flex; align-items: baseline; gap: 8px; }
            .rm-form { margin: 0; }
            .rm { cursor: pointer; padding: 2px 10px; background: transparent; border: 1px solid #45474F; border-radius: 0; color: #FF6B6B; font: inherit; }
            .rm:hover { background: #FF6B6B; border-color: #FF6B6B; color: #0B0D10; }
            .rm:focus-visible { outline: 2px solid #2EA8FF; outline-offset: 1px; }

            .empty { padding: 24px 12px; color: #9A9EA6; }
            .footer { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 22px; background: #1E1F24; border-top: 1px solid #34363D; }
            .pager { display: flex; gap: 8px; }
            .btn { display: inline-block; padding: 6px 14px; border: 1px solid #45474F; color: #2EA8FF; }
            .btn:hover { background: #2EA8FF; border-color: #2EA8FF; color: #0B0D10; }
            .btn[aria-disabled="true"] { pointer-events: none; opacity: 0.4; }
            .cursor { display: inline-block; width: 0.6em; background: #2EA8FF; animation: blink 1s steps(1) infinite; }
            @keyframes blink { 0%, 49% { opacity: 1; } 50%, 100% { opacity: 0; } }
            @media (prefers-reduced-motion: reduce) { .cursor { animation: none; } }

            @media (max-width: 640px) {
                .page { padding: 16px; }
                .body { padding: 14px 16px; }
                .footer { padding: 12px 16px; }
                .titlebar-right { display: none; }
            }
        </style>
    </head>
    <body>
        @php
            $total = $counts->sum();
            $filters = [
                null => ['--all', $total],
                'success' => ['--ok', $counts['success'] ?? 0],
                'failed' => ['--err', $counts['failed'] ?? 0],
            ];
        @endphp

        <div class="page">
            <div class="window">
                <div class="titlebar">
                    <div class="titlebar-left">
                        <span class="badge">YT</span>
                        <span>youtrack-gmail ~ plugin-tickets</span>
                    </div>
                    <span class="titlebar-right dim">{{ $total }} {{ \Illuminate\Support\Str::plural('request', $total) }} logged</span>
                </div>

                <div class="body">
                    <div>
                        <div><span class="prompt">$</span> <span class="bright">tickets ls</span> <span class="flag">--source=gmail</span></div>
                        <div class="muted">Tickets created from Gmail with the plugin, newest first. Failed attempts are listed too.</div>
                    </div>

                    @if (session('success'))
                        <div class="flash" role="status"><span class="tag tag-ok">OK</span><span>{{ session('success') }}</span></div>
                    @endif
                    @if (session('error'))
                        <div class="flash" role="alert"><span class="tag tag-err">ERR</span><span>{{ session('error') }}</span></div>
                    @endif

                    <nav class="cmd" aria-label="Filter by status">
                        <span class="dim">filter:</span>
                        @foreach ($filters as $value => [$label, $count])
                            <a
                                class="chip"
                                href="{{ $value ? '/plugin-tickets?status='.$value : '/plugin-tickets' }}"
                                @if ($status === ($value ?: null)) aria-current="page" @endif
                            >{{ $label }} {{ $count }}</a>
                        @endforeach
                    </nav>

                    <div class="table-wrap">
                        @if ($tickets->isEmpty())
                            <div class="empty">
                                <span class="prompt">&gt;</span> no tickets found <span class="cursor">&nbsp;</span>
                            </div>
                        @else
                            <table>
                                <thead>
                                    <tr>
                                        <th scope="col">created</th>
                                        <th scope="col">status</th>
                                        <th scope="col">issue</th>
                                        <th scope="col">type</th>
                                        <th scope="col">summary</th>
                                        <th scope="col">from</th>
                                        <th scope="col">thread</th>
                                        <th scope="col"><span class="dim">actions</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tickets as $ticket)
                                        <tr>
                                            <td class="nowrap muted" title="{{ $ticket->created_at?->toIso8601String() }}">
                                                {{ $ticket->created_at?->format('Y-m-d H:i') }}
                                            </td>
                                            <td class="nowrap">
                                                @if ($ticket->status === 'success')
                                                    <span class="tag tag-ok">OK</span>
                                                @else
                                                    <span class="tag tag-err">ERR</span>
                                                @endif
                                            </td>
                                            <td class="nowrap">
                                                @if ($ticket->youtrack_issue_id && $youTrackBaseUrl !== '')
                                                    <a href="{{ $youTrackBaseUrl }}/issue/{{ $ticket->youtrack_issue_id }}" target="_blank" rel="noreferrer">{{ $ticket->youtrack_issue_id }}</a>
                                                @elseif ($ticket->youtrack_issue_id)
                                                    <span class="bright">{{ $ticket->youtrack_issue_id }}</span>
                                                @else
                                                    <span class="dim">—</span>
                                                @endif
                                            </td>
                                            <td class="nowrap flag">{{ $ticket->request_type ?? '—' }}</td>
                                            <td class="summary">
                                                <div class="summary-title">{{ $ticket->ai_summary ?? $ticket->email_subject ?? 'Untitled' }}</div>
                                                @if ($ticket->ai_summary && $ticket->email_subject && $ticket->ai_summary !== $ticket->email_subject)
                                                    <div class="subject dim" title="{{ $ticket->email_subject }}">re: {{ $ticket->email_subject }}</div>
                                                @endif
                                                @if ($ticket->status !== 'success' && $ticket->error_message)
                                                    <div class="error">{{ $ticket->error_message }}</div>
                                                @endif
                                            </td>
                                            <td class="from muted" title="{{ $ticket->email_from }}">{{ $ticket->email_from ?? '—' }}</td>
                                            <td class="nowrap">
                                                @if ($ticket->email_thread_url && \Illuminate\Support\Str::startsWith($ticket->email_thread_url, 'https://'))
                                                    <a href="{{ $ticket->email_thread_url }}" target="_blank" rel="noreferrer">open</a>
                                                @else
                                                    <span class="dim">—</span>
                                                @endif
                                            </td>
                                            <td class="nowrap">
                                                @php
                                                    $confirm = $ticket->youtrack_issue_id
                                                        ? "Remove {$ticket->youtrack_issue_id} from the log? It only works once the issue is deleted in YouTrack."
                                                        : 'Remove this failed request from the log?';
                                                @endphp
                                                <form class="rm-form" method="POST" action="/plugin-tickets/{{ $ticket->id }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ $confirm }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rm" aria-label="Remove {{ $ticket->youtrack_issue_id ?? 'failed request' }} from the log">rm</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>

                <div class="footer">
                    <span class="dim">page {{ $tickets->currentPage() }}</span>
                    <div class="pager">
                        <a class="btn" href="{{ $tickets->previousPageUrl() ?? '#' }}" @if ($tickets->onFirstPage()) aria-disabled="true" tabindex="-1" @endif>&lt; prev</a>
                        <a class="btn" href="{{ $tickets->nextPageUrl() ?? '#' }}" @unless ($tickets->hasMorePages()) aria-disabled="true" tabindex="-1" @endunless>next &gt;</a>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
