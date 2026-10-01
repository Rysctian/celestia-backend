<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory;
    protected $fillable = [
        'employee_id',
        'type',
        'first_name',
        'middle_name',
        'last_name',
        'name_extension',
        'birth_date',
        'birth_place',
        'gender',
        'personal_email',
        'mobile_no',
        'telephone_no',
    ];

    public function user(){
        return $this->hasOne(User::class, 'employee_id', 'employee_id');
    }

    public function schedules()
    {
        return $this->hasMany(EmployeeSchedule::class, 'employee_id', 'employee_id');
    }

}
