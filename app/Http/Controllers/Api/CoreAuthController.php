<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoreApiToken;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Support\CoreAccess;

class CoreAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:190'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $identifier = trim($credentials['login']);
        $employee = Employee::query()
            ->with(['company', 'department', 'position'])
            ->where(fn ($query) => $query
                ->where('nrp', $identifier)
                ->orWhere('email', $identifier))
            ->first();

        if (! $employee || ! $employee->canLoginToCore() || ! Hash::check($credentials['password'], $employee->core_password)) {
            return response()->json(['message' => 'Akun Core karyawan atau kata sandi tidak sesuai.'], 422);
        }

        CoreApiToken::where('expires_at', '<', now())->delete();
        $plainToken = Str::random(80);
        CoreApiToken::create([
            'employee_id' => $employee->id,
            'name' => $credentials['device_name'] ?? 'Core Web',
            'token_hash' => hash('sha256', $plainToken),
            'last_used_at' => now(),
            'expires_at' => now()->addHours(12),
        ]);

        $employee->update(['core_last_login_at' => now()]);
        activity('authentication')->performedOn($employee)->withProperties(['channel' => 'core'])->log('Login Core berhasil');

        return response()->json([
            'token' => $plainToken,
            'expires_at' => now()->addHours(12)->toIso8601String(),
            'user' => $this->userPayload($employee),
        ]);
    }

    public function session(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        activity('authentication')->performedOn($request->user())->withProperties(['channel' => 'core'])->log('Logout Core');
        $request->attributes->get('core_api_token')?->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    private function userPayload(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->full_name,
            'email' => $employee->email,
            'nrp' => $employee->nrp,
            'company' => $employee->company?->name,
            'department' => $employee->department?->name,
            'position' => $employee->position?->name,
            'roles' => [$employee->core_role],
            'modules' => CoreAccess::modulesFor($employee)->pluck('key')->values(),
            'must_change_password' => $employee->core_must_change_password,
        ];
    }
}
