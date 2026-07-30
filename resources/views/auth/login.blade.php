<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login &middot; HCIS One</title>
    <link href="{{ asset('assets/libs/bs/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/libs/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/hcis.css') }}" rel="stylesheet">
    <link href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}" rel="stylesheet">
</head>
<body class="login-page">
<main class="login-shell">
    <section class="login-visual" aria-label="Tentang HCIS One">
        <div class="login-product-mark">
            <span class="login-logo">H</span>
            <span><strong>HCIS One</strong><small>Human Capital Workspace</small></span>
        </div>
        <div class="login-visual-content">
            <div class="login-eyebrow">Human Capital Information System</div>
            <h1>Data manusia yang menggerakkan organisasi.</h1>
            <p class="login-lead">Kelola struktur perusahaan, perjalanan karyawan, waktu kerja, payroll, dan pengembangan talenta dalam satu ruang kerja.</p>
            <div class="login-capabilities">
                <span><i class="bi bi-buildings"></i> Multi-company</span>
                <span><i class="bi bi-people"></i> Employee lifecycle</span>
                <span><i class="bi bi-shield-check"></i> Secure workspace</span>
            </div>
        </div>
        <div class="login-visual-footer"><span>HCIS One</span><span>Enterprise Human Capital</span></div>
    </section>

    <section class="login-panel">
        <div class="login-panel-inner">
            <div class="login-panel-brand">
                <span class="login-logo">H</span>
                <span><strong>HCIS One</strong><small>Human Capital Workspace</small></span>
            </div>
            <form class="login-box" method="post" action="{{ url('/login') }}">
                @csrf
                <div class="login-form-kicker">AKSES BACK OFFICE</div>
                <h2>Selamat datang kembali</h2>
                <p class="login-form-subtitle">Masuk untuk melanjutkan pekerjaan Human Capital Anda.</p>
                @if(session('status'))<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>{{ session('status') }}</div>@endif
                @if($errors->any())<div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}</div>@endif

                <div class="login-field">
                    <label for="email">Email perusahaan</label>
                    <div class="login-input-wrap"><i class="bi bi-envelope"></i><input class="form-control" type="email" name="email" id="email" value="{{ old('email') }}" placeholder="nama@perusahaan.com" autocomplete="email" required autofocus></div>
                    @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="login-field">
                    <label for="password">Kata sandi</label>
                    <div class="login-input-wrap"><i class="bi bi-lock"></i><input class="form-control" type="password" name="password" id="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required><button class="login-password-toggle" type="button" id="passwordToggle" aria-label="Tampilkan kata sandi"><i class="bi bi-eye"></i></button></div>
                    @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="login-form-options">
                    <label class="login-remember"><input type="checkbox" name="remember" value="1"> <span>Ingat saya</span></label>
                    <a href="{{ route('password.request') }}">Lupa kata sandi?</a>
                </div>
                <button class="btn login-submit" type="submit"><span>Masuk ke HCIS</span><i class="bi bi-arrow-right"></i></button>
                <div class="login-security"><i class="bi bi-lock"></i><span>Akses terenkripsi untuk pengguna yang berwenang.</span></div>
            </form>
        </div>
    </section>
</main>
<script>
document.getElementById('passwordToggle')?.addEventListener('click',function(){const input=document.getElementById('password');const visible=input.type==='text';input.type=visible?'password':'text';this.querySelector('i').className=visible?'bi bi-eye':'bi bi-eye-slash';this.setAttribute('aria-label',visible?'Tampilkan kata sandi':'Sembunyikan kata sandi')});
</script>
</body>
</html>
