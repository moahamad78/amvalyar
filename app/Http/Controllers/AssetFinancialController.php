<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\AuditLogService;
use App\Services\RoleAccessService;
use App\Support\JalaliDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AssetFinancialController extends Controller
{
    public function edit(Request $request, Asset $asset): View
    {
        $this->ensureVisible($request, $asset);

        return view('assets.financial-edit', compact('asset'));
    }

    public function update(
        Request $request,
        Asset $asset,
        AuditLogService $auditLog
    ): RedirectResponse {
        $this->ensureVisible($request, $asset);

        $data = $request->validate([
            'purchase_date' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        JalaliDate::toGregorianDate(is_string($value) ? $value : null);
                    } catch (\Throwable) {
                        $fail('تاریخ تحصیل معتبر نیست. نمونه صحیح: 1405/05/16');
                    }
                },
            ],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'acquisition_document_number' => ['nullable', 'string', 'max:150'],
        ]);

        if ((float) $data['purchase_price'] > 0) {
            if (trim((string) ($data['purchase_date'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'purchase_date' => 'برای دارایی دارای مبلغ، تاریخ تحصیل الزامی است.',
                ]);
            }
            if (trim((string) ($data['acquisition_document_number'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'acquisition_document_number' => 'برای دارایی دارای مبلغ، شماره سند خرید/تحصیل الزامی است.',
                ]);
            }
        }

        $normalized = [
            'purchase_date' => JalaliDate::toGregorianDate($data['purchase_date'] ?? null),
            'purchase_price' => $data['purchase_price'],
            'supplier_name' => $this->nullableString($data['supplier_name'] ?? null),
            'acquisition_document_number' => $this->nullableString($data['acquisition_document_number'] ?? null),
        ];

        DB::transaction(function () use ($request, $asset, $normalized, $auditLog): void {
            $locked = Asset::withoutGlobalScopes()->lockForUpdate()->findOrFail($asset->id);
            $this->ensureVisible($request, $locked);
            $old = $this->snapshot($locked);

            $locked->forceFill($normalized)->save();
            $new = $this->snapshot($locked->fresh());

            $auditLog->log(
                'asset.financial_updated',
                $locked,
                $old,
                $new,
                'اطلاعات مالی و مستند خرید/تحصیل دارایی ویرایش شد.',
                $request,
                $request->user()
            );
        });

        return redirect()->route('assets.show', $asset)
            ->with('success', 'اطلاعات مالی دارایی با ثبت کامل سابقه به‌روزرسانی شد.');
    }

    private function ensureVisible(Request $request, Asset $asset): void
    {
        $user = $request->user();
        if (! app(RoleAccessService::class)->financialEdit($user)) {
            abort(403);
        }
        if (! $user->isSuperAdmin() && (int) $user->company_id !== (int) $asset->company_id) {
            abort(404);
        }
    }

    private function snapshot(Asset $asset): array
    {
        return [
            'purchase_date' => $asset->getRawOriginal('purchase_date'),
            'purchase_price' => $asset->getRawOriginal('purchase_price'),
            'supplier_name' => $asset->getRawOriginal('supplier_name'),
            'acquisition_document_number' => $asset->getRawOriginal('acquisition_document_number'),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
