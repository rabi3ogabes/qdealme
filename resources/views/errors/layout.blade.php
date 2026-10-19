@php
    $home = current_city() ? route('offers.index') : url('/');
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ locale_dir($locale) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} | {{ brand() }}</title>
    <script>
        try { var t = localStorage.getItem('theme') || 'auto'; document.documentElement.dataset.theme = (t === 'dark' || (t === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches)) ? 'dark' : 'light'; } catch (e) {}
    </script>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-dvh place-items-center bg-bg p-6 text-text">
    <main class="w-full max-w-lg text-center">
        <a href="{{ $home }}" class="inline-block"><x-logo class="mx-auto h-10 w-auto" /></a>
        <p class="mt-10 text-7xl font-semibold tracking-tight text-primary/80 tabular-nums">{{ $code }}</p>
        <h1 class="mt-4 text-h1 font-semibold">{{ $title }}</h1>
        <p class="mt-3 text-text-2">{{ $text }}</p>
        <div class="mt-8 flex flex-wrap justify-center gap-2">
            <x-button :href="$home" icon="home">{{ __('ui.back_home') }}</x-button>
        </div>
    </main>
</body>
</html>
