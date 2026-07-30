<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <link href="{{ asset('assets/libs/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/hcis.css') }}" rel="stylesheet">
</head>
<body>
<div class="login-panel min-vh-100">
    <form class="login-box" method="post" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">
        <h2>Buat kata sandi baru</h2>
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <x-form-input name="password" label="Kata sandi baru" type="password" required class="mb-3"/>
        <x-form-input name="password_confirmation" label="Konfirmasi kata sandi" type="password" required class="mb-3"/>
        <button class="btn btn-primary w-100">Simpan kata sandi</button>
    </form>
</div>
</body>
</html>
