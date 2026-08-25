<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetTransaction;
use App\Models\Company;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportExportController extends Controller
{
    public function __invoke(
        Request $request
    ): StreamedResponse {
        $user = $request->user();

        abort_if(
            $user === null,
            401
        );

        abort_unless(
            $user->isSuperAdmin()
            || $user->hasPermission(
                'reports.export'
            ),
            403
        );

        $selectedCompanyId =
            $user->isSuperAdmin()
            && $request->filled(
                'company_id'
            )
                ? (int) $request->input(
                    'company_id'
                )
                : null;

        $assetsQuery =
            $user->isSuperAdmin()
                ? Asset::withoutGlobalScopes()
                : Asset::query();

        if (
            $selectedCompanyId !== null
        ) {
            $assetsQuery->where(
                'company_id',
                $selectedCompanyId
            );
        }

        $this->applyAssetFilters(
            $assetsQuery,
            $request
        );

        $assetStats = [
            'total' =>
                (clone $assetsQuery)
                    ->count(),

            'warehouse' =>
                (clone $assetsQuery)
                    ->where(
                        'status',
                        'warehouse'
                    )
                    ->count(),

            'assigned' =>
                (clone $assetsQuery)
                    ->where(
                        'status',
                        'assigned'
                    )
                    ->count(),

            'destroyed' =>
                (clone $assetsQuery)
                    ->where(
                        'status',
                        'destroyed'
                    )
                    ->count(),

            'purchase_value' =>
                (float) (
                    (clone $assetsQuery)
                        ->sum(
                            'purchase_price'
                        )
                ),
        ];

        $assets =
            $assetsQuery
                ->with([
                    'category',

                    'transactions' =>
                        function ($query) {
                            $query->latest(
                                'id'
                            );
                        },

                    'transactions.toUser',
                ])
                ->orderBy(
                    'id'
                )
                ->get();

        $transactionsQuery =
            $user->isSuperAdmin()
                ? AssetTransaction::withoutGlobalScopes()
                : AssetTransaction::query();

        if (
            $selectedCompanyId !== null
        ) {
            $transactionsQuery->where(
                'company_id',
                $selectedCompanyId
            );
        }

        $this->applyTransactionFilters(
            $transactionsQuery,
            $request
        );

        $this->applyTransactionAssetFilters(
            $transactionsQuery,
            $request
        );

        $transactionStats = [
            'total' =>
                (clone $transactionsQuery)
                    ->count(),

            'delivery' =>
                (clone $transactionsQuery)
                    ->where(
                        'type',
                        'delivery'
                    )
                    ->count(),

            'transfer' =>
                (clone $transactionsQuery)
                    ->where(
                        'type',
                        'transfer'
                    )
                    ->count(),

            'return' =>
                (clone $transactionsQuery)
                    ->where(
                        'type',
                        'return'
                    )
                    ->count(),

            'destroy' =>
                (clone $transactionsQuery)
                    ->where(
                        'type',
                        'destroy'
                    )
                    ->count(),
        ];

        $transactions =
            $transactionsQuery
                ->with([
                    'asset',
                    'fromUser',
                    'toUser',
                    'creator',
                ])
                ->latest(
                    'id'
                )
                ->get();

        $companyNames =
            Company::query()
                ->pluck(
                    'name',
                    'id'
                );

        $selectedCompanyName =
            $selectedCompanyId !== null
                ? (
                    $companyNames[
                        $selectedCompanyId
                    ]
                    ??
                    'شرکت #' . $selectedCompanyId
                )
                : (
                    $user->isSuperAdmin()
                        ? 'همه شرکت‌ها'
                        : (
                            $companyNames[
                                $user->company_id
                            ]
                            ??
                            'شرکت جاری'
                        )
                );

        $categoryName =
            $request->filled(
                'category_id'
            )
                ? AssetCategory::query()
                    ->whereKey(
                        (int) $request->input(
                            'category_id'
                        )
                    )
                    ->value(
                        'name'
                    )
                : null;

        $spreadsheet =
            new Spreadsheet();

        $spreadsheet
            ->getProperties()
            ->setCreator(
                'Asset Management System'
            )
            ->setTitle(
                'گزارش مدیریتی اموال'
            )
            ->setSubject(
                'دارایی‌ها و گردش اموال'
            );

        $summarySheet =
            $spreadsheet
                ->getActiveSheet();

        $summarySheet->setTitle(
            'خلاصه مدیریتی'
        );

        $this->buildSummarySheet(
            sheet: $summarySheet,
            request: $request,
            userName: $user->name
                ?? $user->username
                ?? 'کاربر',
            selectedCompanyName:
                $selectedCompanyName,
            categoryName:
                $categoryName,
            assetStats:
                $assetStats,
            transactionStats:
                $transactionStats
        );

        $assetSheet =
            $spreadsheet
                ->createSheet();

        $assetSheet->setTitle(
            'دارایی‌ها'
        );

        $this->buildAssetSheet(
            sheet:
                $assetSheet,
            assets:
                $assets,
            companyNames:
                $companyNames
        );

        $transactionSheet =
            $spreadsheet
                ->createSheet();

        $transactionSheet->setTitle(
            'گردش اموال'
        );

        $this->buildTransactionSheet(
            sheet:
                $transactionSheet,
            transactions:
                $transactions,
            companyNames:
                $companyNames
        );

        $spreadsheet->setActiveSheetIndex(
            0
        );

        $fileDate =
            str_replace(
                '/',
                '-',
                JalaliDate::date(
                    now()
                )
            );

        $filename =
            "asset-management-report-{$fileDate}.xlsx";

        return response()->streamDownload(
            function () use (
                $spreadsheet
            ): void {
                $writer =
                    new Xlsx(
                        $spreadsheet
                    );

                $writer->save(
                    'php://output'
                );

                $spreadsheet
                    ->disconnectWorksheets();
            },
            $filename,
            [
                'Content-Type' =>
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

                'Cache-Control' =>
                    'max-age=0, no-cache, must-revalidate',
            ]
        );
    }

    private function buildSummarySheet(
        Worksheet $sheet,
        Request $request,
        string $userName,
        string $selectedCompanyName,
        ?string $categoryName,
        array $assetStats,
        array $transactionStats
    ): void {
        $statusLabels = [
            'warehouse' =>
                'موجود در انبار',

            'assigned' =>
                'تحویل‌شده',

            'destroyed' =>
                'اسقاط‌شده',
        ];

        $transactionLabels = [
            'delivery' =>
                'تحویل',

            'transfer' =>
                'انتقال',

            'return' =>
                'بازگشت به انبار',

            'destroy' =>
                'اسقاط',
        ];

        $sheet->setRightToLeft(
            true
        );

        $sheet->mergeCells(
            'A1:D1'
        );

        $sheet->setCellValue(
            'A1',
            'گزارش مدیریتی اموال'
        );

        $sheet->getStyle(
            'A1:D1'
        )
            ->getFont()
            ->setBold(
                true
            )
            ->setSize(
                16
            );

        $sheet->getStyle(
            'A1:D1'
        )
            ->getAlignment()
            ->setHorizontal(
                Alignment::HORIZONTAL_CENTER
            );

        $sheet->fromArray(
            [
                [
                    'تاریخ تولید',
                    JalaliDate::dateTime(
                        now()
                    ),
                ],
                [
                    'تهیه‌کننده',
                    $userName,
                ],
                [
                    'شرکت',
                    $selectedCompanyName,
                ],
                [
                    'وضعیت دارایی',
                    $request->filled(
                        'status'
                    )
                        ? (
                            $statusLabels[
                                (string) $request->input(
                                    'status'
                                )
                            ]
                            ??
                            (string) $request->input(
                                'status'
                            )
                        )
                        : 'همه وضعیت‌ها',
                ],
                [
                    'دسته‌بندی',
                    $categoryName
                    ?? 'همه دسته‌ها',
                ],
                [
                    'جستجو',
                    $request->filled(
                        'search'
                    )
                        ? (
                            (string) $request->input(
                                'search'
                            )
                        )
                        : 'بدون محدودیت',
                ],
                [
                    'نوع گردش',
                    $request->filled(
                        'transaction_type'
                    )
                        ? (
                            $transactionLabels[
                                (string) $request->input(
                                    'transaction_type'
                                )
                            ]
                            ??
                            (string) $request->input(
                                'transaction_type'
                            )
                        )
                        : 'همه گردش‌ها',
                ],
                [
                    'از تاریخ',
                    $request->filled(
                        'date_from'
                    )
                        ? (
                            (string) $request->input(
                                'date_from'
                            )
                        )
                        : '-',
                ],
                [
                    'تا تاریخ',
                    $request->filled(
                        'date_to'
                    )
                        ? (
                            (string) $request->input(
                                'date_to'
                            )
                        )
                        : '-',
                ],
            ],
            null,
            'A3'
        );

        $sheet->setCellValue(
            'A13',
            'خلاصه دارایی‌ها'
        );

        $sheet->getStyle(
            'A13:B13'
        )
            ->getFont()
            ->setBold(
                true
            );

        $sheet->fromArray(
            [
                [
                    'کل دارایی‌ها',
                    $assetStats[
                        'total'
                    ],
                ],
                [
                    'موجود در انبار',
                    $assetStats[
                        'warehouse'
                    ],
                ],
                [
                    'تحویل‌شده',
                    $assetStats[
                        'assigned'
                    ],
                ],
                [
                    'اسقاط‌شده',
                    $assetStats[
                        'destroyed'
                    ],
                ],
                [
                    'ارزش خرید',
                    $assetStats[
                        'purchase_value'
                    ],
                ],
            ],
            null,
            'A14'
        );

        $sheet->setCellValue(
            'C13',
            'خلاصه گردش'
        );

        $sheet->getStyle(
            'C13:D13'
        )
            ->getFont()
            ->setBold(
                true
            );

        $sheet->fromArray(
            [
                [
                    'کل گردش‌ها',
                    $transactionStats[
                        'total'
                    ],
                ],
                [
                    'تحویل',
                    $transactionStats[
                        'delivery'
                    ],
                ],
                [
                    'انتقال',
                    $transactionStats[
                        'transfer'
                    ],
                ],
                [
                    'بازگشت',
                    $transactionStats[
                        'return'
                    ],
                ],
                [
                    'اسقاط',
                    $transactionStats[
                        'destroy'
                    ],
                ],
            ],
            null,
            'C14'
        );

        $sheet
            ->getStyle(
                'B18'
            )
            ->getNumberFormat()
            ->setFormatCode(
                '#,##0'
            );

        $this->styleSummarySheet(
            $sheet
        );
    }

    private function buildAssetSheet(
        Worksheet $sheet,
        $assets,
        $companyNames
    ): void {
        $headers = [
            'ردیف',
            'شرکت',
            'کد دارایی',
            'کد انبار',
            'عنوان',
            'دسته‌بندی',
            'برند',
            'مدل',
            'شماره سریال',
            'شماره پلاک',
            'وضعیت',
            'دارنده فعلی',
            'تاریخ خرید',
            'مبلغ خرید',
        ];

        $statusLabels = [
            'warehouse' =>
                'موجود در انبار',

            'assigned' =>
                'تحویل‌شده',

            'destroyed' =>
                'اسقاط‌شده',
        ];

        $sheet->fromArray(
            $headers,
            null,
            'A1'
        );

        $row = 2;

        foreach (
            $assets
            as $index => $asset
        ) {
            $currentHolder = '-';

            if (
                $asset->status
                ===
                'warehouse'
            ) {
                $currentHolder =
                    'انبار';
            }
            elseif (
                $asset->status
                ===
                'destroyed'
            ) {
                $currentHolder =
                    'اسقاط';
            }
            elseif (
                $asset->status
                ===
                'assigned'
            ) {
                $lastAssignment =
                    $asset->transactions
                        ->first(
                            fn ($transaction): bool =>
                                in_array(
                                    $transaction->type,
                                    [
                                        'delivery',
                                        'transfer',
                                    ],
                                    true
                                )
                                &&
                                $transaction->toUser
                                !==
                                null
                        );

                $currentHolder =
                    $lastAssignment?->toUser?->name
                    ??
                    '-';
            }

            $sheet->fromArray(
                [
                    $index + 1,

                    $companyNames[
                        $asset->company_id
                    ]
                    ??
                    '-',

                    $asset->asset_code
                    ??
                    '-',

                    $asset->inventory_code
                    ??
                    '-',

                    $asset->title,

                    $asset->category?->name
                    ??
                    '-',

                    $asset->brand
                    ??
                    '-',

                    $asset->model
                    ??
                    '-',

                    $asset->serial_number
                    ??
                    '-',

                    $asset->asset_code
                    ??
                    '-',

                    $statusLabels[
                        $asset->status
                    ]
                    ??
                    $asset->status,

                    $currentHolder,

                    JalaliDate::date(
                        $asset->purchase_date
                    ),

                    (float) $asset->purchase_price,
                ],
                null,
                "A{$row}"
            );

            $row++;
        }

        if (
            $sheet->getHighestRow()
            >=
            2
        ) {
            $sheet
                ->getStyle(
                    'N2:N'
                    .
                    $sheet->getHighestRow()
                )
                ->getNumberFormat()
                ->setFormatCode(
                    '#,##0'
                );
        }

        $this->styleDataSheet(
            sheet:
                $sheet,
            headerRange:
                'A1:N1'
        );
    }

    private function buildTransactionSheet(
        Worksheet $sheet,
        $transactions,
        $companyNames
    ): void {
        $headers = [
            'ردیف',
            'شرکت',
            'کد دارایی',
            'عنوان دارایی',
            'نوع عملیات',
            'از',
            'به',
            'شماره پلاک',
            'ثبت‌کننده',
            'تاریخ و زمان',
            'توضیحات',
        ];

        $typeLabels = [
            'delivery' =>
                'تحویل',

            'transfer' =>
                'انتقال',

            'return' =>
                'بازگشت به انبار',

            'destroy' =>
                'اسقاط',
        ];

        $sheet->fromArray(
            $headers,
            null,
            'A1'
        );

        $row = 2;

        foreach (
            $transactions
            as $index => $transaction
        ) {
            $fromName =
                $transaction->fromUser?->name
                ??
                'انبار';

            $toName = '-';

            if (
                $transaction->toUser
            ) {
                $toName =
                    $transaction->toUser->name;
            }
            elseif (
                $transaction->type
                ===
                'return'
            ) {
                $toName =
                    'انبار';
            }
            elseif (
                $transaction->type
                ===
                'destroy'
            ) {
                $toName =
                    'اسقاط';
            }

            $sheet->fromArray(
                [
                    $index + 1,

                    $companyNames[
                        $transaction->company_id
                    ]
                    ??
                    '-',

                    $transaction->asset?->asset_code
                    ??
                    '-',

                    $transaction->asset?->title
                    ??
                    '-',

                    $typeLabels[
                        $transaction->type
                    ]
                    ??
                    $transaction->type,

                    $fromName,

                    $toName,

                    $transaction->plate_number
                    ??
                    '-',

                    $transaction->creator?->name
                    ??
                    '-',

                    JalaliDate::dateTime(
                        $transaction->created_at
                    ),

                    $transaction->description
                    ??
                    '-',
                ],
                null,
                "A{$row}"
            );

            $row++;
        }

        $this->styleDataSheet(
            sheet:
                $sheet,
            headerRange:
                'A1:K1'
        );
    }

    private function styleSummarySheet(
        Worksheet $sheet
    ): void {
        $sheet->freezePane(
            'A3'
        );

        $sheet->getStyle(
            'A3:D18'
        )
            ->getAlignment()
            ->setVertical(
                Alignment::VERTICAL_CENTER
            );

        $sheet->getStyle(
            'A3:D18'
        )
            ->getAlignment()
            ->setHorizontal(
                Alignment::HORIZONTAL_RIGHT
            );

        foreach (
            [
                'A3:A11',
                'A13:B13',
                'C13:D13',
            ]
            as $range
        ) {
            $sheet
                ->getStyle(
                    $range
                )
                ->getFill()
                ->setFillType(
                    Fill::FILL_SOLID
                )
                ->getStartColor()
                ->setARGB(
                    'FFE9ECEF'
                );

            $sheet
                ->getStyle(
                    $range
                )
                ->getFont()
                ->setBold(
                    true
                );
        }

        $sheet
            ->getStyle(
                'A3:D18'
            )
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(
                Border::BORDER_THIN
            );

        foreach (
            ['A', 'B', 'C', 'D']
            as $column
        ) {
            $sheet
                ->getColumnDimension(
                    $column
                )
                ->setAutoSize(
                    true
                );
        }
    }

    private function styleDataSheet(
        Worksheet $sheet,
        string $headerRange
    ): void {
        $sheet->setRightToLeft(
            true
        );

        $sheet->freezePane(
            'A2'
        );

        $highestColumn =
            $sheet->getHighestColumn();

        $highestRow =
            $sheet->getHighestRow();

        $sheet
            ->getStyle(
                $headerRange
            )
            ->getFont()
            ->setBold(
                true
            );

        $sheet
            ->getStyle(
                $headerRange
            )
            ->getFill()
            ->setFillType(
                Fill::FILL_SOLID
            )
            ->getStartColor()
            ->setARGB(
                'FFE9ECEF'
            );

        $sheet
            ->getStyle(
                "A1:{$highestColumn}{$highestRow}"
            )
            ->getAlignment()
            ->setVertical(
                Alignment::VERTICAL_CENTER
            );

        $sheet
            ->getStyle(
                "A1:{$highestColumn}{$highestRow}"
            )
            ->getAlignment()
            ->setHorizontal(
                Alignment::HORIZONTAL_RIGHT
            );

        $sheet
            ->getStyle(
                "A1:{$highestColumn}{$highestRow}"
            )
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(
                Border::BORDER_THIN
            );

        $sheet->setAutoFilter(
            "A1:{$highestColumn}1"
        );

        foreach (
            range(
                'A',
                $highestColumn
            )
            as $column
        ) {
            $sheet
                ->getColumnDimension(
                    $column
                )
                ->setAutoSize(
                    true
                );
        }
    }

    private function applyAssetFilters(
        Builder $query,
        Request $request
    ): void {
        if (
            $request->filled(
                'status'
            )
        ) {
            $query->where(
                'status',
                (string) $request->input(
                    'status'
                )
            );
        }

        if (
            $request->filled(
                'category_id'
            )
        ) {
            $query->where(
                'asset_category_id',
                (int) $request->input(
                    'category_id'
                )
            );
        }

        if (
            $request->filled(
                'search'
            )
        ) {
            $search =
                trim(
                    (string) $request->input(
                        'search'
                    )
                );

            $query->where(
                function (
                    Builder $subQuery
                ) use (
                    $search
                ): void {
                    $subQuery
                        ->where(
                            'title',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'asset_code',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'inventory_code',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'serial_number',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'brand',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'asset_code',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }
    }

    private function applyTransactionFilters(
        Builder $query,
        Request $request
    ): void {
        if (
            $request->filled(
                'transaction_type'
            )
        ) {
            $query->where(
                'type',
                (string) $request->input(
                    'transaction_type'
                )
            );
        }

        if (
            $request->filled(
                'date_from'
            )
        ) {
            $date =
                JalaliDate::toGregorianDate(
                    (string) $request->input(
                        'date_from'
                    )
                );

            if (
                $date !== null
            ) {
                $query->whereDate(
                    'created_at',
                    '>=',
                    $date
                );
            }
        }

        if (
            $request->filled(
                'date_to'
            )
        ) {
            $date =
                JalaliDate::toGregorianDate(
                    (string) $request->input(
                        'date_to'
                    )
                );

            if (
                $date !== null
            ) {
                $query->whereDate(
                    'created_at',
                    '<=',
                    $date
                );
            }
        }
    }

    private function applyTransactionAssetFilters(
        Builder $query,
        Request $request
    ): void {
        if (
            !$request->filled(
                'status'
            )
            &&
            !$request->filled(
                'category_id'
            )
            &&
            !$request->filled(
                'search'
            )
        ) {
            return;
        }

        $query->whereHas(
            'asset',
            function (
                Builder $assetQuery
            ) use (
                $request
            ): void {
                $this->applyAssetFilters(
                    $assetQuery,
                    $request
                );
            }
        );
    }
}