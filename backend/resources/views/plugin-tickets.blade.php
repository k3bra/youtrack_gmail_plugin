<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
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
            .logo { display: block; flex: 0 0 auto; width: 26px; height: auto; }

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

            .actions { display: flex; gap: 6px; align-items: flex-start; }
            .ai { cursor: pointer; padding: 2px 10px; background: transparent; border: 1px solid #45474F; border-radius: 0; color: #A796FF; font: inherit; }
            .ai:hover { background: #A796FF; border-color: #A796FF; color: #0B0D10; }
            .ai:focus-visible { outline: 2px solid #2EA8FF; outline-offset: 1px; }
            .ai:disabled { cursor: not-allowed; opacity: 0.5; }

            tbody tr.triage-row td { padding: 0 12px 14px; background: #121317; }
            tbody tr.triage-row:hover td { background: #121317; }
            .triage { display: flex; flex-direction: column; gap: 10px; padding: 12px 14px; border: 1px solid #34363D; border-left: 3px solid #A796FF; }
            .triage-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: 10px; }
            .triage-summary { color: #F4F5F7; max-width: 900px; }
            .triage h4 { margin: 0 0 2px; font-size: 13px; font-weight: 400; color: #A796FF; }
            .triage ul { margin: 0; padding-left: 18px; }
            .triage li { margin: 2px 0; }
            .triage code { color: #8FD0FF; word-break: break-all; }
            .triage details summary { cursor: pointer; color: #9A9EA6; }
            .triage details[open] summary { margin-bottom: 6px; }
            .triage-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px; }
            .triage-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
            .tag-fix { background: #3DDC97; }
            .tag-block { background: #FFB347; }
            .tag-run { background: #A796FF; }
            .gate-ok { color: #3DDC97; }
            .gate-fail { color: #FF6B6B; }
            .fix { display: flex; flex-direction: column; gap: 6px; padding-top: 10px; border-top: 1px dashed #34363D; }
            .accept { color: #3DDC97; border-color: #3DDC97; }
            .accept:hover { background: #3DDC97; border-color: #3DDC97; color: #0B0D10; }

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
                        <img class="logo" src="/logo.svg" alt="" width="26" height="19">
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
                                                <div class="actions">
                                                @if ($ticket->youtrack_issue_id)
                                                    <button type="button" class="ai" data-triage-button data-row="{{ $ticket->id }}" data-issue="{{ $ticket->youtrack_issue_id }}" aria-controls="triage-{{ $ticket->id }}" aria-label="AI triage for {{ $ticket->youtrack_issue_id }}">ai</button>
                                                @endif
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
                                                </div>
                                            </td>
                                        </tr>
                                        @if ($ticket->youtrack_issue_id)
                                            <tr class="triage-row" id="triage-{{ $ticket->id }}" hidden>
                                                <td colspan="8"><div class="triage" aria-live="polite"></div></td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>

                    @if ($aiFixTriages->isNotEmpty())
                        <div>
                            <div><span class="prompt">$</span> <span class="bright">tickets triage</span> <span class="flag">--tag=ai-fix</span></div>
                            <div class="muted">Tickets tagged ai-fix in YouTrack that weren't created from Gmail. Each one is triaged once, automatically.</div>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th scope="col">triaged</th>
                                        <th scope="col">issue</th>
                                        <th scope="col">result</th>
                                        <th scope="col">summary</th>
                                        <th scope="col"><span class="dim">actions</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($aiFixTriages as $triage)
                                        <tr>
                                            <td class="nowrap muted" title="{{ $triage->created_at?->toIso8601String() }}">{{ $triage->created_at?->format('Y-m-d H:i') }}</td>
                                            <td class="nowrap">
                                                @if ($youTrackBaseUrl !== '')
                                                    <a href="{{ $youTrackBaseUrl }}/issue/{{ $triage->issue_id }}" target="_blank" rel="noreferrer">{{ $triage->issue_id }}</a>
                                                @else
                                                    <span class="bright">{{ $triage->issue_id }}</span>
                                                @endif
                                            </td>
                                            <td class="nowrap flag">{{ $triage->verdict ?? $triage->status }}</td>
                                            <td class="summary"><div class="summary-title">{{ $triage->analysis['summary'] ?? '—' }}</div></td>
                                            <td class="nowrap">
                                                <button type="button" class="ai" data-triage-button data-row="ai-fix-{{ $triage->id }}" data-issue="{{ $triage->issue_id }}" aria-controls="triage-ai-fix-{{ $triage->id }}" aria-label="AI triage for {{ $triage->issue_id }}">ai</button>
                                            </td>
                                        </tr>
                                        <tr class="triage-row" id="triage-ai-fix-{{ $triage->id }}" hidden>
                                            <td colspan="5"><div class="triage" aria-live="polite"></div></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
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
        <script>
            (() => {
                const triages = @json($triages);
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const POLL_MS = 3000;

                function el(tag, className, text) {
                    const node = document.createElement(tag);
                    if (className) node.className = className;
                    if (text !== undefined && text !== null) node.textContent = String(text);
                    return node;
                }

                function list(title, items, asCode) {
                    if (!Array.isArray(items) || items.length === 0) return null;
                    const wrap = el('div');
                    wrap.append(el('h4', '', title));
                    const ul = el('ul');
                    items.forEach((item) => {
                        const li = el('li');
                        li.append(asCode ? el('code', '', item) : document.createTextNode(String(item)));
                        ul.append(li);
                    });
                    wrap.append(ul);
                    return wrap;
                }

                function block(title, text) {
                    if (!text) return null;
                    const wrap = el('div');
                    wrap.append(el('h4', '', title), el('div', '', text));
                    return wrap;
                }

                function meta(triage) {
                    const parts = [];
                    if (triage.trigger === 'ai-fix') parts.push('auto: ai-fix tag');
                    if (triage.model) parts.push(triage.model);
                    if (triage.duration_ms) parts.push(Math.round(triage.duration_ms / 1000) + 's');
                    if (triage.num_turns) parts.push(triage.num_turns + ' turns');
                    if (typeof triage.cost_usd === 'number') parts.push('~$' + triage.cost_usd.toFixed(2));
                    return el('span', 'dim', parts.join(' · '));
                }

                function render(box, triage, ticketId) {
                    box.replaceChildren();
                    const head = el('div', 'triage-head');
                    box.append(head);

                    if (!triage) {
                        head.append(el('span', 'dim', 'no triage yet'));
                        return;
                    }

                    if (running(triage.status)) {
                        head.append(el('span', 'tag tag-run', triage.status.toUpperCase()), el('span', 'muted', 'reading the code in dev-workspace… ' + elapsed(triage.created_at) + 's'));
                        return;
                    }

                    if (triage.status === 'failed') {
                        head.append(el('span', 'tag tag-err', 'FAILED'), el('span', 'error', triage.error || 'Triage failed.'));
                        box.append(actions(ticketId, 'retry'));
                        return;
                    }

                    const a = triage.analysis || {};
                    const verdict = triage.verdict || a.verdict;
                    const tagClass = verdict === 'fixable' ? 'tag-fix' : verdict === 'blocked' ? 'tag-block' : 'tag-err';
                    head.append(
                        el('span', 'tag ' + tagClass, String(verdict || 'unknown').toUpperCase()),
                        el('span', 'flag', 'confidence: ' + (triage.confidence || a.confidence || '—')),
                        meta(triage)
                    );
                    if (a.verdict_overridden) {
                        head.append(el('span', 'error', 'model said fixable, but a gate failed → blocked'));
                    }
                    if (a.summary) box.append(el('div', 'triage-summary', a.summary));

                    const grid = el('div', 'triage-grid');
                    [
                        list('why this confidence', a.confidence_reasons),
                        list('open questions', a.open_questions),
                        gates(a.gates),
                        list('files', (Array.isArray(a.files) && a.repos && a.repos.length === 1 ? a.files.map((f) => a.repos[0] + '/' + f) : a.files), true),
                    ].forEach((node) => node && grid.append(node));
                    box.append(grid);

                    const details = el('details');
                    details.append(el('summary', '', 'root cause, plan, notes'));
                    [
                        block('root cause', a.root_cause),
                        block('planned fix', a.planned_fix),
                        block('test plan', a.test_plan),
                        list('notes', a.notes),
                    ].forEach((node) => node && details.append(node));
                    box.append(details);

                    if (triage.fix) box.append(fixBlock(triage.fix));

                    const fixStatus = triage.fix ? triage.fix.status : null;
                    const canAccept = verdict === 'fixable' && (fixStatus === null || fixStatus === 'failed' || fixStatus === 'blocked');
                    box.append(actions(ticketId, 're-run', canAccept ? triage : null, fixStatus !== null));
                }

                function gates(g) {
                    if (!g || typeof g !== 'object') return null;
                    const wrap = el('div');
                    wrap.append(el('h4', '', 'gates'));
                    const ul = el('ul');
                    Object.entries(g).forEach(([name, ok]) => {
                        const li = el('li');
                        li.append(el('span', ok === true ? 'gate-ok' : 'gate-fail', ok === true ? '✓ ' : '✗ '), document.createTextNode(name.replaceAll('_', ' ')));
                        ul.append(li);
                    });
                    wrap.append(ul);
                    return wrap;
                }

                function actions(ticketId, rerunLabel, acceptable, retry) {
                    const bar = el('div', 'triage-actions');
                    const rerun = el('button', 'ai', rerunLabel);
                    rerun.type = 'button';
                    rerun.addEventListener('click', () => start(ticketId));
                    bar.append(rerun);
                    if (acceptable) {
                        const button = el('button', 'ai accept', retry ? 'retry fix' : 'accept & fix');
                        button.type = 'button';
                        button.addEventListener('click', () => accept(ticketId, acceptable));
                        bar.append(button);
                    }
                    return bar;
                }

                function rowFor(ticketId) {
                    const row = document.getElementById('triage-' + ticketId);
                    return { row, box: row && row.querySelector('.triage') };
                }

                const issueByRow = {};

                async function request(method, ticketId, suffix = '') {
                    const response = await fetch('/plugin-tickets/triage/' + encodeURIComponent(issueByRow[ticketId]) + suffix, {
                        method,
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        credentials: 'same-origin',
                    });
                    const body = await response.json().catch(() => ({}));
                    if (response.status === 404 && method === 'GET') return null;
                    if (!response.ok) throw new Error(body.error || 'HTTP ' + response.status);
                    return body;
                }

                const polling = new Set();

                function running(status) {
                    return status === 'queued' || status === 'running';
                }

                function inProgress(triage) {
                    return !!triage && (running(triage.status) || (triage.fix && running(triage.fix.status)));
                }

                function elapsed(since) {
                    const t = new Date(since).getTime();
                    return Number.isFinite(t) ? Math.max(0, Math.round((Date.now() - t) / 1000)) : 0;
                }

                function fixBlock(fix) {
                    const wrap = el('div', 'fix');
                    const head = el('div', 'triage-head');
                    wrap.append(head);

                    if (running(fix.status)) {
                        head.append(el('span', 'tag tag-run', 'FIXING'), el('span', 'muted', 'Opus is writing the fix in a worktree… ' + elapsed(fix.created_at) + 's'));
                        return wrap;
                    }

                    const r = fix.result || {};
                    if (fix.status === 'completed') {
                        head.append(el('span', 'tag tag-fix', 'DRAFT PR'));
                        const link = el('a', '', fix.pr_url);
                        link.href = fix.pr_url;
                        link.target = '_blank';
                        link.rel = 'noreferrer';
                        head.append(link, meta(fix));
                    } else {
                        head.append(el('span', 'tag ' + (fix.status === 'blocked' ? 'tag-block' : 'tag-err'), fix.status === 'blocked' ? 'FIX BLOCKED' : 'FIX FAILED'), el('span', 'error', fix.error || ''));
                    }
                    if (fix.branch) wrap.append(el('div', 'dim', fix.repo + ' · ' + fix.branch + (fix.commit_sha ? ' · ' + fix.commit_sha.slice(0, 9) : '')));
                    if (r.summary) wrap.append(el('div', 'triage-summary', r.summary));
                    if (r.verification) wrap.append(el('div', 'muted', 'verification: ' + r.verification));
                    const files = list('files changed', r.files_committed || r.files_changed, true);
                    if (files) wrap.append(files);
                    return wrap;
                }

                async function accept(ticketId, triage) {
                    const a = triage.analysis || {};
                    const repo = (a.repos || [])[0] || '?';
                    const ok = window.confirm(
                        'Accept & fix ' + triage.issue_id + '?\n\n'
                        + 'Opus will write the fix in a worktree of ' + repo + ' (off develop), then the backend commits, '
                        + 'pushes a feature/' + triage.issue_id + '_… branch and opens a DRAFT pull request on Bitbucket.\n\n'
                        + 'Usually a few minutes and a few dollars of tokens.'
                    );
                    if (!ok) return;

                    const { box } = rowFor(ticketId);
                    try {
                        render(box, await request('POST', ticketId, '/accept'), ticketId);
                        poll(ticketId);
                    } catch (error) {
                        window.alert('Could not start the fix: ' + error.message);
                    }
                }

                async function poll(ticketId) {
                    if (polling.has(ticketId)) return;
                    polling.add(ticketId);
                    const { box } = rowFor(ticketId);
                    try {
                        for (;;) {
                            await new Promise((resolve) => setTimeout(resolve, POLL_MS));
                            const triage = await request('GET', ticketId);
                            render(box, triage, ticketId);
                            if (!inProgress(triage)) break;
                        }
                    } catch (error) {
                        render(box, { status: 'failed', error: error.message }, ticketId);
                    } finally {
                        polling.delete(ticketId);
                    }
                }

                async function start(ticketId) {
                    const { row, box } = rowFor(ticketId);
                    row.hidden = false;
                    try {
                        const triage = await request('POST', ticketId);
                        render(box, triage, ticketId);
                        poll(ticketId);
                    } catch (error) {
                        render(box, { status: 'failed', error: error.message }, ticketId);
                    }
                }

                document.querySelectorAll('[data-triage-button]').forEach((button) => {
                    const ticketId = button.dataset.row;
                    issueByRow[ticketId] = button.dataset.issue;
                    const triage = triages[button.dataset.issue];
                    const { row, box } = rowFor(ticketId);

                    if (triage) {
                        render(box, triage, ticketId);
                        if (inProgress(triage)) {
                            row.hidden = false;
                            poll(ticketId);
                        }
                    }

                    // First click on a ticket with no triage starts one; otherwise it toggles the panel.
                    button.addEventListener('click', () => {
                        if (!triages[button.dataset.issue] && !box.hasChildNodes()) {
                            start(ticketId);
                            return;
                        }
                        row.hidden = !row.hidden;
                    });
                });
            })();
        </script>
    </body>
</html>
