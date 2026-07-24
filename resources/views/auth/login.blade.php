<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login &middot; HCIS One</title>
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/hcis.css') }}" rel="stylesheet">
    <link href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}" rel="stylesheet">
</head>
<body class="login-page">
<main class="login-shell">
    <section class="login-visual" aria-label="Tentang HCIS One">
        <div class="login-orb login-orb-one"></div>
        <div class="login-orb login-orb-two"></div>
        <div class="login-visual-content">
            <div class="login-product-mark"><span class="login-logo">H</span><span>HCIS One</span></div>
            <div class="login-eyebrow"><i class="bi bi-shield-check"></i> Human Capital Back Office</div>
            <h1>HC data, organized.</h1>
            <p class="login-lead">Back-office Human Capital untuk PIC HC dalam mengelola struktur perusahaan, organisasi, dan data karyawan.</p>
            <div class="login-capabilities">
                <div class="login-capability"><span><i class="bi bi-buildings"></i></span><div><strong>Company</strong><small>Legal entity & NPWP</small></div></div>
                <div class="login-capability"><span><i class="bi bi-diagram-3"></i></span><div><strong>Organization</strong><small>Struktur & unit kerja</small></div></div>
                <div class="login-capability"><span><i class="bi bi-people"></i></span><div><strong>Employee</strong><small>Database karyawan</small></div></div>
            </div>
        </div>
        <div class="login-visual-footer"><i class="bi bi-lock"></i><span>Akses terbatas untuk PIC HC dan administrator yang berwenang.</span></div>
    </section>

    <section class="login-panel">
        <div class="login-panel-inner">
            <div class="login-mobile-brand"><span class="login-logo">H</span><strong>HCIS</strong></div>
            <form class="login-box" method="post" action="{{ url('/login') }}">
                @csrf
                <div class="login-form-kicker">HC BACK OFFICE</div>
                <h2>Masuk HCIS</h2>
                <p class="login-form-subtitle">Back office untuk PIC dan admin Human Capital.</p>
                @if(session('status'))<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>{{ session('status') }}</div>@endif
                @if($errors->any())<div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}</div>@endif

                <div class="login-field mb-3">
                    <label for="email">Email perusahaan</label>
                    <div class="login-input-wrap"><i class="bi bi-envelope"></i><input class="form-control" type="email" name="email" id="email" value="{{ old('email') }}" placeholder="nama@perusahaan.com" autocomplete="email" required autofocus></div>
                    @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="login-field mb-3">
                    <label for="password">Kata sandi</label>
                    <div class="login-input-wrap"><i class="bi bi-lock"></i><input class="form-control" type="password" name="password" id="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required><button class="login-password-toggle" type="button" id="passwordToggle" aria-label="Tampilkan kata sandi"><i class="bi bi-eye"></i></button></div>
                    @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="login-form-options">
                    <label class="login-remember"><input type="checkbox" name="remember" value="1"> <span>Ingat saya</span></label>
                    <a href="{{ route('password.request') }}">Lupa kata sandi?</a>
                </div>
                <button class="btn login-submit" type="submit"><span>Masuk ke HCIS</span><i class="bi bi-arrow-right"></i></button>
            </form>
        </div>
    </section>
</main>
<script>
document.getElementById('passwordToggle')?.addEventListener('click',function(){const input=document.getElementById('password');const visible=input.type==='text';input.type=visible?'password':'text';this.querySelector('i').className=visible?'bi bi-eye':'bi bi-eye-slash';this.setAttribute('aria-label',visible?'Tampilkan kata sandi':'Sembunyikan kata sandi')});
</script>
</body>
</html>
