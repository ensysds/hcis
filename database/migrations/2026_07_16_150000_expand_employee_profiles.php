<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('pt')->nullable();
            $table->string('organizational_unit')->nullable();
            $table->string('sbu')->nullable();
            $table->string('directorate')->nullable();
            $table->string('division')->nullable();
            $table->string('section')->nullable();
            $table->string('core_support')->nullable();
            $table->string('work_location')->nullable();
            $table->string('floor', 30)->nullable();
            $table->string('placement')->nullable();
            $table->string('personnel_area')->nullable();
            $table->string('subarea')->nullable();
            $table->string('employee_group')->nullable();
            $table->string('grade', 50)->nullable();
            $table->string('rank', 100)->nullable();
            $table->string('poh')->nullable();
            $table->string('poh_type', 50)->nullable();

            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->date('permanent_appointment_date')->nullable();
            $table->date('exit_date')->nullable();
            $table->date('retirement_date')->nullable();

            $table->string('education_level', 50)->nullable();
            $table->string('school_name')->nullable();
            $table->string('major')->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable();

            $table->string('residence_city')->nullable();
            $table->string('residence_province')->nullable();
            $table->string('residence_postal_code', 10)->nullable();
            $table->text('mailing_address')->nullable();
            $table->string('mailing_city')->nullable();
            $table->string('mailing_province')->nullable();
            $table->string('mailing_postal_code', 10)->nullable();
            $table->string('home_phone', 30)->nullable();
            $table->string('personal_email')->nullable();

            $table->string('religion', 30)->nullable();
            $table->string('bpjs_employment_number', 50)->nullable();
            $table->string('bpjs_health_number', 50)->nullable();
            $table->string('npwp', 30)->nullable();
            $table->string('family_card_number', 30)->nullable();

            $table->string('bank_key', 50)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('payee')->nullable();
            $table->string('payment_method', 30)->nullable();

            $table->string('blood_type', 3)->nullable();
            $table->string('rhesus', 10)->nullable();
            $table->decimal('height_cm', 5, 2)->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->string('shirt_size', 10)->nullable();
            $table->string('pants_size', 10)->nullable();
            $table->decimal('shoe_size', 4, 1)->nullable();

            $table->string('tax_status', 10)->nullable();
            $table->date('marriage_date')->nullable();
            $table->string('spouse_name')->nullable();
            $table->string('spouse_birth_place')->nullable();
            $table->date('spouse_birth_date')->nullable();

            foreach (['father', 'mother', 'father_in_law', 'mother_in_law'] as $relative) {
                $table->string($relative.'_name')->nullable();
                $table->date($relative.'_birth_date')->nullable();
                $table->string($relative.'_birth_place')->nullable();
            }

            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_gender', 1)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->text('emergency_contact_address')->nullable();
            $table->string('emergency_contact_relationship', 50)->nullable();
        });

        Schema::create('employee_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('name');
            $table->string('gender', 1)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_children');

        Schema::table('employees', function (Blueprint $table) {
            $columns = [
                'pt', 'organizational_unit', 'sbu', 'directorate', 'division', 'section', 'core_support',
                'work_location', 'floor', 'placement', 'personnel_area', 'subarea', 'employee_group',
                'grade', 'rank', 'poh', 'poh_type', 'contract_start_date', 'contract_end_date',
                'permanent_appointment_date', 'exit_date', 'retirement_date', 'education_level',
                'school_name', 'major', 'graduation_year', 'residence_city', 'residence_province',
                'residence_postal_code', 'mailing_address', 'mailing_city', 'mailing_province',
                'mailing_postal_code', 'home_phone', 'personal_email', 'religion',
                'bpjs_employment_number', 'bpjs_health_number', 'npwp', 'family_card_number',
                'bank_key', 'bank_account', 'payee', 'payment_method', 'blood_type', 'rhesus',
                'height_cm', 'weight_kg', 'shirt_size', 'pants_size', 'shoe_size', 'tax_status',
                'marriage_date', 'spouse_name', 'spouse_birth_place', 'spouse_birth_date',
                'father_name', 'father_birth_date', 'father_birth_place', 'mother_name',
                'mother_birth_date', 'mother_birth_place', 'father_in_law_name',
                'father_in_law_birth_date', 'father_in_law_birth_place', 'mother_in_law_name',
                'mother_in_law_birth_date', 'mother_in_law_birth_place', 'emergency_contact_name',
                'emergency_contact_gender', 'emergency_contact_phone', 'emergency_contact_address',
                'emergency_contact_relationship',
            ];
            $table->dropColumn($columns);
        });
    }
};
