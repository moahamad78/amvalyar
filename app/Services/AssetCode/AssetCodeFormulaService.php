<?php

declare(strict_types=1);

namespace App\Services\AssetCode;

use App\Models\AssetCodeFormulaSetting;
use Illuminate\Validation\ValidationException;

final class AssetCodeFormulaService
{
    public function settingsFor(int $companyId): AssetCodeFormulaSetting
    {
        return AssetCodeFormulaSetting::query()
            ->where('company_id', $companyId)
            ->first()
            ?? AssetCodeFormulaSetting::defaultsFor($companyId);
    }

    public function buildCode(
        AssetCodeFormulaSetting $settings,
        string $siteCode,
        string $categoryCode,
        string $typeCode,
        int $serial
    ): string {
        $segments = [
            'site' => $this->masterCode($siteCode),
            'category' => $this->masterCode($categoryCode),
            'type' => $this->masterCode($typeCode),
            'serial' => str_pad(
                (string) $serial,
                max(1, min(12, (int) $settings->serial_length)),
                '0',
                STR_PAD_LEFT
            ),
        ];

        if ($settings->enforce_segment_lengths) {
            $this->assertLength($segments['site'], (int) $settings->site_length, 'کد سایت');
            $this->assertLength($segments['category'], (int) $settings->category_length, 'کد ماهیت اصلی');
            $this->assertLength($segments['type'], (int) $settings->type_length, 'کد ماهیت فرعی');
        }

        $ordered = [];
        foreach ($this->normalizedOrder($settings) as $segment) {
            $ordered[] = $segments[$segment];
        }

        return implode($this->separator($settings), $ordered);
    }

    public function sequenceKey(
        AssetCodeFormulaSetting $settings,
        string $siteCode,
        string $categoryCode,
        string $typeCode
    ): string {
        $scope = in_array(
            $settings->sequence_scope,
            AssetCodeFormulaSetting::allowedScopes(),
            true
        ) ? $settings->sequence_scope : 'family';

        return match ($scope) {
            'company' => '@FORMULA:COMPANY',
            'site' => '@FORMULA:SITE:' . $siteCode,
            'category' => '@FORMULA:CATEGORY:' . $categoryCode,
            default => implode(
                $this->separator($settings),
                [$siteCode, $categoryCode, $typeCode]
            ),
        };
    }

    public function preview(
        AssetCodeFormulaSetting $settings,
        string $siteCode = '01',
        string $categoryCode = '06',
        string $typeCode = '012',
        int $serial = 1
    ): string {
        return $this->buildCode($settings, $siteCode, $categoryCode, $typeCode, $serial);
    }

    public function separator(AssetCodeFormulaSetting $settings): string
    {
        $separator = (string) ($settings->separator ?? '-');

        return in_array($separator, ['-', '_', '.', '/', ''], true)
            ? $separator
            : '-';
    }

    private function normalizedOrder(AssetCodeFormulaSetting $settings): array
    {
        $order = is_array($settings->segment_order)
            ? array_values($settings->segment_order)
            : [];

        $expected = AssetCodeFormulaSetting::allowedSegments();
        $a = $order;
        $b = $expected;
        sort($a);
        sort($b);

        return $a === $b ? $order : $expected;
    }

    private function masterCode(string $value): string
    {
        return strtoupper(trim($value));
    }

    private function assertLength(string $value, int $expected, string $label): void
    {
        $expected = max(1, min(30, $expected));

        if (mb_strlen($value) !== $expected) {
            throw ValidationException::withMessages([
                'asset_code' => $label . ' باید دقیقاً ' . $expected . ' کاراکتر باشد.',
            ]);
        }
    }
}
