<?php

namespace Database\Seeders\Setup;

use App\Models\Setup\SetupReferenceItem;
use Illuminate\Database\Seeder;

class SetupReferenceItemSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            // -----------------------------------------------------------------
            // 02. Employee Information Setup (12 categories)
            // -----------------------------------------------------------------
            'gender' => [
                ['code' => 'MALE', 'name' => 'Male'],
                ['code' => 'FEMALE', 'name' => 'Female'],
                ['code' => 'NON_BINARY', 'name' => 'Non-Binary'],
                ['code' => 'PREFER_NOT', 'name' => 'Prefer Not to Say'],
            ],
            'civil_status' => [
                ['code' => 'SINGLE', 'name' => 'Single', 'is_default' => true],
                ['code' => 'MARRIED', 'name' => 'Married'],
                ['code' => 'WIDOWED', 'name' => 'Widowed'],
                ['code' => 'SEPARATED', 'name' => 'Legally Separated'],
                ['code' => 'ANNULLED', 'name' => 'Annulled'],
                ['code' => 'DIVORCED', 'name' => 'Divorced'],
            ],
            'blood_type' => [
                ['code' => 'A_POS', 'name' => 'A+'],
                ['code' => 'A_NEG', 'name' => 'A-'],
                ['code' => 'B_POS', 'name' => 'B+'],
                ['code' => 'B_NEG', 'name' => 'B-'],
                ['code' => 'AB_POS', 'name' => 'AB+'],
                ['code' => 'AB_NEG', 'name' => 'AB-'],
                ['code' => 'O_POS', 'name' => 'O+'],
                ['code' => 'O_NEG', 'name' => 'O-'],
            ],
            'country' => [
                ['code' => 'PH', 'name' => 'Philippines', 'is_default' => true, 'metadata' => ['iso3' => 'PHL', 'dial_code' => '+63']],
                ['code' => 'US', 'name' => 'United States', 'metadata' => ['iso3' => 'USA', 'dial_code' => '+1']],
                ['code' => 'SG', 'name' => 'Singapore', 'metadata' => ['iso3' => 'SGP', 'dial_code' => '+65']],
                ['code' => 'JP', 'name' => 'Japan', 'metadata' => ['iso3' => 'JPN', 'dial_code' => '+81']],
                ['code' => 'AU', 'name' => 'Australia', 'metadata' => ['iso3' => 'AUS', 'dial_code' => '+61']],
                ['code' => 'GB', 'name' => 'United Kingdom', 'metadata' => ['iso3' => 'GBR', 'dial_code' => '+44']],
                ['code' => 'CA', 'name' => 'Canada', 'metadata' => ['iso3' => 'CAN', 'dial_code' => '+1']],
            ],
            'nationality' => [
                ['code' => 'FILIPINO', 'name' => 'Filipino', 'is_default' => true],
                ['code' => 'AMERICAN', 'name' => 'American'],
                ['code' => 'SINGAPOREAN', 'name' => 'Singaporean'],
                ['code' => 'JAPANESE', 'name' => 'Japanese'],
                ['code' => 'AUSTRALIAN', 'name' => 'Australian'],
                ['code' => 'BRITISH', 'name' => 'British'],
                ['code' => 'CANADIAN', 'name' => 'Canadian'],
            ],
            'citizenship' => [
                ['code' => 'PH_CITIZEN', 'name' => 'Filipino Citizen', 'is_default' => true],
                ['code' => 'DUAL_CITIZEN', 'name' => 'Dual Citizen'],
                ['code' => 'FOREIGN_RESIDENT', 'name' => 'Foreign National / Resident'],
            ],
            'religion' => [
                ['code' => 'ROMAN_CATHOLIC', 'name' => 'Roman Catholic'],
                ['code' => 'PROTESTANT', 'name' => 'Protestant / Born Again Christian'],
                ['code' => 'INC', 'name' => 'Iglesia ni Cristo'],
                ['code' => 'ISLAM', 'name' => 'Islam'],
                ['code' => 'BUDDHISM', 'name' => 'Buddhism'],
                ['code' => 'HINDUISM', 'name' => 'Hinduism'],
                ['code' => 'OTHER', 'name' => 'Other'],
                ['code' => 'NONE', 'name' => 'None / Prefer Not to Say'],
            ],
            'educational_attainment' => [
                ['code' => 'ELEM', 'name' => 'Elementary Graduate'],
                ['code' => 'HS', 'name' => 'High School / Senior High School'],
                ['code' => 'VOCATIONAL', 'name' => 'Vocational / Technical Diploma'],
                ['code' => 'BACHELORS', 'name' => "Bachelor's Degree", 'is_default' => true],
                ['code' => 'MASTERS', 'name' => "Master's Degree"],
                ['code' => 'DOCTORATE', 'name' => 'Doctorate / PhD'],
            ],
            'degree_course' => [
                ['code' => 'BSCS', 'name' => 'BS in Computer Science', 'parent_code' => 'BACHELORS'],
                ['code' => 'BSIT', 'name' => 'BS in Information Technology', 'parent_code' => 'BACHELORS'],
                ['code' => 'BSA', 'name' => 'BS in Accountancy', 'parent_code' => 'BACHELORS'],
                ['code' => 'BSBA', 'name' => 'BS in Business Administration', 'parent_code' => 'BACHELORS'],
                ['code' => 'BSPSYCH', 'name' => 'BS in Psychology', 'parent_code' => 'BACHELORS'],
                ['code' => 'MBA', 'name' => 'Master of Business Administration', 'parent_code' => 'MASTERS'],
                ['code' => 'MSCS', 'name' => 'MS in Computer Science', 'parent_code' => 'MASTERS'],
                ['code' => 'PHD_CS', 'name' => 'PhD in Computer Science', 'parent_code' => 'DOCTORATE'],
            ],
            'relationship_type' => [
                ['code' => 'SPOUSE', 'name' => 'Spouse'],
                ['code' => 'CHILD', 'name' => 'Child'],
                ['code' => 'PARENT', 'name' => 'Parent'],
                ['code' => 'SIBLING', 'name' => 'Sibling'],
                ['code' => 'GUARDIAN', 'name' => 'Legal Guardian'],
                ['code' => 'RELATIVE', 'name' => 'Other Relative'],
                ['code' => 'FRIEND', 'name' => 'Friend / Colleague'],
            ],
            'language' => [
                ['code' => 'EN', 'name' => 'English', 'is_default' => true],
                ['code' => 'FIL', 'name' => 'Filipino / Tagalog'],
                ['code' => 'CEB', 'name' => 'Cebuano / Bisaya'],
                ['code' => 'ILO', 'name' => 'Ilocano'],
                ['code' => 'HIL', 'name' => 'Hiligaynon'],
                ['code' => 'ZH', 'name' => 'Mandarin Chinese'],
                ['code' => 'JA', 'name' => 'Japanese'],
                ['code' => 'ES', 'name' => 'Spanish'],
            ],
            'disability_type' => [
                ['code' => 'NONE', 'name' => 'None', 'is_default' => true],
                ['code' => 'VISUAL', 'name' => 'Visual Disability'],
                ['code' => 'HEARING', 'name' => 'Deaf or Hard of Hearing'],
                ['code' => 'SPEECH', 'name' => 'Speech and Language Impairment'],
                ['code' => 'ORTHOPEDIC', 'name' => 'Physical / Orthopedic Disability'],
                ['code' => 'PSYCHOSOCIAL', 'name' => 'Psychosocial Disability'],
                ['code' => 'LEARNING', 'name' => 'Learning Disability'],
                ['code' => 'CHRONIC', 'name' => 'Chronic Illness / Rare Disease'],
            ],

            // -----------------------------------------------------------------
            // 03. Employment Setup (8 categories)
            // -----------------------------------------------------------------
            'employment_type' => [
                ['code' => 'REGULAR', 'name' => 'Regular / Permanent', 'is_default' => true],
                ['code' => 'PROBATIONARY', 'name' => 'Probationary'],
                ['code' => 'CONTRACTUAL', 'name' => 'Contractual / Fixed-Term'],
                ['code' => 'PROJECT', 'name' => 'Project-Based'],
                ['code' => 'PART_TIME', 'name' => 'Part-Time'],
                ['code' => 'CASUAL', 'name' => 'Casual / Seasonal'],
                ['code' => 'CONSULTANT', 'name' => 'Consultant'],
                ['code' => 'INTERN', 'name' => 'Intern / OJT'],
            ],
            'employment_status' => [
                ['code' => 'ACTIVE', 'name' => 'Active', 'is_default' => true],
                ['code' => 'ON_LEAVE', 'name' => 'On Extended Leave'],
                ['code' => 'SUSPENDED', 'name' => 'Suspended'],
                ['code' => 'FLOATING', 'name' => 'Floating Status'],
                ['code' => 'SEPARATED', 'name' => 'Separated'],
                ['code' => 'RETIRED', 'name' => 'Retired'],
            ],
            'employee_category' => [
                ['code' => 'ADMIN', 'name' => 'Administrative Staff', 'is_default' => true],
                ['code' => 'FACULTY', 'name' => 'Faculty / Academic'],
                ['code' => 'TECHNICAL', 'name' => 'Technical / IT'],
                ['code' => 'OPERATIONS', 'name' => 'Operations & Support'],
                ['code' => 'EXECUTIVE', 'name' => 'Executive Leadership'],
            ],
            'appointment_type' => [
                ['code' => 'ORIGINAL', 'name' => 'Original / New Hire', 'is_default' => true],
                ['code' => 'REGULARIZATION', 'name' => 'Regularization'],
                ['code' => 'PROMOTION', 'name' => 'Promotion'],
                ['code' => 'TRANSFER', 'name' => 'Transfer / Lateral Movement'],
                ['code' => 'RECLASSIFICATION', 'name' => 'Reclassification'],
                ['code' => 'RENEWAL', 'name' => 'Contract Renewal'],
                ['code' => 'REHIRE', 'name' => 'Rehire'],
            ],
            'employment_classification' => [
                ['code' => 'RANK_FILE', 'name' => 'Rank-and-File', 'is_default' => true],
                ['code' => 'SUPERVISORY', 'name' => 'Supervisory / Team Lead'],
                ['code' => 'MANAGERIAL', 'name' => 'Managerial'],
                ['code' => 'EXECUTIVE', 'name' => 'Executive / Officer'],
            ],
            'work_arrangement' => [
                ['code' => 'ONSITE', 'name' => 'On-Site', 'is_default' => true],
                ['code' => 'HYBRID', 'name' => 'Hybrid'],
                ['code' => 'REMOTE', 'name' => 'Work From Home / Remote'],
                ['code' => 'FIELD', 'name' => 'Field-Based'],
                ['code' => 'COMPRESSED', 'name' => 'Compressed Workweek'],
            ],
            'separation_type' => [
                ['code' => 'RESIGNATION', 'name' => 'Voluntary Resignation'],
                ['code' => 'RETIREMENT', 'name' => 'Retirement'],
                ['code' => 'TERMINATION', 'name' => 'Termination for Cause'],
                ['code' => 'END_CONTRACT', 'name' => 'End of Contract'],
                ['code' => 'RETRENCHMENT', 'name' => 'Redundancy / Retrenchment'],
                ['code' => 'AWOL', 'name' => 'AWOL / Abandonment'],
            ],
            'separation_reason' => [
                ['code' => 'CAREER_GROWTH', 'name' => 'Better Career Opportunity', 'parent_code' => 'RESIGNATION'],
                ['code' => 'RELOCATION', 'name' => 'Personal / Family Relocation', 'parent_code' => 'RESIGNATION'],
                ['code' => 'HEALTH', 'name' => 'Health Reasons', 'parent_code' => 'RESIGNATION'],
                ['code' => 'MANDATORY_RET', 'name' => 'Mandatory Age Retirement', 'parent_code' => 'RETIREMENT'],
                ['code' => 'OPTIONAL_RET', 'name' => 'Optional / Early Retirement', 'parent_code' => 'RETIREMENT'],
                ['code' => 'POLICY_VIOLATION', 'name' => 'Serious Code of Conduct Violation', 'parent_code' => 'TERMINATION'],
                ['code' => 'CONTRACT_COMPLETION', 'name' => 'Project / Fixed-Term Completion', 'parent_code' => 'END_CONTRACT'],
            ],

            // -----------------------------------------------------------------
            // 04. Administrative Setup (5 reference categories)
            // -----------------------------------------------------------------
            'document_type' => [
                ['code' => 'CONTRACT', 'name' => 'Employment Contract / Appointment Letter'],
                ['code' => 'RESUME', 'name' => 'Resume / Curriculum Vitae'],
                ['code' => 'GOV_ID', 'name' => 'Government-Issued ID'],
                ['code' => 'DIPLOMA', 'name' => 'Diploma / Transcript of Records'],
                ['code' => 'COE', 'name' => 'Certificate of Employment'],
                ['code' => 'MEDICAL', 'name' => 'Medical Certificate / Fit-to-Work'],
                ['code' => 'PERFORMANCE', 'name' => 'Performance Evaluation'],
                ['code' => 'MEMO', 'name' => 'Official Memorandum / Notice'],
            ],
            'disciplinary_action_type' => [
                ['code' => 'VERBAL_WARN', 'name' => 'Verbal Warning / Coaching', 'metadata' => ['severity' => 1]],
                ['code' => 'WRITTEN_WARN', 'name' => 'First Written Warning', 'metadata' => ['severity' => 2]],
                ['code' => 'FINAL_WARN', 'name' => 'Final Written Warning', 'metadata' => ['severity' => 3]],
                ['code' => 'SUSPENSION', 'name' => 'Suspension Without Pay', 'metadata' => ['severity' => 4]],
                ['code' => 'DEMOTION', 'name' => 'Demotion', 'metadata' => ['severity' => 5]],
                ['code' => 'DISMISSAL', 'name' => 'Dismissal / Termination', 'metadata' => ['severity' => 6]],
            ],
            'training_category' => [
                ['code' => 'ONBOARDING', 'name' => 'Onboarding & Orientation'],
                ['code' => 'COMPLIANCE', 'name' => 'Compliance, Ethics & Safety'],
                ['code' => 'TECHNICAL', 'name' => 'Technical & Job-Specific Skills'],
                ['code' => 'LEADERSHIP', 'name' => 'Leadership & Management'],
                ['code' => 'SOFT_SKILLS', 'name' => 'Communication & Soft Skills'],
                ['code' => 'CERTIFICATION', 'name' => 'Professional Certification / Licensure'],
            ],
            'award_recognition_type' => [
                ['code' => 'LOYALTY', 'name' => 'Years of Service / Loyalty Award'],
                ['code' => 'EXCELLENCE', 'name' => 'Employee Excellence Award'],
                ['code' => 'PERFECT_ATT', 'name' => 'Perfect Attendance Award'],
                ['code' => 'INNOVATION', 'name' => 'Innovation & Process Improvement Award'],
                ['code' => 'LEADERSHIP', 'name' => 'Outstanding Leadership Recognition'],
                ['code' => 'COMMENDATION', 'name' => 'Special Commendation'],
            ],
            'employee_requirement_type' => [
                ['code' => 'NBI_CLEARANCE', 'name' => 'NBI / Police Clearance', 'metadata' => ['is_mandatory' => true, 'stage' => 'pre_employment']],
                ['code' => 'BIR_2316', 'name' => 'BIR Form 2316 / TIN Verification', 'metadata' => ['is_mandatory' => true, 'stage' => 'onboarding']],
                ['code' => 'SSS_ID', 'name' => 'SSS Number / E-1 Form', 'metadata' => ['is_mandatory' => true, 'stage' => 'onboarding']],
                ['code' => 'PHILHEALTH', 'name' => 'PhilHealth MDR', 'metadata' => ['is_mandatory' => true, 'stage' => 'onboarding']],
                ['code' => 'PAGIBIG', 'name' => 'Pag-IBIG MID Number', 'metadata' => ['is_mandatory' => true, 'stage' => 'onboarding']],
                ['code' => 'PRE_MED', 'name' => 'Pre-Employment Medical Exam', 'metadata' => ['is_mandatory' => true, 'stage' => 'pre_employment']],
                ['code' => 'PSA_BIRTH', 'name' => 'PSA Birth Certificate', 'metadata' => ['is_mandatory' => true, 'stage' => 'pre_employment']],
                ['code' => 'TOR', 'name' => 'Transcript of Records / Diploma', 'metadata' => ['is_mandatory' => false, 'stage' => 'pre_employment']],
            ],
        ];

        $createdByCategoryAndCode = [];

        foreach ($catalog as $category => $items) {
            $group = SetupReferenceItem::groupForCategory($category);
            $parentCategory = SetupReferenceItem::parentCategoryFor($category);

            foreach ($items as $index => $entry) {
                $parentId = null;
                if ($parentCategory !== null && isset($entry['parent_code'])) {
                    $parentId = $createdByCategoryAndCode[$parentCategory][$entry['parent_code']]
                        ?? SetupReferenceItem::where('category', $parentCategory)
                            ->where('code', $entry['parent_code'])
                            ->value('id');
                }

                $item = SetupReferenceItem::firstOrCreate(
                    ['category' => $category, 'code' => $entry['code']],
                    [
                        'group' => $group,
                        'name' => $entry['name'],
                        'description' => $entry['description'] ?? null,
                        'parent_id' => $parentId,
                        'metadata' => $entry['metadata'] ?? null,
                        'sort_order' => $index + 1,
                        'is_default' => (bool) ($entry['is_default'] ?? false),
                        'is_active' => true,
                    ]
                );

                $createdByCategoryAndCode[$category][$entry['code']] = $item->id;
            }
        }
    }
}
