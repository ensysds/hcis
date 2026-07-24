<?php

namespace App\Http\Requests\Auth;

use App\Models\LoginAudit;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email'], 'password' => ['required', 'string'], 'remember' => ['nullable', 'boolean']];
    }

    public function authenticate(): void
    {
        $key = Str::lower($this->string('email')).'|'.$this->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            event(new Lockout($this));
            throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.']);
        }
        $credentials = $this->only('email', 'password');
        $credentials['is_active'] = true;
        $ok = Auth::attempt($credentials, $this->boolean('remember'));
        LoginAudit::create(['user_id' => $ok ? Auth::id() : null, 'email' => $this->string('email'), 'nrp' => $ok ? Auth::user()?->nrp : null, 'successful' => $ok, 'ip_address' => $this->ip(), 'user_agent' => $this->userAgent()]);
        if (! $ok) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak sesuai.']);
        }
        RateLimiter::clear($key);
    }
}
