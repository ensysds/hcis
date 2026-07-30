<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <link href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/hcis.css') }}" rel="stylesheet">
</head>
<body>
<div class="login-panel min-vh-100">
    <form class="login-box" method="post" action="{{ route('password.email') }}">
        @csrf
        <h2>Reset kata sandi</h2>
        <p class="small text-secondary">Kami akan mengirim tautan reset ke email terdaftar.</p>
        @if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <x-form-input name="email" label="Email" type="email" required class="mb-3"/>
        <button class="btn btn-primary w-100">Kirim tautan</button>
        <a class="d-block text-center mt-3 small" href="{{ route('login') }}">Kembali ke login</a>
    </form>
</div>
</body>
</html>
