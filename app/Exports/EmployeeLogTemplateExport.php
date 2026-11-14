<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeLogTemplateExport implements FromArray, ShouldAutoSize, WithStyles
{
    public function array(): array
    {
        return [
            ['DATE', 'EMPLOYEE ID', 'NAME', 'TIME IN', 'TIME OUT', 'TIME IN', 'TIME OUT'],
            ['', '', '', '(AM)', '(AM)', '(PM)', '(PM)'],
            ['9/14/2024', 'EMP-20260001', 'DOE, JANE', '8:00 AM', '12:00 PM', '1:00 PM', '4:00 PM'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
            2 => ['font' => ['bold' => true]],
        ];
    }
}
