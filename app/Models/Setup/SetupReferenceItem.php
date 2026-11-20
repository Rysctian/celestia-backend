<?php

namespace App\Models\Setup;

use App\Queries\Setup\SetupReferenceItemQuery;
use Illuminate\Database\Eloquent\Model;

class SetupReferenceItem extends Model
{
    use SetupReferenceItemQuery;

    public const GROUP_EMPLOYEE_INFO = 'employee_info';

    public const GROUP_EMPLOYMENT = 'employment';

    public const GROUP_ADMINISTRATIVE = 'administrative';

    public const GROUPS = [
        self::GROUP_EMPLOYEE_INFO,
        self::GROUP_EMPLOYMENT,
        self::GROUP_ADMINISTRATIVE,
    ];

    /**
     * Canonical category catalog grouped by HRIS setup module.
     * Each category declares its group, display label, description, and optional parent category.
     */
    public const CATEGORY_CATALOG = [
        // 02. Employee Information Setup
        'gender' => [
            'group' => self::GROUP_EMPLOYEE_INFO,
            'label' => 'Gender',
            'description' => 'Gender identity options for employee personal profiles.',
            'parent_category' => null,
        ],
        'civil_status' => [
            'group' => self::GROUP_EMPLOYEE_INFO,
            'label' => 'Civil Status',
            'description' => 'Marital and civil status classifications.',
            'parent_category' => null,
        ],
        'blood_type' => [
            'group' => self::GROUP_EMPLOYEE_INFO,
            'label' => 'Blood Type',
            'description' => 'Standard ABO and Rh blood type options for medical records.',
            'parent_category' => null,
        ],
        'country' => [
            'group' => self::GROUP_EMPLOYEE_INFO,
            'label' => 'Country',
            'description' => 'Countries used for addresses, birth places, and travel.',
            'parent_category' => null,
        ],
        'nationality' => [
            'group' => self::GROUP_EMPLOYEE_INFO,
            'label' => 'Nationality',
            'description' => 'Employee nationality options.',
            'parent_category' => null,
        ],
        'citizenship' => [
            'group' => self::GROUP_EMPLOYEE_INFO,
            'label' => 'Citizenship',
            'description' => 'Legal citizenship designations.',
            'parent_category' => null,
        ],
        'religion' => [
            'group' => self::GROUP_EMPLOYEE_INFO,
            'label' => 'Religion',
            'description' => 'Religious affiliation options.',
            'parent_category' => null,
        ],
        'educational_attainment' => [
            'group' => self::GROUP_EMPLOYEE_INFO,
            'label' => 'Educational Attainment',
            'description' => 'Highest completed education levels.',
            'parent_category' => null,
        ],
        'degree_course' => [
            'group' => self::GROUP_EMPLOYEE_INFO,
            'label' => 'Degree / Course',
            'description' => 'Academic degrees, courses, and specialization programs.',
            'parent_category' => 'educational_attainment',
        ],
        'relationship_type' => [
            'group' => self::GROUP_EMPLOYEE_INFO,
            'label' => 'Relationship Type',
            'description' => 'Family, dependent, and emergency contact relationship types.',
            'parent_category' => null,
        ],
        'language' => [
            'group' => self::GROUP_EMPLOYEE_INFO,
            'label' => 'Language',
            'description' => 'Spoken and written languages for employee skills profiles.',
            'parent_category' => null,
        ],
        'disability_type' => [
            'group' => self::GROUP_EMPLOYEE_INFO,
            'label' => 'Disability Type',
            'description' => 'Accessibility and PWD disability classifications.',
            'parent_category' => null,
        ],

        // 03. Employment Setup
        'employment_type' => [
            'group' => self::GROUP_EMPLOYMENT,
            'label' => 'Employment Type',
            'description' => 'Nature of employment engagement (Regular, Probationary, Contractual, etc.).',
            'parent_category' => null,
        ],
        'employment_status' => [
            'group' => self::GROUP_EMPLOYMENT,
            'label' => 'Employment Status',
            'description' => 'Current lifecycle status of an employee (Active, On Leave, Separated, etc.).',
            'parent_category' => null,
        ],
        'employee_category' => [
            'group' => self::GROUP_EMPLOYMENT,
            'label' => 'Employee Category',
            'description' => 'Workforce groupings (Faculty, Administrative, Technical, Executive, etc.).',
            'parent_category' => null,
        ],
        'appointment_type' => [
            'group' => self::GROUP_EMPLOYMENT,
            'label' => 'Appointment Type',
            'description' => 'Hiring or movement appointment nature (Original, Promotion, Renewal, etc.).',
            'parent_category' => null,
        ],
        'employment_classification' => [
            'group' => self::GROUP_EMPLOYMENT,
            'label' => 'Employment Classification',
            'description' => 'Rank and job tier classifications (Rank-and-File, Supervisory, Managerial).',
            'parent_category' => null,
        ],
        'work_arrangement' => [
            'group' => self::GROUP_EMPLOYMENT,
            'label' => 'Work Arrangement',
            'description' => 'Work setup modes (On-site, Hybrid, Remote, Field, Flexible).',
            'parent_category' => null,
        ],
        'separation_type' => [
            'group' => self::GROUP_EMPLOYMENT,
            'label' => 'Separation Type',
            'description' => 'High-level offboarding categories (Resignation, Retirement, Termination, etc.).',
            'parent_category' => null,
        ],
        'separation_reason' => [
            'group' => self::GROUP_EMPLOYMENT,
            'label' => 'Separation Reason',
            'description' => 'Specific offboarding reasons optionally linked to a Separation Type.',
            'parent_category' => 'separation_type',
        ],

        // 04. Administrative Setup (reference categories; Clearance uses dedicated relational tables)
        'document_type' => [
            'group' => self::GROUP_ADMINISTRATIVE,
            'label' => 'Document Type',
            'description' => '201 file and HR document classifications.',
            'parent_category' => null,
        ],
        'disciplinary_action_type' => [
            'group' => self::GROUP_ADMINISTRATIVE,
            'label' => 'Disciplinary Action Type',
            'description' => 'Corrective and disciplinary action levels.',
            'parent_category' => null,
        ],
        'training_category' => [
            'group' => self::GROUP_ADMINISTRATIVE,
            'label' => 'Training Category',
            'description' => 'Learning, compliance, and professional development categories.',
            'parent_category' => null,
        ],
        'award_recognition_type' => [
            'group' => self::GROUP_ADMINISTRATIVE,
            'label' => 'Award / Recognition Type',
            'description' => 'Service awards, commendations, and performance recognition types.',
            'parent_category' => null,
        ],
        'employee_requirement_type' => [
            'group' => self::GROUP_ADMINISTRATIVE,
            'label' => 'Employee Requirement Type',
            'description' => 'Pre-employment, onboarding, and statutory compliance requirements.',
            'parent_category' => null,
        ],
    ];

    protected $fillable = [
        'group',
        'category',
        'code',
        'name',
        'description',
        'parent_id',
        'metadata',
        'sort_order',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'metadata' => 'array',
            'sort_order' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public static function categoriesForGroup(string $group): array
    {
        $categories = [];

        foreach (self::CATEGORY_CATALOG as $key => $meta) {
            if ($meta['group'] === $group) {
                $categories[$key] = $meta;
            }
        }

        return $categories;
    }

    public static function categoryKeysForGroup(string $group): array
    {
        return array_keys(self::categoriesForGroup($group));
    }

    public static function groupForCategory(string $category): ?string
    {
        return self::CATEGORY_CATALOG[$category]['group'] ?? null;
    }

    public static function parentCategoryFor(string $category): ?string
    {
        return self::CATEGORY_CATALOG[$category]['parent_category'] ?? null;
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }
}
