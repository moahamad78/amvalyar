<?php

declare(strict_types=1);

namespace App\Services\BulkImport;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Site;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class EmployeeImportTemplateService
{
    public const TEMPLATE_TYPE = 'employees';
    public const TEMPLATE_VERSION = '1.0';
    public const MAX_ROWS = 2000;

    public function build(Company $company): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('پرسنل');
        $sheet->setRightToLeft(true);

        $headers = [
            'کد پرسنلی *',
            'نام نمایشی *',
            'نام',
            'نام خانوادگی',
            'عنوان شغلی',
            'کد ملی',
            'ایمیل',
            'موبایل/تلفن',
            'سایت',
            'واحد سازمانی',
            'محل استقرار',
            'مدیر مستقیم',
            'وضعیت',
            'توضیحات',
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:N1');

        $sheet->getStyle('A1:N1')->getFont()->setBold(true);
        $sheet->getStyle('A1:N1')
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFE2E8F0');

        $sheet->getStyle('A1:N' . self::MAX_ROWS)
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);

        foreach ([
            'A' => 18, 'B' => 26, 'C' => 18, 'D' => 22,
            'E' => 24, 'F' => 18, 'G' => 28, 'H' => 20,
            'I' => 30, 'J' => 30, 'K' => 48, 'L' => 36,
            'M' => 16, 'N' => 42,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $references = $this->buildReferenceSheet($spreadsheet, $company);

        $this->applyListValidation(
            $spreadsheet, $sheet, 'I',
            'BI_EMPLOYEE_SITES',
            $references['sites']
        );

        $this->applyListValidation(
            $spreadsheet, $sheet, 'J',
            'BI_EMPLOYEE_DEPARTMENTS',
            $references['departments']
        );

        $this->applyListValidation(
            $spreadsheet, $sheet, 'K',
            'BI_EMPLOYEE_LOCATIONS',
            $references['locations']
        );

        $this->applyListValidation(
            $spreadsheet, $sheet, 'L',
            'BI_EMPLOYEE_MANAGERS',
            $references['managers']
        );

        $this->applyListValidation(
            $spreadsheet, $sheet, 'M',
            'BI_EMPLOYEE_STATUSES',
            $references['statuses']
        );

        $this->buildInstructionsSheet($spreadsheet, $company);
        $this->buildMetaSheet($spreadsheet, $company);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function buildReferenceSheet(
        Spreadsheet $spreadsheet,
        Company $company
    ): array {
        $sheet = new Worksheet($spreadsheet, '_lists');
        $spreadsheet->addSheet($sheet);

        $sheet->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);

        $sheet->fromArray([
            'site_label',
            'site_id',
            'department_label',
            'department_id',
            'location_label',
            'location_id',
            'manager_label',
            'manager_id',
            'status_label',
            'status_value',
        ], null, 'A1');

        $sites = Site::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $departments = Department::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $locations = Location::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->with(['site', 'parent.parent'])
            ->orderBy('site_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $managers = Employee::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        foreach ($sites as $index => $site) {
            $row = $index + 2;
            $sheet->setCellValue(
                'A' . $row,
                $this->referenceLabel($site->code, $site->name)
            );
            $sheet->setCellValue('B' . $row, $site->id);
        }

        foreach ($departments as $index => $department) {
            $row = $index + 2;
            $sheet->setCellValue(
                'C' . $row,
                $this->referenceLabel($department->code, $department->name)
            );
            $sheet->setCellValue('D' . $row, $department->id);
        }

        foreach ($locations as $index => $location) {
            $row = $index + 2;
            $sheet->setCellValue(
                'E' . $row,
                $this->locationLabel($location)
            );
            $sheet->setCellValue('F' . $row, $location->id);
        }

        foreach ($managers as $index => $manager) {
            $row = $index + 2;
            $sheet->setCellValue(
                'G' . $row,
                trim($manager->personnel_code . ' | ' . $manager->display_name)
            );
            $sheet->setCellValue('H' . $row, $manager->id);
        }

        $statuses = [
            ['فعال', 1],
            ['غیرفعال', 0],
        ];

        foreach ($statuses as $index => [$label, $value]) {
            $row = $index + 2;
            $sheet->setCellValue('I' . $row, $label);
            $sheet->setCellValue('J' . $row, $value);
        }

        return [
            'sites' => [
                'sheet' => $sheet,
                'column' => 'A',
                'count' => max(1, $sites->count()),
            ],
            'departments' => [
                'sheet' => $sheet,
                'column' => 'C',
                'count' => max(1, $departments->count()),
            ],
            'locations' => [
                'sheet' => $sheet,
                'column' => 'E',
                'count' => max(1, $locations->count()),
            ],
            'managers' => [
                'sheet' => $sheet,
                'column' => 'G',
                'count' => max(1, $managers->count()),
            ],
            'statuses' => [
                'sheet' => $sheet,
                'column' => 'I',
                'count' => count($statuses),
            ],
        ];
    }

    private function applyListValidation(
        Spreadsheet $spreadsheet,
        Worksheet $sheet,
        string $column,
        string $rangeName,
        array $reference
    ): void {
        $lastRow = 1 + (int) $reference['count'];

        $spreadsheet->addNamedRange(
            new NamedRange(
                $rangeName,
                $reference['sheet'],
                '$' . $reference['column'] . '$2:$'
                    . $reference['column'] . '$' . $lastRow
            )
        );

        for ($row = 2; $row <= self::MAX_ROWS; $row++) {
            $validation = $sheet
                ->getCell($column . $row)
                ->getDataValidation();

            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('گزینه نامعتبر');
            $validation->setError(
                'لطفاً یکی از گزینه‌های موجود در فهرست را انتخاب کنید.'
            );
            $validation->setFormula1('=' . $rangeName);
        }
    }

    private function buildInstructionsSheet(
        Spreadsheet $spreadsheet,
        Company $company
    ): void {
        $sheet = new Worksheet($spreadsheet, 'راهنما');
        $spreadsheet->addSheet($sheet, 1);
        $sheet->setRightToLeft(true);

        $rows = [
            ['راهنمای ورود گروهی پرسنل'],
            ['شرکت', $company->name],
            ['زمان تولید فایل', now()->format('Y-m-d H:i:s')],
            [''],
            ['نکات مهم'],
            ['1', 'ستون‌های دارای * الزامی هستند.'],
            ['2', 'سایت، واحد، محل استقرار، مدیر مستقیم و وضعیت را فقط از فهرست کشویی انتخاب کنید.'],
            ['3', 'این فهرست‌ها هنگام دانلود فایل از اطلاعات زنده سیستم ساخته شده‌اند.'],
            ['4', 'اگر بعد از دانلود فایل، ساختار سازمانی تغییر کند، هنگام آپلود دوباره با اطلاعات فعلی سیستم اعتبارسنجی می‌شود.'],
            ['5', 'کد پرسنلی باید در همان شرکت یکتا باشد.'],
            ['6', 'اگر محل استقرار انتخاب شود و سایت هم انتخاب شده باشد، محل باید متعلق به همان سایت باشد.'],
            ['7', 'ردیف‌های کاملاً خالی نادیده گرفته می‌شوند.'],
            ['8', 'در V1 ابتدا فایل اعتبارسنجی و پیش‌نمایش می‌شود؛ ثبت نهایی پس از تأیید فعال خواهد شد.'],
        ];

        $sheet->fromArray($rows, null, 'A1');
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(95);
        $sheet->getStyle('A1:B20')->getAlignment()->setWrapText(true);
    }

    private function buildMetaSheet(
        Spreadsheet $spreadsheet,
        Company $company
    ): void {
        $sheet = new Worksheet($spreadsheet, '_meta');
        $spreadsheet->addSheet($sheet);

        $sheet->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);

        $sheet->fromArray([
            ['key', 'value'],
            ['template_type', self::TEMPLATE_TYPE],
            ['template_version', ''],
            ['company_id', $company->id],
            ['generated_at', now()->toIso8601String()],
        ], null, 'A1');

        $sheet->setCellValueExplicit(
            'B3',
            self::TEMPLATE_VERSION,
            DataType::TYPE_STRING
        );
    }

    private function referenceLabel(
        ?string $code,
        string $name
    ): string {
        $code = trim((string) $code);

        return $code !== ''
            ? $code . ' | ' . $name
            : $name;
    }

    private function locationLabel(
        Location $location
    ): string {
        $parts = [];

        if ($location->site !== null) {
            $parts[] = $this->referenceLabel(
                $location->site->code,
                $location->site->name
            );
        }

        $chain = [];

        if ($location->parent?->parent !== null) {
            $chain[] = $location->parent->parent->name;
        }

        if ($location->parent !== null) {
            $chain[] = $location->parent->name;
        }

        $chain[] = $this->referenceLabel(
            $location->code,
            $location->name
        );

        $parts[] = implode(
            ' > ',
            array_values(
                array_unique(
                    array_filter($chain)
                )
            )
        );

        return implode(' | ', $parts);
    }
}