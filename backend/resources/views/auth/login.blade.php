<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>DocSnitch · Login</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-page text-text">
        <main class="auth-page">
            <section class="auth-shell">
                <aside class="auth-aside">
                    <div>
                        <a href="/login" class="inline-flex items-center gap-3 text-white">
                            <span class="brand-mark bg-white text-text">D</span>
                            <span class="text-lg font-semibold">DocSnitch</span>
                            <span class="ai-badge">AI</span>
                        </a>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/50">PMS document analysis</p>
                        <h1 class="mt-4 max-w-sm text-3xl font-semibold leading-tight">Review integrations from one protected workspace.</h1>
                        <p class="mt-4 max-w-sm text-sm leading-6 text-white/70">
                            Sign in to upload PMS documentation, review AI analysis, and follow generated YouTrack tickets.
                        </p>
                    </div>
                    <p class="text-xs text-white/45">Internal backend access</p>
                </aside>

                <div class="auth-panel">
                    <a href="/login" class="inline-flex items-center gap-3 md:hidden">
                        <span class="brand-mark">D</span>
                        <span class="text-lg font-semibold text-text">DocSnitch</span>
                        <span class="ai-badge">AI</span>
                    </a>
                    <div class="mt-10 md:mt-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-text-muted">Secure access</p>
                        <h1 class="mt-3 text-2xl font-semibold text-text">Sign in</h1>
                        <p class="mt-2 text-sm leading-6 text-text-muted">Use your backend account to continue to PMS document analysis.</p>
                    </div>

                    <form class="mt-8 space-y-5" method="POST" action="/login">
                        @csrf

                        <div>
                            <label for="email" class="text-xs font-semibold uppercase tracking-[0.16em] text-text-muted">Email</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                autocomplete="email"
                                required
                                autofocus
                                value="{{ old('email') }}"
                                class="mt-2 w-full rounded-lg border border-border bg-white px-4 py-3 text-sm text-text shadow-sm transition focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary-soft"
                            >
                            @error('email')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="text-xs font-semibold uppercase tracking-[0.16em] text-text-muted">Password</label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                required
                                class="mt-2 w-full rounded-lg border border-border bg-white px-4 py-3 text-sm text-text shadow-sm transition focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary-soft"
                            >
                            @error('password')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="flex items-center gap-3 text-sm text-text-muted">
                            <input
                                name="remember"
                                type="checkbox"
                                value="1"
                                class="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                            >
                            Remember me
                        </label>

                        <button
                            type="submit"
                            class="w-full rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-white shadow-sm ring-1 ring-primary/20 transition hover:bg-primary-dark focus:outline-none focus:ring-4 focus:ring-primary-soft"
                        >
                            Sign in
                        </button>
                    </form>
                </div>
            </section>
        </main>
    </body>
</html>
