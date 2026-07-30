<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $title ?? 'HCIS' }} &middot; HCIS One</title>
    @php($assetVersion = fn (string $path) => file_exists(public_path($path)) ? filemtime(public_path($path)) : time())
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}?v={{ $assetVersion('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}?v={{ $assetVersion('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/hcis.css') }}?v={{ filemtime(public_path('css/hcis.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/employee.css') }}?v={{ filemtime(public_path('css/employee.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/organization-tree.css') }}?v={{ filemtime(public_path('css/organization-tree.css')) }}" rel="stylesheet">
    @vite('resources/js/app.js')
</head>
@php($sidebarGroups = \App\Support\HcisAccess::sidebarGroups())
<body>
<header class="topbar">
    <a href="{{ route('dashboard') }}" class="brand"><span class="brand-mark">H</span><span class="brand-copy">HCIS One<small>Human Capital</small></span></a>
    <div class="topbar-content">
        <button class="btn btn-link menu-toggle d-lg-none me-1" id="menuToggle"><i class="bi bi-list fs-5"></i></button>
        <div class="top-search d-none d-md-flex"><i class="bi bi-search"></i><input aria-label="Cari menu atau karyawan" placeholder="Cari menu atau karyawan..."></div>
        <div class="topbar-actions">
            @can('approval.view')
                <a href="{{ route('approvals.index') }}" class="top-icon" title="Approval"><i class="bi bi-bell"></i></a>
            @endcan
            <div class="dropdown">
                <button class="btn btn-link user-menu text-decoration-none dropdown-toggle p-0" data-bs-toggle="dropdown">
                    <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="user-menu-copy">{{ auth()->user()->name }}<small>{{ str(auth()->user()->getRoleNames()->first() ?? 'User')->replace('_', ' ')->title() }}</small></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end small">
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>Profil & Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><form method="post" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button></form></li>
                </ul>
            </div>
        </div>
    </div>
</header>
<aside class="sidebar" id="sidebar">
    <nav>
        <div class="nav-group">UTAMA</div>
        <a href="{{ auth()->user()->can('employee.view') ? route('admin.dashboard') : route('dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard', 'dashboard') ? 'active' : '' }}"><i class="bi bi-grid-1x2"></i> Dashboard</a>
        @foreach($sidebarGroups as $groupLabel => $moduleKeys)
            @php($visibleModuleKeys = collect($moduleKeys)->filter(fn (string $moduleKey) => auth()->user()->hasRole('super_admin') || auth()->user()->can("{$moduleKey}.view")))
            @if($visibleModuleKeys->isNotEmpty())
                <div class="nav-group">{{ $groupLabel }}</div>
                @foreach($visibleModuleKeys as $moduleKey)
                    @php($item = \App\Support\HcisAccess::sidebarItem($moduleKey))
                    <a href="{{ route($item['route_name'], $item['route_parameters']) }}" class="nav-link {{ \App\Support\HcisAccess::isSidebarItemActive($moduleKey) ? 'active' : '' }}">
                        <i class="bi bi-{{ $item['icon'] }}"></i> {{ $item['label'] }}
                    </a>
                @endforeach
            @endif
        @endforeach
    </nav>
</aside>
<main class="main-content">
    <div class="container-fluid px-3 px-lg-4 py-3 py-lg-4">
        @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
        @if(session('status'))<div class="alert alert-info py-2">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger py-2"><strong>Periksa kembali:</strong> {{ $errors->first() }}</div>@endif
        {{ $slot }}
    </div>
</main>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}?v={{ $assetVersion('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script>document.getElementById('menuToggle')?.addEventListener('click',()=>document.getElementById('sidebar').classList.toggle('show'));</script>
{{ $scripts ?? '' }}
</body>
</html>
