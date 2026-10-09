@props(['title' => null, 'breadcrumbs' => null])
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
<body>
<div class="d-lg-flex">
    @include('partials.sidebar')

    <div class="flex-grow-1 hub-main">
        <nav class="navbar navbar-expand bg-white border-bottom px-3">
            <form class="d-flex flex-grow-1 me-3" method="GET" action="{{ route('search') }}" role="search">
                <div class="input-group input-group-sm hub-search">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input class="form-control" type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}"
                           placeholder="Search products, repositories, modules, docs, tasks, APIs…" aria-label="Search">
                </div>
            </form>
            <div class="dropdown">
                <a class="nav-link dropdown-toggle small" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle"></i> {{ auth()->user()->name }}
                    <span class="badge text-bg-light border ms-1">{{ auth()->user()->role?->name ?? 'No role' }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-gear"></i> Profile &amp; password</a></li>
                    <li><a class="dropdown-item" href="{{ route('tasks.index', ['mine' => 1]) }}"><i class="bi bi-person-check"></i> My tasks</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right"></i> Log out</button>
                        </form>
                    </li>
                </ul>
            </div>
        </nav>

        <main class="p-3 p-lg-4">
            @if ($breadcrumbs)
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb small mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        @foreach ($breadcrumbs as $label => $url)
                            @if ($loop->last || $url === null)
                                <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
                            @else
                                <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                            @endif
                        @endforeach
                    </ol>
                </nav>
            @endif

            <x-flash />

            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
