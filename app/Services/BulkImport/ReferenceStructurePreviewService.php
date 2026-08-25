<?php

declare(strict_types=1);

namespace App\Services\BulkImport;

use App\Models\Department;
use App\Models\Location;
use App\Models\Site;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

final class ReferenceStructurePreviewService
{
    public function preview(
        string $path,
        int $companyId
    ): array {
        $book = IOFactory::load($path);

        try {
            $this->validateMeta($book, $companyId);

            $sites = $this->previewSites(
                $book,
                $companyId
            );

            $locations = $this->previewLocations(
                $book,
                $companyId,
                $sites
            );

            $departments = $this->previewDepartments(
                $book,
                $companyId
            );

            $totalRows =
                count($sites['rows'])
                + count($locations['rows'])
                + count($departments['rows']);

            $errorRows =
                $sites['error_rows']
                + $locations['error_rows']
                + $departments['error_rows'];

            return [
                'total_rows' => $totalRows,
                'valid_rows' => $totalRows - $errorRows,
                'error_rows' => $errorRows,
                'can_commit' =>
                    $totalRows > 0
                    && $errorRows === 0,

                'sites' => $sites,
                'locations' => $locations,
                'departments' => $departments,
            ];
        }
        finally {
            $book->disconnectWorksheets();
        }
    }


    private function validateMeta(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $book,
        int $companyId
    ): void {
        $meta = $book->getSheetByName('_meta');

        if ($meta === null) {
            throw new RuntimeException(
                'Metadata sheet is missing.'
            );
        }

        $values = [];

        for (
            $row = 2;
            $row <= $meta->getHighestDataRow();
            $row++
        ) {
            $key = trim(
                (string) $meta->getCell('A' . $row)->getValue()
            );

            if ($key !== '') {
                $values[$key] =
                    $meta->getCell('B' . $row)->getValue();
            }
        }

        if (
            (string) ($values['template_type'] ?? '')
            !== ReferenceStructureTemplateService::TEMPLATE_TYPE
        ) {
            throw new RuntimeException(
                'Invalid reference structure template.'
            );
        }

        if (
            !$this->versionsMatch(
                (string) ($values['template_version'] ?? ''),
                ReferenceStructureTemplateService::TEMPLATE_VERSION
            )
        ) {
            throw new RuntimeException(
                'Invalid reference structure template version.'
            );
        }

        if (
            (int) ($values['company_id'] ?? 0)
            !== $companyId
        ) {
            throw new RuntimeException(
                'Template belongs to another company.'
            );
        }
    }


    private function previewSites(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $book,
        int $companyId
    ): array {
        $sheet =
            $book->getSheetByName('سایت‌ها')
            ?? $book->getSheet(0);

        $existingCodes =
            Site::query()
                ->where('company_id', $companyId)
                ->pluck('id', 'code')
                ->mapWithKeys(
                    fn ($id, $code) => [
                        $this->normalizeCode($code) =>
                            (int) $id,
                    ]
                )
                ->all();

        $rows = [];
        $seenCodes = [];

        for (
            $row = 2;
            $row <= $sheet->getHighestDataRow();
            $row++
        ) {
            $values = $this->row(
                $sheet,
                $row,
                7
            );

            if ($this->rowEmpty($values)) {
                continue;
            }

            $errors = [];

            $name = trim($values[1]);
            $code = trim($values[2]);
            $type = trim($values[3]) ?: 'site';
            $status =
                $this->statusValue(
                    trim($values[6])
                );

            if ($name === '') {
                $errors[] = 'نام سایت الزامی است.';
            }

            if ($code === '') {
                $errors[] = 'کد سایت الزامی است.';
            }

            $normalizedCode =
                $this->normalizeCode($code);

            if ($code !== '') {
                if (isset($seenCodes[$normalizedCode])) {
                    $errors[] =
                        'کد سایت داخل فایل تکراری است.';
                }

                $seenCodes[$normalizedCode] = true;

                if (isset($existingCodes[$normalizedCode])) {
                    $errors[] =
                        'کد سایت قبلاً در این شرکت ثبت شده است.';
                }
            }

            if ($status === null) {
                $errors[] =
                    'وضعیت سایت معتبر نیست.';
            }

            $sortOrder =
                $this->nonNegativeInteger(
                    $values[7],
                    $errors,
                    'ترتیب سایت'
                );

            $rows[] = [
                'excel_row' => $row,
                'valid' => $errors === [],
                'errors' => $errors,
                'data' => [
                    'company_id' => $companyId,
                    'name' => $name,
                    'code' => $code,
                    'type' => $type,
                    'address' =>
                        trim($values[4]) !== ''
                            ? trim($values[4])
                            : null,
                    'description' =>
                        trim($values[5]) !== ''
                            ? trim($values[5])
                            : null,
                    'is_active' => $status ?? true,
                    'sort_order' => $sortOrder,
                ],
            ];
        }

        return $this->summary($rows);
    }


    private function previewLocations(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $book,
        int $companyId,
        array $sitePreview
    ): array {
        $sheet =
            $book->getSheetByName('مکان‌ها')
            ?? $book->getSheet(1);

        $existingSites =
            Site::query()
                ->where('company_id', $companyId)
                ->get(['id', 'code'])
                ->mapWithKeys(
                    fn ($site) => [
                        $this->normalizeCode($site->code) => [
                            'id' => (int) $site->id,
                            'code' =>
                                $this->normalizeCode(
                                    $site->code
                                ),
                            'source' => 'database',
                        ],
                    ]
                )
                ->all();

        $newSites = [];

        foreach ($sitePreview['rows'] as $siteRow) {
            if (($siteRow['valid'] ?? false) !== true) {
                continue;
            }

            $key =
                $this->normalizeCode(
                    $siteRow['data']['code']
                );

            $newSites[$key] = [
                'id' => null,
                'code' => $key,
                'source' => 'file',
            ];
        }

        /*
         * Actual DB uniqueness is company + site + code.
         * Use composite keys to avoid rejecting legal repeated codes
         * across different sites.
         */
        $existingLocations = [];

        foreach (
            Location::query()
                ->where('company_id', $companyId)
                ->get(['id', 'site_id', 'code'])
            as $location
        ) {
            $site =
                Site::query()
                    ->where('company_id', $companyId)
                    ->find($location->site_id);

            if ($site === null) {
                continue;
            }

            $siteCode =
                $this->normalizeCode(
                    $site->code
                );

            $locationCode =
                $this->normalizeCode(
                    $location->code
                );

            $existingLocations[
                $this->locationKey(
                    $siteCode,
                    $locationCode
                )
            ] = [
                'id' => (int) $location->id,
                'site_id' => (int) $location->site_id,
                'site_code' => $siteCode,
                'source' => 'database',
            ];
        }

        $rows = [];
        $seenKeys = [];
        $newLocationByKey = [];

        for (
            $row = 2;
            $row <= $sheet->getHighestDataRow();
            $row++
        ) {
            $values =
                $this->row(
                    $sheet,
                    $row,
                    8
                );

            if ($this->rowEmpty($values)) {
                continue;
            }

            $errors = [];

            $siteCode = trim($values[1]);
            $parentCode = trim($values[2]);
            $name = trim($values[3]);
            $code = trim($values[4]);
            $type =
                $this->locationTypeValue(
                    trim($values[5])
                );
            $status =
                $this->statusValue(
                    trim($values[7])
                );

            if ($siteCode === '') {
                $errors[] =
                    'کد سایت برای مکان الزامی است.';
            }

            if ($name === '') {
                $errors[] =
                    'نام مکان الزامی است.';
            }

            if ($code === '') {
                $errors[] =
                    'کد مکان الزامی است.';
            }

            if ($type === null) {
                $errors[] =
                    'نوع مکان معتبر نیست.';
            }

            if ($status === null) {
                $errors[] =
                    'وضعیت مکان معتبر نیست.';
            }

            $normalizedSiteCode =
                $this->normalizeCode($siteCode);

            $siteRef =
                $existingSites[$normalizedSiteCode]
                ?? $newSites[$normalizedSiteCode]
                ?? null;

            if (
                $siteCode !== ''
                && $siteRef === null
            ) {
                $errors[] =
                    'کد سایت مکان در دیتابیس یا شیت سایت‌ها پیدا نشد.';
            }

            $normalizedCode =
                $this->normalizeCode($code);

            $locationKey =
                $this->locationKey(
                    $normalizedSiteCode,
                    $normalizedCode
                );

            if (
                $siteCode !== ''
                && $code !== ''
            ) {
                if (isset($seenKeys[$locationKey])) {
                    $errors[] =
                        'کد مکان در همین سایت داخل فایل تکراری است.';
                }

                $seenKeys[$locationKey] = true;

                if (
                    isset(
                        $existingLocations[
                            $locationKey
                        ]
                    )
                ) {
                    $errors[] =
                        'کد مکان قبلاً در همین سایت ثبت شده است.';
                }
            }

            $parentRef = null;

            if ($parentCode !== '') {
                $normalizedParentCode =
                    $this->normalizeCode(
                        $parentCode
                    );

                $parentKey =
                    $this->locationKey(
                        $normalizedSiteCode,
                        $normalizedParentCode
                    );

                $parentRef =
                    $existingLocations[$parentKey]
                    ?? $newLocationByKey[$parentKey]
                    ?? null;

                if ($parentRef === null) {
                    $errors[] =
                        'کد والد مکان پیدا نشد؛ والد باید در همین سایت باشد و والد جدید باید در ردیف‌های قبلی فایل باشد.';
                }
            }

            $sortOrder =
                $this->nonNegativeInteger(
                    $values[8],
                    $errors,
                    'ترتیب مکان'
                );

            $current = [
                'excel_row' => $row,
                'valid' => $errors === [],
                'errors' => $errors,
                'resolved' => [
                    'site_code' => $siteCode,
                    'site_source' =>
                        $siteRef['source'] ?? null,
                    'parent_code' =>
                        $parentCode !== ''
                            ? $parentCode
                            : null,
                    'parent_source' =>
                        $parentRef['source'] ?? null,
                ],
                'data' => [
                    'company_id' => $companyId,
                    'site_code' => $siteCode,
                    'parent_code' =>
                        $parentCode !== ''
                            ? $parentCode
                            : null,
                    'name' => $name,
                    'code' => $code,
                    'type' => $type ?? 'location',
                    'description' =>
                        trim($values[6]) !== ''
                            ? trim($values[6])
                            : null,
                    'is_active' => $status ?? true,
                    'sort_order' => $sortOrder,
                ],
            ];

            $rows[] = $current;

            if (
                $code !== ''
                && $current['valid']
            ) {
                $newLocationByKey[
                    $locationKey
                ] = [
                    'id' => null,
                    'site_id' =>
                        $siteRef['id'] ?? null,
                    'site_code' =>
                        $normalizedSiteCode,
                    'source' => 'file',
                ];
            }
        }

        return $this->summary($rows);
    }


    private function previewDepartments(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $book,
        int $companyId
    ): array {
        $sheet =
            $book->getSheetByName('واحدها')
            ?? $book->getSheet(2);

        $existing =
            Department::query()
                ->where('company_id', $companyId)
                ->get(['id', 'code'])
                ->mapWithKeys(
                    fn ($department) => [
                        $this->normalizeCode(
                            $department->code
                        ) => [
                            'id' => (int) $department->id,
                            'source' => 'database',
                        ],
                    ]
                )
                ->all();

        $rows = [];
        $seenCodes = [];
        $newByCode = [];

        for (
            $row = 2;
            $row <= $sheet->getHighestDataRow();
            $row++
        ) {
            $values =
                $this->row(
                    $sheet,
                    $row,
                    6
                );

            if ($this->rowEmpty($values)) {
                continue;
            }

            $errors = [];

            $parentCode = trim($values[1]);
            $name = trim($values[2]);
            $code = trim($values[3]);
            $status =
                $this->statusValue(
                    trim($values[5])
                );

            if ($name === '') {
                $errors[] =
                    'نام واحد الزامی است.';
            }

            if ($code === '') {
                $errors[] =
                    'کد واحد الزامی است.';
            }

            if ($status === null) {
                $errors[] =
                    'وضعیت واحد معتبر نیست.';
            }

            $normalizedCode =
                $this->normalizeCode($code);

            if ($code !== '') {
                if (isset($seenCodes[$normalizedCode])) {
                    $errors[] =
                        'کد واحد داخل فایل تکراری است.';
                }

                $seenCodes[$normalizedCode] = true;

                if (isset($existing[$normalizedCode])) {
                    $errors[] =
                        'کد واحد قبلاً در این شرکت ثبت شده است.';
                }
            }

            $parentRef = null;

            if ($parentCode !== '') {
                $normalizedParent =
                    $this->normalizeCode(
                        $parentCode
                    );

                $parentRef =
                    $existing[$normalizedParent]
                    ?? $newByCode[$normalizedParent]
                    ?? null;

                if ($parentRef === null) {
                    $errors[] =
                        'کد والد واحد پیدا نشد؛ والد جدید باید در ردیف‌های قبلی همین فایل باشد.';
                }
            }

            $sortOrder =
                $this->nonNegativeInteger(
                    $values[6],
                    $errors,
                    'ترتیب واحد'
                );

            $current = [
                'excel_row' => $row,
                'valid' => $errors === [],
                'errors' => $errors,
                'resolved' => [
                    'parent_code' =>
                        $parentCode !== ''
                            ? $parentCode
                            : null,
                    'parent_source' =>
                        $parentRef['source'] ?? null,
                ],
                'data' => [
                    'company_id' => $companyId,
                    'parent_code' =>
                        $parentCode !== ''
                            ? $parentCode
                            : null,
                    'name' => $name,
                    'code' => $code,
                    'description' =>
                        trim($values[4]) !== ''
                            ? trim($values[4])
                            : null,
                    'is_active' => $status ?? true,
                    'sort_order' => $sortOrder,
                ],
            ];

            $rows[] = $current;

            if (
                $code !== ''
                && $current['valid']
            ) {
                $newByCode[
                    $normalizedCode
                ] = [
                    'id' => null,
                    'source' => 'file',
                ];
            }
        }

        return $this->summary($rows);
    }


    private function row(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $row,
        int $columns
    ): array {
        $values = [];

        for (
            $column = 1;
            $column <= $columns;
            $column++
        ) {
            $values[$column] =
                trim(
                    (string) $sheet
                        ->getCell([
                            $column,
                            $row,
                        ])
                        ->getFormattedValue()
                );
        }

        return $values;
    }


    private function rowEmpty(
        array $values
    ): bool {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }


    private function summary(
        array $rows
    ): array {
        $valid = count(
            array_filter(
                $rows,
                fn (array $row): bool =>
                    $row['valid']
            )
        );

        return [
            'total_rows' => count($rows),
            'valid_rows' => $valid,
            'error_rows' =>
                count($rows) - $valid,
            'rows' => $rows,
        ];
    }


    private function statusValue(
        string $value
    ): ?bool {
        if ($value === '') {
            return true;
        }

        $normalized =
            $this->normalizeText($value);

        if (
            in_array(
                $normalized,
                ['فعال', 'active', '1', 'true'],
                true
            )
        ) {
            return true;
        }

        if (
            in_array(
                $normalized,
                ['غیرفعال', 'inactive', '0', 'false'],
                true
            )
        ) {
            return false;
        }

        return null;
    }


    private function locationTypeValue(
        string $value
    ): ?string {
        $normalized =
            $this->normalizeText($value);

        return match ($normalized) {
            'ساختمان',
            'building' => 'building',

            'طبقه',
            'floor' => 'floor',

            'اتاق',
            'room' => 'room',

            'انبار',
            'warehouse' => 'warehouse',

            'سایر',
            'location' => 'location',

            default => null,
        };
    }


    private function nonNegativeInteger(
        string $value,
        array &$errors,
        string $label
    ): int {
        if ($value === '') {
            return 0;
        }

        if (!ctype_digit($value)) {
            $errors[] =
                $label
                . ' باید عدد صحیح صفر یا بزرگ‌تر باشد.';

            return 0;
        }

        return (int) $value;
    }


    private function normalizeCode(
        string $value
    ): string {
        return mb_strtolower(
            trim($value)
        );
    }


    private function normalizeText(
        string $value
    ): string {
        $value = str_replace(
            ['ي', 'ك'],
            ['ی', 'ک'],
            trim($value)
        );

        return mb_strtolower(
            preg_replace(
                '/\s+/u',
                ' ',
                $value
            ) ?? $value
        );
    }


    private function locationKey(
        string $siteCode,
        string $locationCode
    ): string {
        return
            $siteCode
            . '|'
            . $locationCode;
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
            return
                (float) $actual
                === (float) $expected;
        }

        return false;
    }
}