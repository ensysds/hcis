<?php

namespace App\Http\Controllers;

use App\Support\FixedSuperadmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('profile.edit');
    }

    public function update(Request $r)
    {
        $data = $r->validate(['current_password' => 'required|current_password', 'password' => 'required|confirmed|min:8']);
        if (FixedSuperadmin::isEmail($r->user()?->email)) {
            $r->user()->update(['password' => FixedSuperadmin::passwordHash()]);

            return back()->with('success', 'Password superadmin tetap mengikuti konfigurasi akun production.');
        }
        $r->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Kata sandi diperbarui.');
    }
}
