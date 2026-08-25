<?php

declare(strict_types=1);

namespace App\Services\BulkImport;

use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\Company;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

final class AssetImportTemplateService
{
    public const TEMPLATE_TYPE = 'assets';
    public const TEMPLATE_VERSION = '1.0';

    public function generate(int $companyId): string
    {
        $company = Company::withoutGlobalScopes()->find($companyId);

        if ($company === null) {
            throw new RuntimeException('Company not found.');
        }

        $spreadsheet = new Spreadsheet();
        $input = $spreadsheet->getActiveSheet();
        $input->setTitle('دارایی‌ها');

        $headers = [
            'دسته‌بندی *',
            'نوع دارایی *',
            'کد اموال',
            'عنوان *',
            'برند',
            'مدل',
            'شماره سریال',
            'سازنده',
            'کشور',
            'تاریخ خرید',
            'مبلغ خرید',
            'توضیحات',
            'وضعیت',
        ];

        foreach ($headers as $index => $header) {
            $input->setCellValue([$index + 1, 1], $header);
        }

        $input->freezePane('A2');
        $input->setRightToLeft(true);
        $input->getStyle('A1:M1')->getFont()->setBold(true);
        $input->getStyle('A1:M1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $input->getStyle('A1:M1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE2E8F0');

        foreach (range('A', 'M') as $column) {
            $input->getColumnDimension($column)->setWidth(20);
        }

        $guide = $spreadsheet->createSheet();
        $guide->setTitle('راهنما');
        $guide->setRightToLeft(true);
        $guide->fromArray([
            ['راهنمای ورود گروهی دارایی‌ها'],
            ['شرکت', $company->name ?? ('#' . $companyId)],
            ['نسخه قالب', self::TEMPLATE_VERSION],
            [],
            ['نکته'],
            ['دسته‌بندی و نوع دارایی را فقط از فهرست انتخاب کنید.'],
            ['نوع دارایی باید متعلق به همان دسته‌بندی باشد.'],
            ['کد اموال در صورت ورود، داخل همان شرکت باید یکتا باشد.'],
            ['دارایی جدید با وضعیت انبار وارد می‌شود؛ تحویل/انتقال از گردش دارایی انجام شود.'],
            ['تاریخ خرید را به صورت شمسی مانند 1405/05/24 وارد کنید.'],
        ]);

        $lists = $spreadsheet->createSheet();
        $lists->setTitle('_lists');
        $lists->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VERYHIDDEN);

        $lists->fromArray([
            ['category_label', 'category_id', 'type_label', 'type_id', 'type_category_id', 'status_label', 'status_value'],
        ]);

        $categories = AssetCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $types = AssetType::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('asset_category_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'asset_category_id', 'name']);

        $row = 2;
        foreach ($categories as $category) {
            $lists->setCellValue("A{$row}", (string) $category->name);
            $lists->setCellValue("B{$row}", (int) $category->id);
            $row++;
        }
        $categoryEnd = max(2, $row - 1);

        $row = 2;
        foreach ($types as $type) {
            $lists->setCellValue("C{$row}", (string) $type->name);
            $lists->setCellValue("D{$row}", (int) $type->id);
            $lists->setCellValue("E{$row}", (int) $type->asset_category_id);
            $row++;
        }
        $typeEnd = max(2, $row - 1);

        $lists->setCellValue('F2', 'انبار');
        $lists->setCellValue('G2', 'warehouse');

        $spreadsheet->addNamedRange(
            new NamedRange('BI_ASSET_CATEGORIES', $lists, '$A$2:$A$' . $categoryEnd)
        );
        $spreadsheet->addNamedRange(
            new NamedRange('BI_ASSET_TYPES', $lists, '$C$2:$C$' . $typeEnd)
        );
        $spreadsheet->addNamedRange(
            new NamedRange('BI_ASSET_STATUSES', $lists, '$F$2:$F$2')
        );

        for ($excelRow = 2; $excelRow <= 2000; $excelRow++) {
            foreach ([
                'A' => '=BI_ASSET_CATEGORIES',
                'B' => '=BI_ASSET_TYPES',
                'M' => '=BI_ASSET_STATUSES',
            ] as $column => $formula) {
                $validation = new DataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank($column !== 'A' && $column !== 'B');
                $validation->setShowDropDown(true);
                $validation->setShowErrorMessage(true);
                $validation->setFormula1($formula);
                $input->getCell($column . $excelRow)->setDataValidation($validation);
            }
        }

        $meta = $spreadsheet->createSheet();
        $meta->setTitle('_meta');
        $meta->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VERYHIDDEN);
        $meta->fromArray([
            ['key', 'value'],
            ['template_type', self::TEMPLATE_TYPE],
            ['template_version', ''],
            ['company_id', $companyId],
            ['generated_at', now()->toIso8601String()],
        ]);

        $meta->setCellValueExplicit(
            'B3',
            self::TEMPLATE_VERSION,
            DataType::TYPE_STRING
        );

        $directory = storage_path('app/bulk-import/templates');

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create template directory.');
        }

        $path = $directory . DIRECTORY_SEPARATOR
            . 'assets-company-' . $companyId . '-' . now()->format('Ymd-His') . '.xlsx';

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}