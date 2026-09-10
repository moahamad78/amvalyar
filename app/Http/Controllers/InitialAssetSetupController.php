<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Services\BulkImport\BulkImportCompanyResolver;
use App\Services\BulkImport\InitialAssetSetupCommitService;
use App\Services\BulkImport\InitialAssetSetupPreviewService;
use App\Services\BulkImport\InitialAssetSetupTemplateService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class InitialAssetSetupController extends Controller
{
    private const TTL_MINUTES = 30;

    public function index(Request $request): Response
    {
        $this->ensureAccess($request->user());
        return response()->view('initial_setup.index', [
            'companies' => $this->companiesFor($request->user()),
            'preview' => null, 'previewToken' => null,
        ]);
    }

    public function template(Request $request, BulkImportCompanyResolver $resolver, InitialAssetSetupTemplateService $service): BinaryFileResponse
    {
        $this->ensureAccess($request->user());
        $company = $resolver->resolve($request->user(), $request->integer('company_id') ?: null);
        $spreadsheet = $service->build($company);
        $directory = storage_path('app/tmp/bulk-import');
        if (!is_dir($directory)) mkdir($directory, 0775, true);
        $path = $directory.DIRECTORY_SEPARATOR.'initial-asset-setup-'.$company->id.'-'.now()->format('Ymd-His').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        return response()->download($path, 'initial-asset-setup-'.$company->id.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function preview(Request $request, BulkImportCompanyResolver $resolver, InitialAssetSetupPreviewService $service): Response
    {
        $this->ensureAccess($request->user());
        $request->validate([
            'company_id' => ['nullable', 'integer', 'min:1'],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);
        $company = $resolver->resolve($request->user(), $request->integer('company_id') ?: null);
        try { $preview = $service->preview($request->file('file')->getRealPath(), (int) $company->id); }
        catch (\Throwable $e) { throw ValidationException::withMessages(['file' => 'فایل راه‌اندازی اولیه قابل بررسی نیست: '.$e->getMessage()]); }
        $token = $this->storePreview($request, $company, $request->file('file'));
        return response()->view('initial_setup.index', [
            'companies' => $this->companiesFor($request->user()), 'preview' => $preview, 'previewToken' => $token,
        ]);
    }

    public function commit(Request $request, BulkImportCompanyResolver $resolver, InitialAssetSetupPreviewService $previewService, InitialAssetSetupCommitService $commitService): Response
    {
        $this->ensureAccess($request->user());
        $request->validate(['preview_token' => ['required', 'string', 'regex:/^[a-f0-9]{48}$/']]);
        $token = (string) $request->input('preview_token');
        $stored = $request->session()->get($this->sessionKey($token));
        if (!is_array($stored) || (int) ($stored['user_id'] ?? 0) !== (int) $request->user()->id || (int) ($stored['expires_at'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages(['file' => 'پیش‌نمایش منقضی شده است؛ فایل را دوباره بررسی کنید.']);
        }
        if (!is_file((string) ($stored['path'] ?? '')) || !hash_equals((string) ($stored['sha256'] ?? ''), hash_file('sha256', $stored['path']))) {
            throw ValidationException::withMessages(['file' => 'فایل پیش‌نمایش دیگر معتبر نیست.']);
        }
        $company = $resolver->resolve($request->user(), (int) ($stored['company_id'] ?? 0));
        $preview = $previewService->preview($stored['path'], (int) $company->id);
        if (!$preview['can_commit']) {
            throw ValidationException::withMessages(['file' => 'پیش‌نمایش لحظه‌ای شامل خطا است و ثبت انجام نشد.']);
        }
        try { $created = $commitService->commit($company, $preview['rows'], $request); }
        catch (\Throwable $e) { throw ValidationException::withMessages(['file' => 'ثبت موجودی اولیه انجام نشد و هیچ ردیفی ذخیره نشد: '.$e->getMessage()]); }
        $this->forgetPreview($request, $token, $stored);
        return redirect()->route('initial-setup.index')->with('success', number_format($created->count()).' دارایی موجودی اولیه با کد دائمی و وضعیت فعلی ثبت شد.');
    }

    private function ensureAccess(User $user): void
    {
        abort_if(!$user->isSuperAdmin() && !$user->hasPermission('assets.create'), 403);
    }
    private function companiesFor(User $user)
    {
        return $user->isSuperAdmin() ? Company::query()->orderBy('name')->get(['id', 'name']) : collect();
    }
    private function storePreview(Request $request, Company $company, $file): string
    {
        $directory = storage_path('app/tmp/bulk-import/initial-asset-previews');
        if (!is_dir($directory)) mkdir($directory, 0775, true);
        $token = bin2hex(random_bytes(24));
        $path = $directory.DIRECTORY_SEPARATOR.$token.'.xlsx';
        if (!copy($file->getRealPath(), $path)) throw ValidationException::withMessages(['file' => 'ذخیره امن فایل پیش‌نمایش انجام نشد.']);
        $request->session()->put($this->sessionKey($token), [
            'user_id' => $request->user()->id, 'company_id' => $company->id, 'path' => $path,
            'sha256' => hash_file('sha256', $path), 'expires_at' => now()->addMinutes(self::TTL_MINUTES)->timestamp,
        ]);
        return $token;
    }
    private function forgetPreview(Request $request, string $token, array $stored): void
    {
        $path = (string) ($stored['path'] ?? '');
        if ($path !== '' && is_file($path)) @unlink($path);
        $request->session()->forget($this->sessionKey($token));
    }
    private function sessionKey(string $token): string { return 'initial_setup.asset_preview.'.$token; }
}
