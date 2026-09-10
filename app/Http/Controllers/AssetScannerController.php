<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class AssetScannerController extends Controller
{
    public function index(Request $request): View
    {
        $code = trim((string) $request->query('code', ''));
        $asset = $code !== '' ? $this->findAsset($code) : null;

        return view('asset_scanner.index', compact('code', 'asset'));
    }

    public function lookup(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $code = trim($data['code']);
        $asset = $this->findAsset($code);

        if ($asset === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'مالی با این کد، شمارهٔ سریال یا شمارهٔ اموال پیدا نشد.',
                ], 404);
            }

            return back()->withInput()->withErrors([
                'code' => 'مالی با این کد، شمارهٔ سریال یا شمارهٔ اموال پیدا نشد.',
            ]);
        }

        $url = route('assets.show', $asset);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'مال پیدا شد.',
                'asset' => [
                    'id' => $asset->id,
                    'title' => $asset->title,
                    'asset_code' => $asset->asset_code,
                ],
                'redirect_url' => $url,
            ]);
        }

        return redirect()->to($url)->with('success', 'مال با موفقیت از روی کد پیدا شد.');
    }

    private function findAsset(string $rawCode): ?Asset
    {
        $code = $this->normalizeCode($rawCode);

        if ($code === '') {
            return null;
        }

        return Asset::query()
            ->where(function ($query) use ($code): void {
                $query->where('asset_code', $code)
                    ->orWhere('inventory_code', $code)
                    ->orWhere('serial_number', $code);
            })
            ->first();
    }

    private function normalizeCode(string $value): string
    {
        $value = trim($value);

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $path = trim((string) parse_url($value, PHP_URL_PATH), '/');
            $segments = array_values(array_filter(explode('/', $path)));
            $value = (string) end($segments);
        }

        return trim(Str::of($value)->replace(["\r", "\n", "\t"], ' ')->toString());
    }
}
