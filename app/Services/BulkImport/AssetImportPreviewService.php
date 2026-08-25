<?php

declare(strict_types=1);

namespace App\Services\BulkImport;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Support\JalaliDate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

final class AssetImportPreviewService
{
    public function preview(string $path, int $companyId): array
    {
        $spreadsheet = IOFactory::load($path);

        try {
            $meta = $spreadsheet->getSheetByName('_meta');

            if ($meta === null) {
                throw new RuntimeException('Asset import metadata sheet is missing.');
            }

            $metadata = [];
            for ($row = 2; $row <= $meta->getHighestRow(); $row++) {
                $key = trim((string) $meta->getCell("A{$row}")->getValue());
                if ($key !== '') {
                    $metadata[$key] = $meta->getCell("B{$row}")->getValue();
                }
            }

            if ((string) ($metadata['template_type'] ?? '') !== AssetImportTemplateService::TEMPLATE_TYPE) {
                throw new RuntimeException('Invalid asset import template type.');
            }

            if (
                !$this->versionsMatch(
                    (string) ($metadata['template_version'] ?? ''),
                    AssetImportTemplateService::TEMPLATE_VERSION
                )
            ) {
                throw new RuntimeException('Invalid asset import template version.');
            }

            if ((int) ($metadata['company_id'] ?? 0) !== $companyId) {
                throw new RuntimeException('Template belongs to another company.');
            }

            $sheet = $spreadsheet->getSheet(0);
            $highestRow = $sheet->getHighestDataRow();

            $categories = AssetCategory::query()
                ->where('is_active', true)
                ->get()
                ->keyBy(fn ($item) => $this->normalize((string) $item->name));

            $types = AssetType::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->get()
                ->keyBy(fn ($item) => $this->normalize((string) $item->name));

            $rows = [];
            $seenInventoryCodes = [];

            for ($row = 2; $row <= $highestRow; $row++) {
                $values = [];
                for ($column = 1; $column <= 13; $column++) {
                    $values[$column] = trim((string) $sheet->getCell([$column, $row])->getFormattedValue());
                }

                if ($this->rowIsEmpty($values)) {
                    continue;
                }

                $errors = [];

                $category = $categories->get($this->normalize($values[1]));
                $type = $types->get($this->normalize($values[2]));

                if ($category === null) {
                    $errors[] = 'دسته‌بندی معتبر نیست.';
                }

                if ($type === null) {
                    $errors[] = 'نوع دارایی معتبر نیست.';
                }

                if (
                    $category !== null
                    && $type !== null
                    && (int) $type->asset_category_id !== (int) $category->id
                ) {
                    $errors[] = 'نوع دارایی متعلق به دسته‌بندی انتخاب‌شده نیست.';
                }

                if ($values[4] === '') {
                    $errors[] = 'عنوان دارایی الزامی است.';
                }

                $inventoryCode = $values[3] !== '' ? $values[3] : null;

                if ($inventoryCode !== null) {
                    $normalizedInventory = mb_strtolower($inventoryCode);

                    if (isset($seenInventoryCodes[$normalizedInventory])) {
                        $errors[] = 'کد اموال داخل فایل تکراری است.';
                    }

                    $seenInventoryCodes[$normalizedInventory] = true;

                    if (
                        Asset::withoutGlobalScopes()
                            ->where('company_id', $companyId)
                            ->where('inventory_code', $inventoryCode)
                            ->exists()
                    ) {
                        $errors[] = 'کد اموال قبلاً در این شرکت ثبت شده است.';
                    }
                }

                $purchaseDate = null;
                if ($values[10] !== '') {
                    try {
                        $purchaseDate = JalaliDate::toGregorianDate($values[10]);
                    } catch (Throwable) {
                        $errors[] = 'تاریخ خرید معتبر نیست.';
                    }
                }

                $purchasePrice = null;
                if ($values[11] !== '') {
                    $numeric = str_replace([',', '٬', ' '], '', $values[11]);

                    if (!is_numeric($numeric) || (float) $numeric < 0) {
                        $errors[] = 'مبلغ خرید معتبر نیست.';
                    } else {
                        $purchasePrice = $numeric;
                    }
                }

                $status = 'warehouse';
                if ($values[13] !== '' && $values[13] !== 'انبار' && $values[13] !== 'warehouse') {
                    $errors[] = 'وضعیت معتبر نیست.';
                }

                $rows[] = [
                    'excel_row' => $row,
                    'valid' => $errors === [],
                    'errors' => $errors,
                    'inventory_code' => $inventoryCode,
                    'title' => $values[4],
                    'resolved' => [
                        'company_id' => $companyId,
                        'asset_category_id' => $category?->id,
                        'asset_type_id' => $type?->id,
                        'status' => $status,
                    ],
                    'data' => [
                        'company_id' => $companyId,
                        'asset_category_id' => $category?->id,
                        'asset_type_id' => $type?->id,
                        'inventory_code' => $inventoryCode,
                        'title' => $values[4],
                        'brand' => $values[5] !== '' ? $values[5] : null,
                        'model' => $values[6] !== '' ? $values[6] : null,
                        'serial_number' => $values[7] !== '' ? $values[7] : null,
                        'manufacturer' => $values[8] !== '' ? $values[8] : null,
                        'country' => $values[9] !== '' ? $values[9] : null,
                        'purchase_date' => $purchaseDate,
                        'purchase_price' => $purchasePrice,
                        'description' => $values[12] !== '' ? $values[12] : null,
                        'is_active' => true,
                        'status' => $status,
                    ],
                ];
            }

            $validRows = count(array_filter($rows, fn (array $row): bool => $row['valid']));
            $errorRows = count($rows) - $validRows;

            return [
                'total_rows' => count($rows),
                'valid_rows' => $validRows,
                'error_rows' => $errorRows,
                'can_commit' => count($rows) > 0 && $errorRows === 0,
                'rows' => $rows,
            ];
        } finally {
            $spreadsheet->disconnectWorksheets();
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

    private function normalize(string $value): string
    {
        $value = str_replace(['ي', 'ك'], ['ی', 'ک'], trim($value));
        return mb_strtolower(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function rowIsEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}