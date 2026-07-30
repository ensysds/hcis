<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <link href="{{ asset('assets/libs/bs/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/hcis.css') }}" rel="stylesheet">
</head>
<body class="login-panel min-vh-100">
    <div class="login-box text-center">
        <div class="display-4 text-danger">403</div>
        <h2>Akses tidak diizinkan</h2>
        <p class="text-secondary">Anda tidak memiliki izin untuk membuka halaman ini.</p>
        <a href="{{ url('/dashboard') }}" class="btn btn-primary">Kembali ke Dashboard</a>
    </div>
</body>
</html>
