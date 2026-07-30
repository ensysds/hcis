<?php

namespace App\Models;

use App\Support\FixedSuperadmin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Employee extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'join_date' => 'date',
        'end_date' => 'date',
        'birth_date' => 'date',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'permanent_appointment_date' => 'date',
        'exit_date' => 'date',
        'retirement_date' => 'date',
        'marriage_date' => 'date',
        'spouse_birth_date' => 'date',
        'father_birth_date' => 'date',
        'mother_birth_date' => 'date',
        'father_in_law_birth_date' => 'date',
        'mother_in_law_birth_date' => 'date',
        'height_cm' => 'float',
        'weight_kg' => 'float',
        'shoe_size' => 'float',
        'core_is_active' => 'boolean',
        'core_must_change_password' => 'boolean',
        'core_password_reset_requested_at' => 'datetime',
        'core_last_login_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $employee) {
            $employee->core_role ??= 'employee';
            $employee->core_is_active ??= true;
            $employee->core_must_change_password ??= true;

            if (blank($employee->core_password) && $employee->nrp && $employee->join_date && $employee->birth_date) {
                $employee->core_password = Hash::make($employee->defaultCorePassword());
            }
        });
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function manager()
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function coreTokens()
    {
        return $this->hasMany(CoreApiToken::class);
    }

    public function coreAccessRoles()
    {
        return $this->belongsToMany(CoreAccessRole::class, 'employee_core_access_role')
            ->withPivot(['scope_type', 'scope_id'])
            ->withTimestamps();
    }

    public function children()
    {
        return $this->hasMany(EmployeeChild::class)->orderBy('sequence');
    }

    public function contracts() { return $this->hasMany(EmploymentContract::class); }
    public function actions() { return $this->hasMany(EmployeeAction::class); }
    public function hcCases() { return $this->hasMany(HcCase::class); }
    public function assetAssignments() { return $this->hasMany(AssetAssignment::class); }

    public function getGenerationAttribute(): ?string
    {
        if (! $this->birth_date) {
            return null;
        }

        return match (true) {
            $this->birth_date->year <= 1964 => 'Baby Boomer',
            $this->birth_date->year <= 1980 => 'Generasi X',
            $this->birth_date->year <= 1996 => 'Milenial',
            $this->birth_date->year <= 2012 => 'Generasi Z',
            default => 'Generasi Alpha',
        };
    }

    public function getContractDurationAttribute(): ?string
    {
        return $this->periodDuration($this->contract_start_date, $this->contract_end_date);
    }

    public function getPermanentTenureAttribute(): ?string
    {
        return $this->periodDuration($this->permanent_appointment_date, $this->exit_date ?? now());
    }

    public function defaultCorePassword(): string
    {
        return substr($this->nrp, -4).Carbon::parse($this->join_date)->format('dmy').Carbon::parse($this->birth_date)->format('dmy');
    }

    public function resetCorePasswordToDefault(): void
    {
        if (FixedSuperadmin::isEmail($this->email)) {
            $this->forceFill([
                'core_password' => FixedSuperadmin::passwordHash(),
                'core_must_change_password' => false,
                'core_password_reset_requested_at' => null,
            ])->save();

            return;
        }

        $this->forceFill([
            'core_password' => Hash::make($this->defaultCorePassword()),
            'core_must_change_password' => true,
            'core_password_reset_requested_at' => null,
        ])->save();
    }

    public function canLoginToCore(): bool
    {
        return $this->status === 'active' && $this->core_is_active && filled($this->core_password);
    }

    private function periodDuration($start, $end): ?string
    {
        if (! $start || ! $end || $end->lt($start)) {
            return null;
        }

        return $start->diff($end)->format('%y tahun %m bulan %d hari');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
