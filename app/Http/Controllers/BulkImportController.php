<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Services\BulkImport\BulkImportCompanyResolver;
use App\Services\BulkImport\EmployeeImportCommitService;
use App\Services\BulkImport\EmployeeImportPreviewService;
use App\Services\BulkImport\EmployeeImportTemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class BulkImportController extends Controller
{
    private const PREVIEW_TTL_MINUTES = 30;

    public function index(
        Request $request
    ): View {
        $user = $request->user();

        $this->ensureCanOpen(
            $user
        );

        $this->cleanupExpiredPreviewFiles();

        return view(
            'bulk_import.index',
            [
                'companies' =>
                    $this->companiesFor(
                        $user
                    ),

                'preview' =>
                    null,

                'previewToken' =>
                    null,
            ]
        );
    }

    public function employeeTemplate(
        Request $request,
        BulkImportCompanyResolver $resolver,
        EmployeeImportTemplateService $service
    ): BinaryFileResponse {
        $user = $request->user();

        $this->ensureCanImportEmployees(
            $user
        );

        $company =
            $resolver->resolve(
                $user,
                $request->integer(
                    'company_id'
                ) ?: null
            );

        $spreadsheet =
            $service->build(
                $company
            );

        $directory =
            storage_path(
                'app/tmp/bulk-import'
            );

        if (!is_dir($directory)) {
            mkdir(
                $directory,
                0775,
                true
            );
        }

        $path =
            $directory
            . DIRECTORY_SEPARATOR
            . 'employee-import-template-'
            . $company->id
            . '-'
            . now()->format('Ymd-His')
            . '.xlsx';

        (new Xlsx($spreadsheet))
            ->save($path);

        $spreadsheet
            ->disconnectWorksheets();

        return response()
            ->download(
                $path,
                'employee-import-template-'
                    . $company->id
                    . '.xlsx',
                [
                    'Content-Type' =>
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            )
            ->deleteFileAfterSend(
                true
            );
    }

    public function employeePreview(
        Request $request,
        BulkImportCompanyResolver $resolver,
        EmployeeImportPreviewService $service
    ): View {
        $user = $request->user();

        $this->ensureCanImportEmployees(
            $user
        );

        $validated =
            $request->validate([
                'company_id' => [
                    'nullable',
                    'integer',
                    'exists:companies,id',
                ],

                'file' => [
                    'required',
                    'file',
                    'mimes:xlsx',
                    'max:10240',
                ],
            ]);

        $company =
            $resolver->resolve(
                $user,
                isset(
                    $validated[
                        'company_id'
                    ]
                )
                    ? (int) $validated[
                        'company_id'
                    ]
                    : null
            );

        try {
            $preview =
                $service->preview(
                    $request->file(
                        'file'
                    ),
                    $company
                );
        }
        catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'file' =>
                    'فایل قابل بررسی نیست: '
                    . $e->getMessage(),
            ]);
        }

        $previewToken =
            null;

        if (
            $preview[
                'can_commit'
            ] === true
        ) {
            $previewToken =
                $this->storePreviewFile(
                    request:
                        $request,

                    company:
                        $company,

                    file:
                        $request->file(
                            'file'
                        )
                );
        }

        return view(
            'bulk_import.index',
            [
                'companies' =>
                    $this->companiesFor(
                        $user
                    ),

                'preview' =>
                    $preview,

                'previewToken' =>
                    $previewToken,
            ]
        );
    }

    public function employeeCommit(
        Request $request,
        BulkImportCompanyResolver $resolver,
        EmployeeImportPreviewService $previewService,
        EmployeeImportCommitService $commitService
    ): Response {
        $user = $request->user();

        $this->ensureCanImportEmployees(
            $user
        );

        $validated =
            $request->validate([
                'preview_token' => [
                    'required',
                    'string',
                    'size:48',
                ],
            ]);

        $token =
            $validated[
                'preview_token'
            ];

        $sessionKey =
            $this->sessionKey(
                $token
            );

        $stored =
            $request->session()
                ->get(
                    $sessionKey
                );

        if (!is_array($stored)) {
            throw ValidationException::withMessages([
                'file' =>
                    'پیش‌نمایش معتبر نیست یا منقضی شده است. فایل را دوباره بررسی کنید.',
            ]);
        }

        if (
            (int) (
                $stored[
                    'user_id'
                ] ?? 0
            )
            !== (int) $user->id
        ) {
            abort(403);
        }

        if (
            (int) (
                $stored[
                    'expires_at'
                ] ?? 0
            )
            < now()->timestamp
        ) {
            $this->forgetPreview(
                $request,
                $token,
                $stored
            );

            throw ValidationException::withMessages([
                'file' =>
                    'مهلت پیش‌نمایش پایان یافته است. فایل را دوباره بررسی کنید.',
            ]);
        }

        $path =
            (string) (
                $stored[
                    'path'
                ] ?? ''
            );

        if (
            $path === ''
            || !is_file(
                $path
            )
        ) {
            $this->forgetPreview(
                $request,
                $token,
                $stored
            );

            throw ValidationException::withMessages([
                'file' =>
                    'فایل پیش‌نمایش در دسترس نیست. دوباره فایل را بارگذاری کنید.',
            ]);
        }

        $expectedHash =
            (string) (
                $stored[
                    'sha256'
                ] ?? ''
            );

        $actualHash =
            hash_file(
                'sha256',
                $path
            );

        if (
            $expectedHash === ''
            || !hash_equals(
                $expectedHash,
                $actualHash
            )
        ) {
            $this->forgetPreview(
                $request,
                $token,
                $stored
            );

            throw ValidationException::withMessages([
                'file' =>
                    'فایل پیش‌نمایش تغییر کرده است. دوباره فایل را بارگذاری کنید.',
            ]);
        }

        $company =
            $resolver->resolve(
                $user,
                (int) (
                    $stored[
                        'company_id'
                    ] ?? 0
                )
            );

        if (
            (int) $company->id
            !== (int) (
                $stored[
                    'company_id'
                ] ?? 0
            )
        ) {
            abort(403);
        }

        $upload =
            new UploadedFile(
                $path,
                basename($path),
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            );

        try {
            /*
             * Critical safety step:
             * Re-run full preview against CURRENT database.
             * Any site/department/location/manager change after preview
             * is therefore caught before commit.
             */
            $freshPreview =
                $previewService->preview(
                    $upload,
                    $company
                );
        }
        catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'file' =>
                    'اعتبارسنجی نهایی فایل ناموفق بود: '
                    . $e->getMessage(),
            ]);
        }

        if (
            $freshPreview[
                'can_commit'
            ] !== true
        ) {
            $messages =
                collect(
                    $freshPreview[
                        'rows'
                    ]
                )
                    ->filter(
                        fn (array $row): bool =>
                            ($row[
                                'valid'
                            ] ?? false)
                            !== true
                    )
                    ->take(5)
                    ->flatMap(
                        function (
                            array $row
                        ): array {
                            $excelRow =
                                $row[
                                    'excel_row'
                                ]
                                ?? '?';

                            return collect(
                                $row[
                                    'errors'
                                ]
                                ?? []
                            )
                                ->map(
                                    fn (
                                        string $error
                                    ): string =>
                                        'ردیف '
                                        . $excelRow
                                        . ': '
                                        . $error
                                )
                                ->all();
                        }
                    )
                    ->values()
                    ->all();

            throw ValidationException::withMessages([
                'file' =>
                    array_merge(
                        [
                            'فایل از زمان پیش‌نمایش تا ثبت نهایی دیگر معتبر نیست.',
                        ],
                        $messages
                    ),
            ]);
        }

        try {
            $created =
                $commitService->commit(
                    company:
                        $company,

                    rows:
                        $freshPreview[
                            'rows'
                        ],

                    request:
                        $request
                );
        }
        catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'file' =>
                    'ثبت نهایی انجام نشد و هیچ ردیفی ذخیره نشد: '
                    . $e->getMessage(),
            ]);
        }

        $this->forgetPreview(
            $request,
            $token,
            $stored
        );

        return redirect()
            ->route(
                'employees.index'
            )
            ->with(
                'success',
                number_format(
                    $created->count()
                )
                . ' پرسنل با موفقیت به‌صورت گروهی ثبت شد.'
            );
    }

    private function storePreviewFile(
        Request $request,
        Company $company,
        UploadedFile $file
    ): string {
        $directory =
            storage_path(
                'app/tmp/bulk-import/previews'
            );

        if (!is_dir($directory)) {
            mkdir(
                $directory,
                0775,
                true
            );
        }

        $token =
            bin2hex(
                random_bytes(
                    24
                )
            );

        $path =
            $directory
            . DIRECTORY_SEPARATOR
            . $token
            . '.xlsx';

        if (
            !copy(
                $file->getRealPath(),
                $path
            )
        ) {
            throw ValidationException::withMessages([
                'file' =>
                    'ذخیره امن فایل پیش‌نمایش انجام نشد.',
            ]);
        }

        $request->session()
            ->put(
                $this->sessionKey(
                    $token
                ),
                [
                    'user_id' =>
                        $request->user()->id,

                    'company_id' =>
                        $company->id,

                    'path' =>
                        $path,

                    'sha256' =>
                        hash_file(
                            'sha256',
                            $path
                        ),

                    'expires_at' =>
                        now()
                            ->addMinutes(
                                self::PREVIEW_TTL_MINUTES
                            )
                            ->timestamp,
                ]
            );

        return $token;
    }

    private function forgetPreview(
        Request $request,
        string $token,
        array $stored
    ): void {
        $path =
            (string) (
                $stored[
                    'path'
                ]
                ?? ''
            );

        if (
            $path !== ''
            && is_file(
                $path
            )
        ) {
            @unlink(
                $path
            );
        }

        $request->session()
            ->forget(
                $this->sessionKey(
                    $token
                )
            );
    }

    private function cleanupExpiredPreviewFiles(): void
    {
        $directory =
            storage_path(
                'app/tmp/bulk-import/previews'
            );

        if (!is_dir($directory)) {
            return;
        }

        $cutoff =
            now()
                ->subDay()
                ->timestamp;

        foreach (
            glob(
                $directory
                . DIRECTORY_SEPARATOR
                . '*.xlsx'
            ) ?: []
            as $path
        ) {
            if (
                is_file($path)
                && filemtime($path) < $cutoff
            ) {
                @unlink($path);
            }
        }
    }

    private function sessionKey(
        string $token
    ): string {
        return 'bulk_import.employee_preview.'
            . $token;
    }

    private function companiesFor(
        User $user
    ) {
        return $user->isSuperAdmin()
            ? Company::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                ])
            : collect();
    }

    private function ensureCanOpen(
        User $user
    ): void {
        abort_if(
            !$user->isSuperAdmin()
            && !$user->hasPermission(
                'employees.create'
            )
            && !$user->hasPermission(
                'assets.create'
            ),
            403
        );
    }

    private function ensureCanImportEmployees(
        User $user
    ): void {
        abort_if(
            !$user->isSuperAdmin()
            && !$user->hasPermission(
                'employees.create'
            ),
            403
        );
    }

    public function assetIndex(
        \Illuminate\Http\Request $request
    ): \Illuminate\View\View {
        $user = $request->user();

        $this->ensureCanImportAssets(
            $user
        );

        $this->cleanupExpiredAssetPreviewFiles();

        return view(
            'bulk_import.assets',
            [
                'companies' =>
                    $this->companiesFor(
                        $user
                    ),

                'assetPreview' =>
                    null,

                'assetPreviewToken' =>
                    null,
            ]
        );
    }


    public function assetTemplate(
        \Illuminate\Http\Request $request,
        \App\Services\BulkImport\BulkImportCompanyResolver $resolver,
        \App\Services\BulkImport\AssetImportTemplateService $service
    ): \Symfony\Component\HttpFoundation\BinaryFileResponse {
        $user = $request->user();

        $this->ensureCanImportAssets(
            $user
        );

        $company =
            $resolver->resolve(
                $user,
                $request->integer(
                    'company_id'
                ) ?: null
            );

        $path =
            $service->generate(
                (int) $company->id
            );

        return response()
            ->download(
                $path,
                'asset-import-template-'
                    . $company->id
                    . '.xlsx',
                [
                    'Content-Type' =>
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            )
            ->deleteFileAfterSend(
                true
            );
    }


    public function assetPreview(
        \Illuminate\Http\Request $request,
        \App\Services\BulkImport\BulkImportCompanyResolver $resolver,
        \App\Services\BulkImport\AssetImportPreviewService $service
    ): \Illuminate\View\View {
        $user = $request->user();

        $this->ensureCanImportAssets(
            $user
        );

        $validated =
            $request->validate([
                'company_id' => [
                    'nullable',
                    'integer',
                    'exists:companies,id',
                ],

                'file' => [
                    'required',
                    'file',
                    'mimes:xlsx',
                    'max:10240',
                ],
            ]);

        $company =
            $resolver->resolve(
                $user,
                isset($validated['company_id'])
                    ? (int) $validated['company_id']
                    : null
            );

        try {
            $assetPreview =
                $service->preview(
                    $request->file('file')
                        ->getRealPath(),
                    (int) $company->id
                );
        }
        catch (\Throwable $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' =>
                    'فایل دارایی قابل بررسی نیست: '
                    . $e->getMessage(),
            ]);
        }

        $assetPreviewToken =
            null;

        if (
            $assetPreview['can_commit']
            === true
        ) {
            $assetPreviewToken =
                $this->storeAssetPreviewFile(
                    request:
                        $request,

                    company:
                        $company,

                    file:
                        $request->file('file')
                );
        }

        return view(
            'bulk_import.assets',
            [
                'companies' =>
                    $this->companiesFor(
                        $user
                    ),

                'assetPreview' =>
                    $assetPreview,

                'assetPreviewToken' =>
                    $assetPreviewToken,
            ]
        );
    }


    public function assetCommit(
        \Illuminate\Http\Request $request,
        \App\Services\BulkImport\BulkImportCompanyResolver $resolver,
        \App\Services\BulkImport\AssetImportPreviewService $previewService,
        \App\Services\BulkImport\AssetImportCommitService $commitService
    ): \Symfony\Component\HttpFoundation\Response {
        $user = $request->user();

        $this->ensureCanImportAssets(
            $user
        );

        $validated =
            $request->validate([
                'preview_token' => [
                    'required',
                    'string',
                    'size:48',
                ],
            ]);

        $token =
            $validated['preview_token'];

        $sessionKey =
            $this->assetSessionKey(
                $token
            );

        $stored =
            $request->session()
                ->get(
                    $sessionKey
                );

        if (!is_array($stored)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' =>
                    'پیش‌نمایش دارایی معتبر نیست یا منقضی شده است. فایل را دوباره بررسی کنید.',
            ]);
        }

        if (
            (int) ($stored['user_id'] ?? 0)
            !== (int) $user->id
        ) {
            abort(403);
        }

        if (
            (int) ($stored['expires_at'] ?? 0)
            < now()->timestamp
        ) {
            $this->forgetAssetPreview(
                $request,
                $token,
                $stored
            );

            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' =>
                    'مهلت پیش‌نمایش دارایی پایان یافته است. فایل را دوباره بررسی کنید.',
            ]);
        }

        $path =
            (string) ($stored['path'] ?? '');

        if (
            $path === ''
            || !is_file($path)
        ) {
            $this->forgetAssetPreview(
                $request,
                $token,
                $stored
            );

            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' =>
                    'فایل پیش‌نمایش دارایی در دسترس نیست.',
            ]);
        }

        $expectedHash =
            (string) ($stored['sha256'] ?? '');

        $actualHash =
            hash_file(
                'sha256',
                $path
            );

        if (
            $expectedHash === ''
            || !hash_equals(
                $expectedHash,
                $actualHash
            )
        ) {
            $this->forgetAssetPreview(
                $request,
                $token,
                $stored
            );

            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' =>
                    'فایل پیش‌نمایش دارایی تغییر کرده است. دوباره بارگذاری کنید.',
            ]);
        }

        $company =
            $resolver->resolve(
                $user,
                (int) ($stored['company_id'] ?? 0)
            );

        if (
            (int) $company->id
            !== (int) ($stored['company_id'] ?? 0)
        ) {
            abort(403);
        }

        /*
         * Revalidate against CURRENT database.
         * Stale category/type or duplicate inventory code is caught here.
         */
        try {
            $freshPreview =
                $previewService->preview(
                    $path,
                    (int) $company->id
                );
        }
        catch (\Throwable $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' =>
                    'اعتبارسنجی نهایی دارایی‌ها ناموفق بود: '
                    . $e->getMessage(),
            ]);
        }

        if (
            $freshPreview['can_commit']
            !== true
        ) {
            $messages =
                collect($freshPreview['rows'])
                    ->filter(
                        fn (array $row): bool =>
                            ($row['valid'] ?? false)
                            !== true
                    )
                    ->take(5)
                    ->flatMap(
                        function (array $row): array {
                            $excelRow =
                                $row['excel_row']
                                ?? '?';

                            return collect(
                                $row['errors']
                                ?? []
                            )
                                ->map(
                                    fn (string $error): string =>
                                        'ردیف '
                                        . $excelRow
                                        . ': '
                                        . $error
                                )
                                ->all();
                        }
                    )
                    ->values()
                    ->all();

            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' =>
                    array_merge(
                        [
                            'فایل دارایی از زمان پیش‌نمایش تا ثبت نهایی دیگر معتبر نیست.',
                        ],
                        $messages
                    ),
            ]);
        }

        try {
            $created =
                $commitService->commit(
                    company:
                        $company,

                    rows:
                        $freshPreview['rows'],

                    request:
                        $request
                );
        }
        catch (\Throwable $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' =>
                    'ثبت نهایی دارایی‌ها انجام نشد و هیچ ردیفی ذخیره نشد: '
                    . $e->getMessage(),
            ]);
        }

        $this->forgetAssetPreview(
            $request,
            $token,
            $stored
        );

        return redirect()
            ->route(
                'assets.index'
            )
            ->with(
                'success',
                number_format(
                    $created->count()
                )
                . ' دارایی با موفقیت به‌صورت گروهی ثبت شد.'
            );
    }


    private function storeAssetPreviewFile(
        \Illuminate\Http\Request $request,
        \App\Models\Company $company,
        \Illuminate\Http\UploadedFile $file
    ): string {
        $directory =
            storage_path(
                'app/tmp/bulk-import/asset-previews'
            );

        if (!is_dir($directory)) {
            mkdir(
                $directory,
                0775,
                true
            );
        }

        $token =
            bin2hex(
                random_bytes(24)
            );

        $path =
            $directory
            . DIRECTORY_SEPARATOR
            . $token
            . '.xlsx';

        if (
            !copy(
                $file->getRealPath(),
                $path
            )
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' =>
                    'ذخیره امن فایل پیش‌نمایش دارایی انجام نشد.',
            ]);
        }

        $request->session()
            ->put(
                $this->assetSessionKey(
                    $token
                ),
                [
                    'user_id' =>
                        $request->user()->id,

                    'company_id' =>
                        $company->id,

                    'path' =>
                        $path,

                    'sha256' =>
                        hash_file(
                            'sha256',
                            $path
                        ),

                    'expires_at' =>
                        now()
                            ->addMinutes(
                                self::PREVIEW_TTL_MINUTES
                            )
                            ->timestamp,
                ]
            );

        return $token;
    }


    private function forgetAssetPreview(
        \Illuminate\Http\Request $request,
        string $token,
        array $stored
    ): void {
        $path =
            (string) ($stored['path'] ?? '');

        if (
            $path !== ''
            && is_file($path)
        ) {
            @unlink($path);
        }

        $request->session()
            ->forget(
                $this->assetSessionKey(
                    $token
                )
            );
    }


    private function cleanupExpiredAssetPreviewFiles(): void
    {
        $directory =
            storage_path(
                'app/tmp/bulk-import/asset-previews'
            );

        if (!is_dir($directory)) {
            return;
        }

        $cutoff =
            now()
                ->subDay()
                ->timestamp;

        foreach (
            glob(
                $directory
                . DIRECTORY_SEPARATOR
                . '*.xlsx'
            ) ?: []
            as $path
        ) {
            if (
                is_file($path)
                && filemtime($path) < $cutoff
            ) {
                @unlink($path);
            }
        }
    }


    private function assetSessionKey(
        string $token
    ): string {
        return 'bulk_import.asset_preview.'
            . $token;
    }


    private function ensureCanImportAssets(
        \App\Models\User $user
    ): void {
        abort_if(
            !$user->isSuperAdmin()
            && !$user->hasPermission(
                'assets.create'
            ),
            403
        );
    }


    public function referenceStructureIndex(
        \Illuminate\Http\Request $request
    ): \Illuminate\View\View {
        $user = $request->user();

        $this->ensureCanImportReferenceStructure(
            $user
        );

        return view(
            'bulk_import.reference_structure',
            [
                'companies' =>
                    $this->companiesFor(
                        $user
                    ),

                'referencePreview' =>
                    null,
            ]
        );
    }


    public function referenceStructureTemplate(
        \Illuminate\Http\Request $request,
        \App\Services\BulkImport\BulkImportCompanyResolver $resolver,
        \App\Services\BulkImport\ReferenceStructureTemplateService $service
    ): \Symfony\Component\HttpFoundation\BinaryFileResponse {
        $user = $request->user();

        $this->ensureCanImportReferenceStructure(
            $user
        );

        $company =
            $resolver->resolve(
                $user,
                $request->integer(
                    'company_id'
                ) ?: null
            );

        $path =
            $service->generate(
                (int) $company->id
            );

        return response()
            ->download(
                $path,
                'reference-structure-template-'
                    . $company->id
                    . '.xlsx',
                [
                    'Content-Type' =>
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            )
            ->deleteFileAfterSend(
                true
            );
    }


    public function referenceStructurePreview(
        \Illuminate\Http\Request $request,
        \App\Services\BulkImport\BulkImportCompanyResolver $resolver,
        \App\Services\BulkImport\ReferenceStructurePreviewService $service
    ): \Illuminate\View\View {
        $user = $request->user();

        $this->ensureCanImportReferenceStructure(
            $user
        );

        $validated =
            $request->validate([
                'company_id' => [
                    'nullable',
                    'integer',
                    'exists:companies,id',
                ],

                'file' => [
                    'required',
                    'file',
                    'mimes:xlsx',
                    'max:10240',
                ],
            ]);

        $company =
            $resolver->resolve(
                $user,
                isset($validated['company_id'])
                    ? (int) $validated['company_id']
                    : null
            );

        try {
            $referencePreview =
                $service->preview(
                    $request->file('file')
                        ->getRealPath(),
                    (int) $company->id
                );
        }
        catch (\Throwable $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' =>
                    'فایل ساختار سازمانی قابل بررسی نیست: '
                    . $e->getMessage(),
            ]);
        }

        $referencePreviewToken =
            null;

        if (
            $referencePreview['can_commit']
            === true
        ) {
            $referencePreviewToken =
                $this->storeReferenceStructurePreviewFile(
                    request: $request,
                    company: $company,
                    file: $request->file('file')
                );
        }

        return view(
            'bulk_import.reference_structure',
            [
                'companies' =>
                    $this->companiesFor($user),

                'referencePreview' =>
                    $referencePreview,

                'referencePreviewToken' =>
                    $referencePreviewToken,
            ]
        );
    }


    public function referenceStructureCommit(
        \Illuminate\Http\Request $request,
        \App\Services\BulkImport\BulkImportCompanyResolver $resolver,
        \App\Services\BulkImport\ReferenceStructurePreviewService $previewService,
        \App\Services\BulkImport\ReferenceStructureCommitService $commitService
    ): \Symfony\Component\HttpFoundation\Response {
        $user = $request->user();

        $this->ensureCanImportReferenceStructure(
            $user
        );

        $validated =
            $request->validate([
                'preview_token' => [
                    'required',
                    'string',
                    'size:48',
                ],
            ]);

        $token =
            $validated['preview_token'];

        $sessionKey =
            $this->referenceStructureSessionKey(
                $token
            );

        $stored =
            $request->session()
                ->get($sessionKey);

        if (!is_array($stored)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' =>
                    'پیش‌نمایش ساختار سازمانی معتبر نیست یا منقضی شده است.',
            ]);
        }

        if (
            (int) ($stored['user_id'] ?? 0)
            !== (int) $user->id
        ) {
            abort(403);
        }

        if (
            (int) ($stored['expires_at'] ?? 0)
            < now()->timestamp
        ) {
            $this->forgetReferenceStructurePreview(
                $request,
                $token,
                $stored
            );

            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' =>
                    'مهلت پیش‌نمایش ساختار سازمانی پایان یافته است.',
            ]);
        }

        $path =
            (string) ($stored['path'] ?? '');

        if (
            $path === ''
            || !is_file($path)
        ) {
            $this->forgetReferenceStructurePreview(
                $request,
                $token,
                $stored
            );

            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' =>
                    'فایل پیش‌نمایش ساختار سازمانی در دسترس نیست.',
            ]);
        }

        $expectedHash =
            (string) ($stored['sha256'] ?? '');

        $actualHash =
            hash_file(
                'sha256',
                $path
            );

        if (
            $expectedHash === ''
            || !hash_equals(
                $expectedHash,
                $actualHash
            )
        ) {
            $this->forgetReferenceStructurePreview(
                $request,
                $token,
                $stored
            );

            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' =>
                    'فایل پیش‌نمایش تغییر کرده است؛ دوباره بارگذاری کنید.',
            ]);
        }

        $company =
            $resolver->resolve(
                $user,
                (int) ($stored['company_id'] ?? 0)
            );

        if (
            (int) $company->id
            !== (int) ($stored['company_id'] ?? 0)
        ) {
            abort(403);
        }

        /*
         * Fresh validation against current DB immediately before commit.
         */
        try {
            $freshPreview =
                $previewService->preview(
                    $path,
                    (int) $company->id
                );
        }
        catch (\Throwable $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' =>
                    'اعتبارسنجی نهایی ساختار سازمانی ناموفق بود: '
                    . $e->getMessage(),
            ]);
        }

        if (
            $freshPreview['can_commit']
            !== true
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' =>
                    'فایل از زمان پیش‌نمایش تا ثبت نهایی دیگر معتبر نیست؛ دوباره بررسی کنید.',
            ]);
        }

        try {
            $result =
                $commitService->commit(
                    company: $company,
                    preview: $freshPreview,
                    request: $request
                );
        }
        catch (\Throwable $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' =>
                    'ثبت ساختار سازمانی انجام نشد و هیچ ردیفی ذخیره نشد: '
                    . $e->getMessage(),
            ]);
        }

        $this->forgetReferenceStructurePreview(
            $request,
            $token,
            $stored
        );

        return redirect()
            ->route(
                'bulk-import.reference-structure.index'
            )
            ->with(
                'success',
                number_format(
                    $result['created_count']
                )
                . ' رکورد ساختار سازمانی با موفقیت ثبت شد.'
            );
    }


    private function storeReferenceStructurePreviewFile(
        \Illuminate\Http\Request $request,
        \App\Models\Company $company,
        \Illuminate\Http\UploadedFile $file
    ): string {
        $directory =
            storage_path(
                'app/tmp/bulk-import/reference-structure-previews'
            );

        if (!is_dir($directory)) {
            mkdir(
                $directory,
                0775,
                true
            );
        }

        $token =
            bin2hex(
                random_bytes(24)
            );

        $path =
            $directory
            . DIRECTORY_SEPARATOR
            . $token
            . '.xlsx';

        if (
            !copy(
                $file->getRealPath(),
                $path
            )
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' =>
                    'ذخیره امن فایل پیش‌نمایش انجام نشد.',
            ]);
        }

        $request->session()
            ->put(
                $this->referenceStructureSessionKey(
                    $token
                ),
                [
                    'user_id' =>
                        $request->user()->id,

                    'company_id' =>
                        $company->id,

                    'path' =>
                        $path,

                    'sha256' =>
                        hash_file(
                            'sha256',
                            $path
                        ),

                    'expires_at' =>
                        now()
                            ->addMinutes(30)
                            ->timestamp,
                ]
            );

        return $token;
    }


    private function forgetReferenceStructurePreview(
        \Illuminate\Http\Request $request,
        string $token,
        array $stored
    ): void {
        $path =
            (string) ($stored['path'] ?? '');

        if (
            $path !== ''
            && is_file($path)
        ) {
            @unlink($path);
        }

        $request->session()
            ->forget(
                $this->referenceStructureSessionKey(
                    $token
                )
            );
    }


    private function referenceStructureSessionKey(
        string $token
    ): string {
        return
            'bulk_import.reference_structure_preview.'
            . $token;
    }

    private function ensureCanImportReferenceStructure(
        \App\Models\User $user
    ): void {
        abort_if(
            !$user->isSuperAdmin()
            && !$user->hasPermission(
                'organization.manage'
            )
            && !$user->hasPermission(
                'employees.create'
            ),
            403
        );
    }

}