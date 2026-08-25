<?php

declare(strict_types=1);

namespace App\Services\BulkImport;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

final class EmployeeImportPreviewService
{
    public function preview(
        UploadedFile $file,
        Company $company
    ): array {
        $spreadsheet = IOFactory::load($file->getRealPath());

        $this->validateMeta($spreadsheet, $company);

        $sheet = $spreadsheet->getSheetByName('پرسنل');

        if ($sheet === null) {
            throw new RuntimeException(
                'Sheet پرسنل در فایل پیدا نشد.'
            );
        }

        $highestRow = min(
            $sheet->getHighestDataRow(),
            EmployeeImportTemplateService::MAX_ROWS
        );

        $maps = $this->referenceMaps($company);

        $existingPersonnelCodes = Employee::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->pluck('id', 'personnel_code')
            ->mapWithKeys(
                fn ($id, $code) => [
                    $this->normalize((string) $code) => $id,
                ]
            )
            ->all();

        $existingNationalCodes = Employee::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNotNull('national_code')
            ->pluck('id', 'national_code')
            ->mapWithKeys(
                fn ($id, $code) => [
                    $this->normalize((string) $code) => $id,
                ]
            )
            ->all();

        $seenPersonnel = [];
        $seenNational = [];
        $rows = [];
        $validCount = 0;
        $errorCount = 0;

        for ($row = 2; $row <= $highestRow; $row++) {
            $values = $sheet->rangeToArray(
                'A' . $row . ':N' . $row,
                null,
                true,
                true,
                false
            )[0];

            if ($this->rowIsEmpty($values)) {
                continue;
            }

            $data = [
                'personnel_code' => $this->text($values[0] ?? null),
                'display_name' => $this->text($values[1] ?? null),
                'first_name' => $this->nullableText($values[2] ?? null),
                'last_name' => $this->nullableText($values[3] ?? null),
                'job_title' => $this->nullableText($values[4] ?? null),
                'national_code' => $this->nullableText($values[5] ?? null),
                'email' => $this->nullableText($values[6] ?? null),
                'phone' => $this->nullableText($values[7] ?? null),
                'site_label' => $this->nullableText($values[8] ?? null),
                'department_label' => $this->nullableText($values[9] ?? null),
                'location_label' => $this->nullableText($values[10] ?? null),
                'manager_label' => $this->nullableText($values[11] ?? null),
                'status_label' => $this->nullableText($values[12] ?? null),
                'description' => $this->nullableText($values[13] ?? null),
            ];

            $errors = [];

            if ($data['personnel_code'] === '') {
                $errors[] = 'کد پرسنلی الزامی است.';
            }

            if ($data['display_name'] === '') {
                $errors[] = 'نام نمایشی الزامی است.';
            }

            if (mb_strlen($data['personnel_code']) > 50) {
                $errors[] = 'کد پرسنلی بیش از 50 کاراکتر است.';
            }

            if (mb_strlen($data['display_name']) > 255) {
                $errors[] = 'نام نمایشی بیش از 255 کاراکتر است.';
            }

            if (
                $data['email'] !== null
                && !filter_var(
                    $data['email'],
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                $errors[] = 'ایمیل معتبر نیست.';
            }

            $personnelKey = $this->normalize(
                $data['personnel_code']
            );

            if (
                $personnelKey !== ''
                && isset($existingPersonnelCodes[$personnelKey])
            ) {
                $errors[] =
                    'کد پرسنلی قبلاً در این شرکت ثبت شده است.';
            }

            if (
                $personnelKey !== ''
                && isset($seenPersonnel[$personnelKey])
            ) {
                $errors[] =
                    'کد پرسنلی داخل همین فایل تکراری است؛ ردیف '
                    . $seenPersonnel[$personnelKey]
                    . '.';
            }

            if ($personnelKey !== '') {
                $seenPersonnel[$personnelKey] = $row;
            }

            if ($data['national_code'] !== null) {
                $nationalKey = $this->normalize(
                    $data['national_code']
                );

                if (isset($existingNationalCodes[$nationalKey])) {
                    $errors[] =
                        'کد ملی قبلاً در این شرکت ثبت شده است.';
                }

                if (isset($seenNational[$nationalKey])) {
                    $errors[] =
                        'کد ملی داخل همین فایل تکراری است؛ ردیف '
                        . $seenNational[$nationalKey]
                        . '.';
                }

                $seenNational[$nationalKey] = $row;
            }

            $siteId = $this->resolveReference(
                $data['site_label'],
                $maps['sites'],
                'سایت',
                $errors
            );

            $departmentId = $this->resolveReference(
                $data['department_label'],
                $maps['departments'],
                'واحد سازمانی',
                $errors
            );

            $locationId = $this->resolveReference(
                $data['location_label'],
                $maps['locations'],
                'محل استقرار',
                $errors
            );

            $managerId = $this->resolveReference(
                $data['manager_label'],
                $maps['managers'],
                'مدیر مستقیم',
                $errors
            );

            $isActive = $this->resolveStatus(
                $data['status_label'],
                $errors
            );

            if (
                $locationId !== null
                && $siteId !== null
            ) {
                $location =
                    $maps['location_models'][$locationId]
                    ?? null;

                if (
                    $location === null
                    || (int) $location->site_id
                        !== (int) $siteId
                ) {
                    $errors[] =
                        'محل استقرار متعلق به سایت انتخاب‌شده نیست.';
                }
            }

            if (
                $locationId !== null
                && $siteId === null
            ) {
                $location =
                    $maps['location_models'][$locationId]
                    ?? null;

                $siteId = $location?->site_id;
            }

            $resolved = [
                'company_id' => $company->id,
                'site_id' => $siteId,
                'department_id' => $departmentId,
                'location_id' => $locationId,
                'manager_employee_id' => $managerId,
                'is_active' => $isActive,
            ];

            $valid = count($errors) === 0;

            if ($valid) {
                $validCount++;
            } else {
                $errorCount++;
            }

            $rows[] = [
                'excel_row' => $row,
                'valid' => $valid,
                'errors' => $errors,
                'data' => $data,
                'resolved' => $resolved,
            ];
        }

        return [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
            ],
            'rows' => $rows,
            'total_rows' => count($rows),
            'valid_rows' => $validCount,
            'error_rows' => $errorCount,
            'can_commit' =>
                count($rows) > 0
                && $errorCount === 0,
        ];
    }

    private function validateMeta(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet,
        Company $company
    ): void {
        $sheet = $spreadsheet->getSheetByName('_meta');

        if ($sheet === null) {
            throw new RuntimeException(
                'این فایل نمونه معتبر ورود گروهی سیستم نیست.'
            );
        }

        $values = [];

        for (
            $row = 2;
            $row <= $sheet->getHighestDataRow();
            $row++
        ) {
            $key = trim(
                (string) $sheet
                    ->getCell('A' . $row)
                    ->getValue()
            );

            $value = trim(
                (string) $sheet
                    ->getCell('B' . $row)
                    ->getValue()
            );

            if ($key !== '') {
                $values[$key] = $value;
            }
        }

        if (
            ($values['template_type'] ?? null)
            !== EmployeeImportTemplateService::TEMPLATE_TYPE
        ) {
            throw new RuntimeException(
                'نوع فایل نمونه با ورود گروهی پرسنل سازگار نیست.'
            );
        }

        if (
            !$this->versionsMatch(
                (string) (
                    $values['template_version']
                    ?? ''
                ),
                EmployeeImportTemplateService::TEMPLATE_VERSION
            )
        ) {
            throw new RuntimeException(
                'نسخه فایل نمونه پشتیبانی نمی‌شود؛ فایل نمونه جدید دانلود کنید.'
            );
        }

        if (
            (int) ($values['company_id'] ?? 0)
            !== (int) $company->id
        ) {
            throw new RuntimeException(
                'این فایل نمونه برای شرکت دیگری تولید شده است.'
            );
        }
    }

    private function versionsMatch(
        string $actual,
        string $expected
    ): bool {
        $actual = trim($actual);
        $expected = trim($expected);

        if ($actual === $expected) {
            return true;
        }

        if (
            is_numeric($actual)
            && is_numeric($expected)
        ) {
            return (float) $actual
                === (float) $expected;
        }

        return false;
    }

    private function referenceMaps(
        Company $company
    ): array {
        $sites = Site::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->get();

        $departments = Department::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->get();

        $locations = Location::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->with(['site', 'parent.parent'])
            ->get();

        $managers = Employee::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->get();

        $siteMap = [];
        $departmentMap = [];
        $locationMap = [];
        $managerMap = [];
        $locationModels = [];

        foreach ($sites as $site) {
            $label = $this->referenceLabel(
                $site->code,
                $site->name
            );

            $siteMap[$this->normalize($label)] = $site->id;
        }

        foreach ($departments as $department) {
            $label = $this->referenceLabel(
                $department->code,
                $department->name
            );

            $departmentMap[
                $this->normalize($label)
            ] = $department->id;
        }

        foreach ($locations as $location) {
            $label = $this->locationLabel($location);

            $locationMap[
                $this->normalize($label)
            ] = $location->id;

            $locationModels[$location->id] = $location;
        }

        foreach ($managers as $manager) {
            $label = trim(
                $manager->personnel_code
                . ' | '
                . $manager->display_name
            );

            $managerMap[
                $this->normalize($label)
            ] = $manager->id;
        }

        return [
            'sites' => $siteMap,
            'departments' => $departmentMap,
            'locations' => $locationMap,
            'managers' => $managerMap,
            'location_models' => $locationModels,
        ];
    }

    private function resolveReference(
        ?string $label,
        array $map,
        string $fieldLabel,
        array &$errors
    ): ?int {
        if ($label === null) {
            return null;
        }

        $key = $this->normalize($label);

        if (!array_key_exists($key, $map)) {
            $errors[] =
                $fieldLabel
                . ' انتخاب‌شده دیگر در فهرست فعال سیستم وجود ندارد.';

            return null;
        }

        return (int) $map[$key];
    }

    private function resolveStatus(
        ?string $label,
        array &$errors
    ): bool {
        if ($label === null) {
            return true;
        }

        return match ($this->normalize($label)) {
            $this->normalize('فعال') => true,
            $this->normalize('غیرفعال') => false,

            default => (function () use (&$errors): bool {
                $errors[] =
                    'وضعیت باید از فهرست فعال/غیرفعال انتخاب شود.';

                return true;
            })(),
        };
    }

    private function rowIsEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) ($value ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function nullableText(mixed $value): ?string
    {
        $value = $this->text($value);

        return $value === ''
            ? null
            : $value;
    }

    private function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value));

        $value = str_replace(
            [
                "\u{200C}",
                "\u{200F}",
                "\u{200E}",
            ],
            '',
            $value
        );

        return preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? $value;
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