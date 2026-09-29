<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeInfo extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeInfoFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_no',  
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

}