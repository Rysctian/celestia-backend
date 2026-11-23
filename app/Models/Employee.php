<?php

namespace App\Models;

use App\Models\Setup\Department;
use App\Models\Setup\Division;
use App\Models\Setup\Office;
use App\Models\Setup\Position;
use App\Models\Setup\Section;
use App\Queries\EmployeeQuery;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use EmployeeQuery, HasFactory;

    protected $fillable = [
        'employee_id',
        'type',
        'office_id',
        'department_id',
        'division_id',
        'section_id',
        'position_id',
        'reports_to_employee_id',
        'first_name',
        'middle_name',
        'last_name',
        'name_extension',
        'birth_date',
        'birth_place',
        'gender',
        'civil_status',
        'blood_type',
        'country',
        'nationality',
        'citizenship',
        'religion',
        'educational_attainment',
        'degree_course',
        'languages',
        'disability_type',
        'personal_email',
        'mobile_no',
        'telephone_no',
        'current_address',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_no',
        'employment_status',
        'employee_category',
        'appointment_type',
        'employment_classification',
        'work_arrangement',
        'work_email',
        'date_hired',
        'regularization_date',
        'separation_type',
        'separation_reason',
        'separation_date',
        'tin_no',
        'sss_no',
        'philhealth_no',
        'pagibig_no',
    ];

    protected function casts(): array
    {
        return [
            'office_id' => 'integer',
            'department_id' => 'integer',
            'division_id' => 'integer',
            'section_id' => 'integer',
            'position_id' => 'integer',
            'languages' => 'array',
        ];
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function reportsTo()
    {
        return $this->belongsTo(self::class, 'reports_to_employee_id', 'employee_id');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'employee_id', 'employee_id');
    }

    public function schedules()
    {
        return $this->hasMany(EmployeeSchedule::class, 'employee_id', 'employee_id');
    }

    public function logs()
    {
        return $this->hasMany(EmployeeLog::class, 'employee_id', 'employee_id');
    }

    public function attendance()
    {
        return $this->hasMany(EmployeeAttendance::class, 'employee_id', 'employee_id');
    }
}
