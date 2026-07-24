<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use App\Support\CompanyAccess;

class AttendanceController extends Controller
{
    public function index(Request $r)
    {
        $q = Attendance::with('employee')->whereHas('employee', fn ($query) => $query->whereIn('company_id', CompanyAccess::ids($r->user())))->latest('date');

        return view('attendance.index', ['attendances' => $q->paginate(20)]);
    }

    public function check(Request $r)
    {
        abort_unless($r->user()->can('attendance.update') || $r->user()->hasRole('super_admin'), 403);
        $r->validate(['latitude' => 'nullable|numeric|between:-90,90', 'longitude' => 'nullable|numeric|between:-180,180']);
        abort_unless($r->user()->employee_id, 422, 'Akun belum terhubung ke karyawan.');
        $record = Attendance::firstOrCreate(['employee_id' => $r->user()->employee_id, 'date' => today()], ['status' => 'present']);
        if (! $record->check_in_at) {
            $record->update(['check_in_at' => now(), 'latitude_in' => $r->latitude, 'longitude_in' => $r->longitude]);
        } elseif (! $record->check_out_at) {
            $record->update(['check_out_at' => now()]);
        } else {
            return back()->withErrors(['attendance' => 'Check-in dan check-out hari ini sudah lengkap.']);
        }

        return back()->with('success', 'Kehadiran berhasil direkam.');
    }
}
