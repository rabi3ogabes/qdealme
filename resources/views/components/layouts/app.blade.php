@props(['seo' => null, 'hideBottomNav' => false, 'bodyClass' => ''])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ locale_dir() }}" data-theme="light" class="scroll-pt-24">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#fbf9f5">
    <meta name="color-scheme" content="light dark">
    <x-seo-head :seo="$seo" />
    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme') || 'auto';
                var d = t === 'dark' || (t === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.dataset.theme = d ? 'dark' : 'light';
                document.documentElement.dataset.themeSetting = t;
                if (d) document.querySelector('meta[name="theme-color"]').setAttribute('content', '#141114');
            } catch (e) {}
        })();
    </script>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <link rel="preload" href="{{ asset('fonts/readex-pro-arabic.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/readex-pro-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="flex min-h-dvh flex-col {{ $bodyClass }}">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:start-3 focus:z-[100] focus:rounded-lg focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-fg">
        {{ __('nav.skip_to_content') }}
    </a>

    <x-app-header />

    <main id="main" class="flex-1 {{ $hideBottomNav ? '' : 'pb-24 md:pb-0' }}">
        {{ $slot }}
    </main>

    <x-app-footer />

    @unless ($hideBottomNav)
        <x-bottom-nav />
    @endunless

    <x-search-palette />
    <x-city-picker />
    <x-toasts />
</body>
</html>
