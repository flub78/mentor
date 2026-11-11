<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Examen' }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-100 text-zinc-900 antialiased print:bg-white">
    <main class="mx-auto w-full max-w-3xl px-4 py-6 sm:py-10 print:max-w-none print:p-0">
        {{ $slot }}
    </main>
</body>
</html>
