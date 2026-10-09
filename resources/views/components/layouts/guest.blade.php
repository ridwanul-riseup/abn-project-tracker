@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex align-items-center min-vh-100">
<main class="container">
    <div class="row justify-content-center">
        <div class="col-sm-9 col-md-6 col-lg-4">
            <div class="text-center mb-4">
                <h1 class="h4"><i class="bi bi-hexagon-half"></i> AB Project Hub</h1>
                <p class="text-muted small mb-0">Project documentation and development tracker</p>
            </div>
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <x-flash />
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
