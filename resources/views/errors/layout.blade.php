{{--
    Konelsis hata sayfasi iskeleti (11 Eylul 2026 kullanici karari: 404 / 403 /
    500 / bakimda gibi sayfalar animasyonlu ve kirmizi tonlarda). Laravel,
    resources/views/errors/{kod}.blade.php dosyalarini otomatik kullanir;
    4xx / 5xx dosyalari tanimsiz kodlar icin yedektir. Tek dosya, dis kaynak yok.
--}}
@php
    $locale = app()->getLocale();
    $code = $code ?? 500;
    $tone = $tone ?? 'error';
    $isAuthenticated = auth()->check();
    $panelUrl = url('/admin');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $code }} · {{ $title }} · {{ __('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/konelsis-favicon.png') }}">
    <style>
        :root {
            --red-950: #450a0a;
            --red-900: #7f1d1d;
            --red-800: #991b1b;
            --red-700: #b91c1c;
            --red-600: #dc2626;
            --red-500: #ef4444;
            --red-400: #f87171;
            --red-200: #fecaca;
            --red-100: #fee2e2;
            --red-50: #fef2f2;
            --ink: #1f1212;
            --muted: #7a4b4b;
            --card: rgba(255, 255, 255, 0.92);
        }

        * { box-sizing: border-box; }

        html, body { height: 100%; margin: 0; }

        body {
            font-family: "Instrument Sans", "Segoe UI", system-ui, -apple-system, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(1200px 600px at 10% -10%, var(--red-100) 0%, transparent 60%),
                radial-gradient(900px 500px at 110% 110%, var(--red-200) 0%, transparent 55%),
                linear-gradient(160deg, var(--red-50) 0%, #fff 45%, var(--red-50) 100%);
            overflow-x: hidden;
        }

        .sky { position: fixed; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }

        .orb {
            position: absolute;
            border-radius: 9999px;
            background: radial-gradient(circle at 30% 30%, var(--red-400), var(--red-700) 70%);
            opacity: 0.12;
            filter: blur(2px);
            animation: drift var(--dur, 18s) ease-in-out infinite alternate;
        }

        .orb.a { width: 26rem; height: 26rem; left: -8rem; top: -6rem; --dur: 22s; }
        .orb.b { width: 18rem; height: 18rem; right: -4rem; top: 20%; --dur: 17s; animation-delay: -6s; }
        .orb.c { width: 32rem; height: 32rem; right: 10%; bottom: -16rem; --dur: 26s; animation-delay: -12s; }
        .orb.d { width: 10rem; height: 10rem; left: 18%; bottom: 12%; --dur: 14s; animation-delay: -3s; opacity: 0.18; }

        @keyframes drift {
            from { transform: translate3d(0, 0, 0) scale(1); }
            to { transform: translate3d(3rem, -2.5rem, 0) scale(1.08); }
        }

        .grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(185, 28, 28, 0.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(185, 28, 28, 0.06) 1px, transparent 1px);
            background-size: 2.5rem 2.5rem;
            mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%);
            -webkit-mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%);
        }

        main {
            position: relative; z-index: 1;
            min-height: 100%;
            display: flex; align-items: center; justify-content: center;
            padding: 2rem 1rem;
        }

        .card {
            width: 100%; max-width: 44rem;
            background: var(--card);
            border: 1px solid var(--red-100);
            border-radius: 1.5rem;
            box-shadow: 0 30px 60px -30px rgba(153, 27, 27, 0.35), 0 10px 25px -15px rgba(153, 27, 27, 0.25);
            padding: 2.5rem 2rem 2rem;
            text-align: center;
            backdrop-filter: blur(6px);
            animation: rise 0.7s cubic-bezier(0.2, 0.8, 0.2, 1) both;
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(1.25rem) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .brand { display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.25rem; }
        .brand img { height: 3.25rem; width: auto; }

        .code {
            font-size: clamp(4.5rem, 16vw, 8rem);
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.04em;
            margin: 0.25rem 0 0.5rem;
            color: var(--red-700);
            position: relative;
        }

        @keyframes shimmer {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* Gradyan metin her rakamin kendi kutusunda: donusturulmus alt ogeler
           ustteki background-clip:text'i kaybettigi icin (Chromium). */
        .code span {
            display: inline-block;
            background: linear-gradient(120deg, var(--red-800), var(--red-500) 45%, var(--red-700));
            background-size: 200% 200%;
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
            animation: bob 2.8s ease-in-out infinite, shimmer 4s ease-in-out infinite;
        }
        .code span:nth-child(2) { animation-delay: 0.18s; }
        .code span:nth-child(3) { animation-delay: 0.36s; }

        @keyframes bob {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-0.35rem); }
        }

        .badge {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.35rem 0.9rem;
            border-radius: 9999px;
            background: var(--red-100);
            color: var(--red-800);
            font-size: 0.8rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase;
        }

        .badge .dot {
            width: 0.5rem; height: 0.5rem; border-radius: 9999px; background: var(--red-600);
            box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.6);
            animation: pulse 1.8s ease-out infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.55); }
            70% { box-shadow: 0 0 0 0.75rem rgba(220, 38, 38, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
        }

        h1 { font-size: clamp(1.4rem, 3.5vw, 1.9rem); margin: 0.75rem 0 0.5rem; color: var(--red-900); }
        p.lead { margin: 0 auto 1.5rem; max-width: 34rem; color: var(--muted); line-height: 1.55; font-size: 1.02rem; }
        p.detail { margin: -0.75rem auto 1.5rem; max-width: 34rem; color: var(--red-700); font-size: 0.92rem; }

        .icon-wrap { display: flex; justify-content: center; margin: 0.25rem 0 0.75rem; }

        .actions { display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: center; }

        .btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.7rem 1.25rem;
            border-radius: 0.85rem;
            font-weight: 600; font-size: 0.95rem;
            text-decoration: none; cursor: pointer;
            border: 1px solid transparent;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .btn:hover { transform: translateY(-1px); }
        .btn-primary { background: linear-gradient(135deg, var(--red-600), var(--red-700)); color: #fff; box-shadow: 0 10px 20px -12px rgba(185, 28, 28, 0.7); }
        .btn-primary:hover { box-shadow: 0 14px 24px -12px rgba(185, 28, 28, 0.8); }
        .btn-ghost { background: #fff; color: var(--red-800); border-color: var(--red-200); }
        .btn-ghost:hover { background: var(--red-50); }

        .foot { margin-top: 1.75rem; font-size: 0.8rem; color: var(--muted); }
        .foot code { background: var(--red-50); color: var(--red-800); padding: 0.1rem 0.4rem; border-radius: 0.35rem; }

        /* Animasyonlu simgeler */
        .glyph { width: 5.5rem; height: 5.5rem; }
        .glyph .ring { fill: none; stroke: var(--red-200); stroke-width: 4; }
        .glyph .arc { fill: none; stroke: var(--red-600); stroke-width: 4; stroke-linecap: round; stroke-dasharray: 60 200; transform-origin: 50% 50%; animation: spin 2.4s linear infinite; }
        .glyph .core { fill: var(--red-600); }
        .glyph .stroke { fill: none; stroke: var(--red-700); stroke-width: 5; stroke-linecap: round; stroke-linejoin: round; }
        .glyph .draw { stroke-dasharray: 120; stroke-dashoffset: 120; animation: draw 1.4s ease forwards 0.35s; }
        .glyph .wobble { transform-origin: 50% 50%; animation: wobble 2.6s ease-in-out infinite; }
        .glyph .shake { transform-origin: 50% 80%; animation: shake 1.8s ease-in-out infinite; }
        .glyph .tick { transform-origin: 50% 50%; animation: spin 8s linear infinite; }

        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes draw { to { stroke-dashoffset: 0; } }
        @keyframes wobble { 0%, 100% { transform: rotate(-6deg); } 50% { transform: rotate(6deg); } }
        @keyframes shake { 0%, 100% { transform: rotate(0); } 20% { transform: rotate(-8deg); } 40% { transform: rotate(7deg); } 60% { transform: rotate(-4deg); } 80% { transform: rotate(3deg); } }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }
    </style>
</head>
<body>
    <div class="sky" aria-hidden="true">
        <div class="grid"></div>
        <div class="orb a"></div>
        <div class="orb b"></div>
        <div class="orb c"></div>
        <div class="orb d"></div>
    </div>

    <main>
        <section class="card" role="alert" aria-live="polite">
            <a class="brand" href="{{ $panelUrl }}" aria-label="{{ __('app.name') }}">
                <img src="{{ asset('images/konelsis-logo.png') }}" alt="{{ __('app.name') }}">
            </a>

            <div>
                <span class="badge"><span class="dot"></span>{{ __('errors.kinds.'.$tone) }}</span>
            </div>

            <div class="icon-wrap">
                @yield('glyph')
            </div>

            <div class="code" aria-label="{{ $code }}">
                @foreach (str_split((string) $code) as $digit)<span>{{ $digit }}</span>@endforeach
            </div>

            <h1>{{ $title }}</h1>
            <p class="lead">{{ $message }}</p>

            @if (! empty($detail))
                <p class="detail">{{ $detail }}</p>
            @endif

            <div class="actions">
                @if ($showBack ?? true)
                    <a class="btn btn-ghost" href="{{ url()->previous() !== url()->current() ? url()->previous() : $panelUrl }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                        {{ __('errors.actions.back') }}
                    </a>
                @endif

                @if ($showLogin ?? false)
                    <a class="btn btn-primary" href="{{ url('/admin/login') }}">
                        {{ __('errors.actions.login') }}
                    </a>
                @else
                    <a class="btn btn-primary" href="{{ $panelUrl }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>
                        {{ __('errors.actions.home') }}
                    </a>
                @endif

                @if ($showRetry ?? false)
                    <a class="btn btn-ghost" href="{{ url()->current() }}">
                        {{ __('errors.actions.retry') }}
                    </a>
                @endif
            </div>

            <p class="foot">
                {{ __('errors.footer.help') }}
                @if ($isAuthenticated)
                    · {{ __('errors.footer.signed_in_as', ['name' => auth()->user()->getFilamentName()]) }}
                @endif
            </p>
        </section>
    </main>
</body>
</html>
