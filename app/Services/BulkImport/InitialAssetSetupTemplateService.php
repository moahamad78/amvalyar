<?php

declare(strict_types=1);

namespace App\Services\BulkImport;

use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Site;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class InitialAssetSetupTemplateService
{
    public const TEMPLATE_TYPE = 'initial_asset_setup';
    public const TEMPLATE_VERSION = '1.0';
    // 2,000 rows keeps the workbook practical while covering the expected
    // opening-balance batches; additional rows can be uploaded in another batch.
    public const MAX_ROWS = 2000;

    public function build(Company $company): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('موجودی اولیه');
        $sheet->setRightToLeft(true);

        $headers = [
            'دسته‌بندی *', 'نوع دارایی *', 'کد اموال داخلی', 'عنوان *',
            'برند', 'مدل', 'شماره سریال', 'سازنده', 'کشور', 'تاریخ خرید',
            'مبلغ خرید', 'نوع استقرار *', 'کد پرسنلی', 'کد واحد', 'کد سایت فعلی',
            'کد محل فعلی', 'کد سایت کدگذاری *', 'توضیحات',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:R1');
        $sheet->getStyle('A1:R1')->getFont()->setBold(true);
        $sheet->getStyle('A1:R1')->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE2E8F0');
        $sheet->getStyle('A1:R'.self::MAX_ROWS)->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);

        foreach (['A'=>24,'B'=>28,'C'=>20,'D'=>34,'E'=>20,'F'=>20,'G'=>24,
            'H'=>22,'I'=>18,'J'=>18,'K'=>18,'L'=>18,'M'=>20,'N'=>20,
            'O'=>20,'P'=>20,'Q'=>20,'R'=>44] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $lists = new Worksheet($spreadsheet, '_lists');
        $spreadsheet->addSheet($lists);
        $lists->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
        $lists->fromArray([
            'category','type','custody','employee','department','site','location',
        ], null, 'A1');

        $categories = AssetCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $types = AssetType::withoutGlobalScopes()->where('company_id', $company->id)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $employees = Employee::withoutGlobalScopes()->where('company_id', $company->id)->where('is_active', true)->orderBy('personnel_code')->get();
        $departments = Department::withoutGlobalScopes()->where('company_id', $company->id)->where('is_active', true)->orderBy('code')->get();
        $sites = Site::withoutGlobalScopes()->where('company_id', $company->id)->where('is_active', true)->orderBy('code')->get();
        $locations = Location::withoutGlobalScopes()->where('company_id', $company->id)->where('is_active', true)->orderBy('code')->get();

        $write = static function (Worksheet $target, string $column, $items, callable $label): int {
            foreach ($items as $index => $item) {
                $target->setCellValue($column.($index + 2), $label($item));
            }
            return max(1, count($items) + 1);
        };
        $categoryCount = $write($lists, 'A', $categories, fn ($item) => $item->name);
        $typeCount = $write($lists, 'B', $types, fn ($item) => $item->name);
        foreach ([['warehouse','انبار'],['employee','پرسنلی'],['organization','سازمانی']] as $index => [$value, $label]) {
            $lists->setCellValue('C'.($index + 2), $label);
        }
        $employeeCount = $write($lists, 'D', $employees, fn ($item) => $item->personnel_code.' | '.$item->display_name);
        $departmentCount = $write($lists, 'E', $departments, fn ($item) => $item->code.' | '.$item->name);
        $siteCount = $write($lists, 'F', $sites, fn ($item) => $item->code.' | '.$item->name);
        $locationCount = $write($lists, 'G', $locations, fn ($item) => $item->code.' | '.$item->name);

        $this->namedRange($spreadsheet, 'BI_INITIAL_SETUP_CATEGORIES', $lists, 'A', $categoryCount);
        $this->namedRange($spreadsheet, 'BI_INITIAL_SETUP_TYPES', $lists, 'B', $typeCount);
        $this->namedRange($spreadsheet, 'BI_INITIAL_SETUP_CUSTODY', $lists, 'C', 4);
        $this->namedRange($spreadsheet, 'BI_INITIAL_SETUP_EMPLOYEES', $lists, 'D', $employeeCount);
        $this->namedRange($spreadsheet, 'BI_INITIAL_SETUP_DEPARTMENTS', $lists, 'E', $departmentCount);
        $this->namedRange($spreadsheet, 'BI_INITIAL_SETUP_SITES', $lists, 'F', $siteCount);
        $this->namedRange($spreadsheet, 'BI_INITIAL_SETUP_LOCATIONS', $lists, 'G', $locationCount);

        $this->validation($sheet, 'A', '=BI_INITIAL_SETUP_CATEGORIES');
        $this->validation($sheet, 'B', '=BI_INITIAL_SETUP_TYPES');
        $this->validation($sheet, 'L', '=BI_INITIAL_SETUP_CUSTODY');
        $this->validation($sheet, 'M', '=BI_INITIAL_SETUP_EMPLOYEES');
        $this->validation($sheet, 'N', '=BI_INITIAL_SETUP_DEPARTMENTS');
        $this->validation($sheet, 'O', '=BI_INITIAL_SETUP_SITES');
        $this->validation($sheet, 'P', '=BI_INITIAL_SETUP_LOCATIONS');
        $this->validation($sheet, 'Q', '=BI_INITIAL_SETUP_SITES');

        $this->instructions($spreadsheet, $company);
        $meta = new Worksheet($spreadsheet, '_meta');
        $spreadsheet->addSheet($meta);
        $meta->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
        $meta->fromArray([
            ['key','value'],
            ['template_type', self::TEMPLATE_TYPE],
            ['template_version', self::TEMPLATE_VERSION],
            ['company_id', (string) $company->id],
            ['generated_at', now()->toIso8601String()],
        ], null, 'A1');
        $spreadsheet->setActiveSheetIndex(0);
        return $spreadsheet;
    }

    private function validation(Worksheet $sheet, string $column, string $formula): void
    {
        for ($row = 2; $row <= self::MAX_ROWS; $row++) {
            $validation = $sheet->getCell($column.$row)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            // PhpSpreadsheet serializes this flag inversely to OOXML's
            // showDropDown attribute. `true` keeps Excel's list arrow visible.
            $validation->setShowDropDown(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setFormula1($formula);
        }
    }

    private function namedRange(Spreadsheet $spreadsheet, string $name, Worksheet $sheet, string $column, int $lastRow): void
    {
        $spreadsheet->addNamedRange(new NamedRange($name, $sheet, '$'.$column.'$2:$'.$column.'$'.$lastRow));
    }

    private function instructions(Spreadsheet $spreadsheet, Company $company): void
    {
        $sheet = new Worksheet($spreadsheet, 'راهنما');
        $spreadsheet->addSheet($sheet);
        $sheet->setRightToLeft(true);
        $sheet->fromArray([
            ['راهنمای ثبت موجودی اولیه - '.$company->name],
            ['این فایل برای دارایی‌هایی است که قبلاً تحویل پرسنل یا مستقر در سازمان شده‌اند. هر ردیف یک دارایی است.'],
            ['نوع استقرار: پرسنلی، سازمانی یا انبار. برای پرسنلی کد پرسنلی و برای سازمانی حداقل یکی از کد واحد/سایت/محل فعلی را وارد کنید.'],
            ['کدهای واحد، سایت، محل و سایت کدگذاری از ساختار سازمانی همان شرکت و کد پرسنلی از فهرست پرسنل فعال انتخاب شود. سایت کدگذاری برای همه ردیف‌ها الزامی است.'],
            ['برای دارایی انباری، نوع استقرار را انبار بگذارید و مقصد فعلی را خالی کنید؛ فقط سایت کدگذاری را برای صدور کد وارد کنید.'],
            ['کد اموال داخلی اختیاری است؛ کد دائمی اموال پس از اعتبارسنجی، خودکار و غیرقابل‌تغییر صادر می‌شود.'],
            ['تاریخ خرید را به‌صورت شمسی مانند ۱۴۰۴/۰۶/۱۹ وارد کنید. مبلغ خرید فقط عدد باشد.'],
            ['قبل از ثبت، دسته‌بندی، نوع دارایی و تنظیمات کدگذاری شرکت باید کامل باشند.'],
        ], null, 'A1');
        $sheet->getColumnDimension('A')->setWidth(125);
        $sheet->getStyle('A1:A8')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    }
}
