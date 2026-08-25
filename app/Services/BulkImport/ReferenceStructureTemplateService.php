<?php

declare(strict_types=1);

namespace App\Services\BulkImport;

use App\Models\Company;
use App\Models\Department;
use App\Models\Site;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

final class ReferenceStructureTemplateService
{
    public const TEMPLATE_TYPE = 'reference_structure';
    public const TEMPLATE_VERSION = '1.0';

    public function generate(int $companyId): string
    {
        $company = Company::withoutGlobalScopes()->find($companyId);

        if ($company === null) {
            throw new RuntimeException('Company not found.');
        }

        $book = new Spreadsheet();

        /*
         * Sheet 1: Sites
         */
        $sites = $book->getActiveSheet();
        $sites->setTitle('سایت‌ها');
        $sites->setRightToLeft(true);

        $sites->fromArray([[
            'نام *',
            'کد *',
            'نوع',
            'آدرس',
            'توضیحات',
            'وضعیت',
            'ترتیب',
        ]]);

        /*
         * Sheet 2: Locations
         * Parent can be an existing DB location or a new row in this same file.
         * Parent reference is by CODE, not label, to avoid Persian spelling drift.
         */
        $locations = $book->createSheet();
        $locations->setTitle('مکان‌ها');
        $locations->setRightToLeft(true);

        $locations->fromArray([[
            'کد سایت *',
            'کد والد',
            'نام *',
            'کد *',
            'نوع *',
            'توضیحات',
            'وضعیت',
            'ترتیب',
        ]]);

        /*
         * Sheet 3: Departments
         * Department has NO site_id in current schema.
         */
        $departments = $book->createSheet();
        $departments->setTitle('واحدها');
        $departments->setRightToLeft(true);

        $departments->fromArray([[
            'کد والد',
            'نام *',
            'کد *',
            'توضیحات',
            'وضعیت',
            'ترتیب',
        ]]);

        /*
         * Guide
         */
        $guide = $book->createSheet();
        $guide->setTitle('راهنما');
        $guide->setRightToLeft(true);

        $guide->fromArray([
            ['راهنمای ورود گروهی ساختار سازمانی'],
            ['شرکت', $company->name ?? ('#' . $companyId)],
            ['نسخه قالب', self::TEMPLATE_VERSION],
            [],
            ['ترتیب ثبت'],
            ['1) سایت‌ها'],
            ['2) مکان‌ها: ساختمان، طبقه، اتاق و ...'],
            ['3) واحدهای سازمانی'],
            [],
            ['قواعد مهم'],
            ['کدها شناسه پایدار هستند؛ نام‌ها می‌توانند تغییر کنند.'],
            ['کد سایت در شیت مکان‌ها می‌تواند سایت موجود یا سایت جدید همین فایل باشد.'],
            ['کد والد مکان می‌تواند مکان موجود یا مکان جدید قبلی در همین فایل باشد.'],
            ['کد والد واحد می‌تواند واحد موجود یا واحد جدید قبلی در همین فایل باشد.'],
            ['واحد سازمانی در ساختار فعلی پروژه مستقیماً به سایت وابسته نیست.'],
        ]);

        /*
         * Hidden dynamic reference lists.
         */
        $lists = $book->createSheet();
        $lists->setTitle('_lists');
        $lists->setSheetState(
            \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VERYHIDDEN
        );

        $lists->fromArray([[
            'site_code',
            'site_id',
            'location_code',
            'location_id',
            'location_site_id',
            'department_code',
            'department_id',
            'status_label',
            'status_value',
            'location_type_label',
            'location_type_value',
        ]]);

        $row = 2;

        foreach (
            Site::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code'])
            as $site
        ) {
            $lists->setCellValue("A{$row}", (string) $site->code);
            $lists->setCellValue("B{$row}", (int) $site->id);
            $row++;
        }

        $siteEnd = max(2, $row - 1);

        $row = 2;

        foreach (
            \App\Models\Location::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'site_id', 'code'])
            as $location
        ) {
            $lists->setCellValue("C{$row}", (string) $location->code);
            $lists->setCellValue("D{$row}", (int) $location->id);
            $lists->setCellValue("E{$row}", (int) $location->site_id);
            $row++;
        }

        $locationEnd = max(2, $row - 1);

        $row = 2;

        foreach (
            Department::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code'])
            as $department
        ) {
            $lists->setCellValue("F{$row}", (string) $department->code);
            $lists->setCellValue("G{$row}", (int) $department->id);
            $row++;
        }

        $departmentEnd = max(2, $row - 1);

        $lists->setCellValue('H2', 'فعال');
        $lists->setCellValue('I2', 1);
        $lists->setCellValue('H3', 'غیرفعال');
        $lists->setCellValue('I3', 0);

        $locationTypes = [
            ['ساختمان', 'building'],
            ['طبقه', 'floor'],
            ['اتاق', 'room'],
            ['انبار', 'warehouse'],
            ['سایر', 'location'],
        ];

        $row = 2;

        foreach ($locationTypes as [$label, $value]) {
            $lists->setCellValue("J{$row}", $label);
            $lists->setCellValue("K{$row}", $value);
            $row++;
        }

        $locationTypeEnd = $row - 1;

        $book->addNamedRange(
            new NamedRange(
                'BI_REF_SITES',
                $lists,
                '$A$2:$A$' . $siteEnd
            )
        );

        $book->addNamedRange(
            new NamedRange(
                'BI_REF_LOCATIONS',
                $lists,
                '$C$2:$C$' . $locationEnd
            )
        );

        $book->addNamedRange(
            new NamedRange(
                'BI_REF_DEPARTMENTS',
                $lists,
                '$F$2:$F$' . $departmentEnd
            )
        );

        $book->addNamedRange(
            new NamedRange(
                'BI_REF_STATUSES',
                $lists,
                '$H$2:$H$3'
            )
        );

        $book->addNamedRange(
            new NamedRange(
                'BI_REF_LOCATION_TYPES',
                $lists,
                '$J$2:$J$' . $locationTypeEnd
            )
        );

        /*
         * Apply styles and validations.
         */
        foreach ([$sites, $locations, $departments] as $sheet) {
            $sheet->freezePane('A2');
            $highest = $sheet->getHighestColumn();

            $sheet->getStyle("A1:{$highest}1")
                ->getFont()
                ->setBold(true);

            $sheet->getStyle("A1:{$highest}1")
                ->getAlignment()
                ->setHorizontal(
                    Alignment::HORIZONTAL_CENTER
                );

            $sheet->getStyle("A1:{$highest}1")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFE2E8F0');

            foreach (
                range(
                    'A',
                    $highest
                )
                as $column
            ) {
                $sheet
                    ->getColumnDimension($column)
                    ->setWidth(20);
            }
        }

        for ($excelRow = 2; $excelRow <= 2000; $excelRow++) {
            $statusValidation = new DataValidation();
            $statusValidation->setType(DataValidation::TYPE_LIST);
            $statusValidation->setErrorStyle(DataValidation::STYLE_STOP);
            $statusValidation->setAllowBlank(true);
            $statusValidation->setShowDropDown(true);
            $statusValidation->setFormula1('=BI_REF_STATUSES');

            $sites
                ->getCell('F' . $excelRow)
                ->setDataValidation(
                    clone $statusValidation
                );

            $locations
                ->getCell('G' . $excelRow)
                ->setDataValidation(
                    clone $statusValidation
                );

            $departments
                ->getCell('E' . $excelRow)
                ->setDataValidation(
                    clone $statusValidation
                );

            $locationTypeValidation = new DataValidation();
            $locationTypeValidation->setType(DataValidation::TYPE_LIST);
            $locationTypeValidation->setErrorStyle(DataValidation::STYLE_STOP);
            $locationTypeValidation->setAllowBlank(false);
            $locationTypeValidation->setShowDropDown(true);
            $locationTypeValidation->setFormula1('=BI_REF_LOCATION_TYPES');

            $locations
                ->getCell('E' . $excelRow)
                ->setDataValidation(
                    $locationTypeValidation
                );
        }

        /*
         * Metadata
         */
        $meta = $book->createSheet();
        $meta->setTitle('_meta');
        $meta->setSheetState(
            \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VERYHIDDEN
        );

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

        $directory = storage_path(
            'app/bulk-import/templates'
        );

        if (
            !is_dir($directory)
            && !mkdir($directory, 0775, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'Could not create template directory.'
            );
        }

        $path =
            $directory
            . DIRECTORY_SEPARATOR
            . 'reference-structure-company-'
            . $companyId
            . '-'
            . now()->format('Ymd-His')
            . '.xlsx';

        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }
}