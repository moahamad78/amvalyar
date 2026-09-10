<?php

declare(strict_types=1);

namespace App\Services\BulkImport;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetCategoryCodingMapping;
use App\Models\AssetType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Site;
use App\Support\JalaliDate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

final class InitialAssetSetupPreviewService
{
    public function preview(string $path, int $companyId): array
    {
        $spreadsheet = IOFactory::load($path);
        try {
            $this->validateMetadata($spreadsheet, $companyId);
            $sheet = $spreadsheet->getSheet(0);
            $highestRow = $sheet->getHighestDataRow();
            if ($highestRow > InitialAssetSetupTemplateService::MAX_ROWS + 1) {
                throw new RuntimeException('حداکثر تعداد ردیف در هر فایل راه‌اندازی اولیه '.InitialAssetSetupTemplateService::MAX_ROWS.' ردیف است.');
            }

            $categories = AssetCategory::query()->where('is_active', true)->get()
                ->keyBy(fn ($item) => $this->normalize((string) $item->name));
            $types = AssetType::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->get()
                ->keyBy(fn ($item) => $this->normalize((string) $item->name));
            $employees = Employee::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->get()
                ->keyBy(fn ($item) => $this->normalize((string) $item->personnel_code));
            $departments = Department::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->get()
                ->keyBy(fn ($item) => $this->normalize((string) $item->code));
            $sites = Site::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->get()
                ->keyBy(fn ($item) => $this->normalize((string) $item->code));
            $locations = Location::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->get()
                ->keyBy(fn ($item) => $this->normalize((string) $item->code));
            $mappings = AssetCategoryCodingMapping::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)
                ->get()->keyBy('asset_category_id');

            $rows = [];
            $seen = [];
            for ($row = 2; $row <= $highestRow; $row++) {
                $values = [];
                for ($column = 1; $column <= 18; $column++) {
                    $values[$column] = trim((string) $sheet->getCell([$column, $row])->getFormattedValue());
                }
                if ($this->rowIsEmpty($values)) {
                    continue;
                }
                $errors = [];
                $category = $categories->get($this->normalize($values[1]));
                $type = $types->get($this->normalize($values[2]));
                if ($category === null) $errors[] = 'دسته‌بندی معتبر نیست.';
                if ($type === null) $errors[] = 'نوع دارایی معتبر نیست.';
                if ($category && $type && (int) $type->asset_category_id !== (int) $category->id) $errors[] = 'نوع دارایی متعلق به دسته‌بندی نیست.';
                if ($values[4] === '') $errors[] = 'عنوان دارایی الزامی است.';

                $inventory = $values[3] !== '' ? $values[3] : null;
                if ($inventory !== null) {
                    $key = mb_strtolower($inventory);
                    if (isset($seen[$key])) $errors[] = 'کد اموال داخلی داخل فایل تکراری است.';
                    $seen[$key] = true;
                    if (Asset::withoutGlobalScopes()->where('company_id', $companyId)->where('inventory_code', $inventory)->exists()) $errors[] = 'کد اموال داخلی قبلاً ثبت شده است.';
                }

                $custody = $this->custodyType($values[12]);
                if ($custody === null) $errors[] = 'نوع استقرار باید پرسنلی، سازمانی یا انبار باشد.';
                $employee = $this->resolve($employees, $values[13]);
                $department = $this->resolve($departments, $values[14]);
                $site = $this->resolve($sites, $values[15]);
                $location = $this->resolve($locations, $values[16]);
                $requestedCodingSite = $this->resolve($sites, $values[17]);

                if ($custody === 'employee') {
                    if ($values[13] === '') $errors[] = 'برای استقرار پرسنلی کد پرسنلی الزامی است.';
                    elseif ($employee === null) $errors[] = 'کد پرسنلی فعال و معتبر نیست.';
                    if ($values[14] !== '' || $values[15] !== '' || $values[16] !== '') $errors[] = 'برای دارایی پرسنلی فقط کد پرسنلی را وارد کنید؛ محل از مشخصات پرسنل خوانده می‌شود.';
                }
                if ($custody === 'organization') {
                    if ($department === null && $site === null && $location === null) $errors[] = 'برای دارایی سازمانی حداقل کد واحد، سایت یا محل لازم است.';
                    if ($values[14] !== '' && $department === null) $errors[] = 'کد واحد معتبر نیست.';
                    if ($values[15] !== '' && $site === null) $errors[] = 'کد سایت معتبر نیست.';
                    if ($values[16] !== '' && $location === null) $errors[] = 'کد محل معتبر نیست.';
                    if ($site && $location && $location->site_id !== null && (int) $location->site_id !== (int) $site->id) $errors[] = 'محل انتخاب‌شده متعلق به سایت انتخاب‌شده نیست.';
                }
                if ($custody === 'warehouse' && ($values[13] !== '' || $values[14] !== '' || $values[15] !== '' || $values[16] !== '')) $errors[] = 'برای استقرار انبار، اطلاعات مقصد فعلی را خالی بگذارید.';

                $purchaseDate = null;
                if ($values[10] !== '') {
                    try { $purchaseDate = JalaliDate::toGregorianDate($values[10]); }
                    catch (Throwable) { $errors[] = 'تاریخ خرید معتبر نیست.'; }
                }
                $purchasePrice = null;
                if ($values[11] !== '') {
                    $numeric = str_replace([',', '٬', ' '], '', $values[11]);
                    if (!is_numeric($numeric) || (float) $numeric < 0) $errors[] = 'مبلغ خرید معتبر نیست.';
                    else $purchasePrice = $numeric;
                }

                $codingSite = $requestedCodingSite;
                if ($values[17] !== '' && $codingSite === null) $errors[] = 'کد سایت کدگذاری معتبر نیست.';
                if ($codingSite === null) $codingSite = $site;
                if ($codingSite === null && $location && $location->site_id) $codingSite = $sites->first(fn ($item) => (int) $item->id === (int) $location->site_id);
                if ($codingSite === null && $employee) {
                    $codingSite = $employee->site_id ? $sites->first(fn ($item) => (int) $item->id === (int) $employee->site_id) : $codingSite;
                    if ($employee->location_id) {
                        $employeeLocation = $locations->first(fn ($item) => (int) $item->id === (int) $employee->location_id);
                        if ($employeeLocation?->site_id) $codingSite = $sites->first(fn ($item) => (int) $item->id === (int) $employeeLocation->site_id);
                    }
                }
                if ($codingSite === null) $errors[] = 'سایت مبنای کدگذاری برای این دارایی مشخص نیست.';
                $mapping = $category ? $mappings->get($category->id) : null;
                if ($category && $mapping === null) $errors[] = 'برای دسته‌بندی، نگاشت کد اموال تعریف نشده است.';
                elseif ($mapping && preg_match('/^[A-Za-z0-9]+$/', trim((string) $mapping->coding_code)) !== 1) $errors[] = 'کدگذاری دسته‌بندی معتبر نیست.';
                if ($type && preg_match('/^[A-Za-z0-9]+$/', trim((string) $type->coding_code)) !== 1) $errors[] = 'برای نوع دارایی، کدگذاری معتبر تعریف نشده است.';

                $data = [
                    'company_id' => $companyId, 'asset_category_id' => $category?->id, 'asset_type_id' => $type?->id,
                    'inventory_code' => $inventory, 'title' => $values[4], 'brand' => $values[5] ?: null,
                    'model' => $values[6] ?: null, 'serial_number' => $values[7] ?: null, 'manufacturer' => $values[8] ?: null,
                    'country' => $values[9] ?: null, 'purchase_date' => $purchaseDate, 'purchase_price' => $purchasePrice,
                    'description' => $values[18] ?: null, 'custody_type' => $custody, 'employee_id' => $employee?->id,
                    'department_id' => $department?->id, 'site_id' => $site?->id, 'location_id' => $location?->id,
                    'coding_site_id' => $codingSite?->id,
                    'employee_label' => $employee ? ($employee->personnel_code.' | '.$employee->display_name) : null,
                    'department_label' => $department ? ($department->code.' | '.$department->name) : null,
                    'site_label' => $site ? ($site->code.' | '.$site->name) : null,
                    'location_label' => $location ? ($location->code.' | '.$location->name) : null,
                ];
                $rows[] = ['excel_row' => $row, 'valid' => $errors === [], 'errors' => $errors, 'data' => $data];
            }
            $valid = count(array_filter($rows, fn (array $item): bool => $item['valid']));
            return ['total_rows' => count($rows), 'valid_rows' => $valid, 'error_rows' => count($rows) - $valid, 'can_commit' => count($rows) > 0 && $valid === count($rows), 'rows' => $rows];
        } finally { $spreadsheet->disconnectWorksheets(); }
    }

    private function validateMetadata($spreadsheet, int $companyId): void
    {
        $meta = $spreadsheet->getSheetByName('_meta');
        if ($meta === null) throw new RuntimeException('برگه مشخصات قالب موجود نیست.');
        $metadata = [];
        for ($row = 2; $row <= $meta->getHighestRow(); $row++) {
            $key = trim((string) $meta->getCell("A{$row}")->getValue());
            if ($key !== '') $metadata[$key] = $meta->getCell("B{$row}")->getValue();
        }
        if ((string) ($metadata['template_type'] ?? '') !== InitialAssetSetupTemplateService::TEMPLATE_TYPE) throw new RuntimeException('قالب راه‌اندازی اولیه معتبر نیست.');
        if ((string) ($metadata['template_version'] ?? '') !== InitialAssetSetupTemplateService::TEMPLATE_VERSION) throw new RuntimeException('نسخه قالب راه‌اندازی اولیه پشتیبانی نمی‌شود.');
        if ((int) ($metadata['company_id'] ?? 0) !== $companyId) throw new RuntimeException('قالب متعلق به شرکت دیگری است.');
    }

    private function resolve($items, string $value): mixed
    {
        if ($value === '') return null;
        $value = trim(explode('|', $value, 2)[0]);
        return $items->get($this->normalize($value));
    }
    private function custodyType(string $value): ?string
    {
        return match ($this->normalize($value)) {
            'پرسنلی', 'employee', 'personal' => 'employee',
            'سازمانی', 'organization', 'org' => 'organization',
            'انبار', 'warehouse' => 'warehouse',
            default => null,
        };
    }
    private function normalize(string $value): string
    {
        $value = str_replace(['ي', 'ك'], ['ی', 'ک'], trim($value));
        return mb_strtolower(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
    private function rowIsEmpty(array $values): bool
    {
        foreach ($values as $value) if (trim((string) $value) !== '') return false;
        return true;
    }
}
