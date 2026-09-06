<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class PublicSupportTicketController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $payload = $request->all();
        $payload['mobile'] = $this->normalizeDigits((string) ($payload['mobile'] ?? ''));

        $validator = Validator::make($payload, [
            'type' => ['required', Rule::in(SupportTicket::TYPES)],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'mobile' => ['required', 'regex:/^(?:\+98|0098|98|0)?9\d{9}$/'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'organization' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'privacy' => ['accepted'],
            'website' => ['nullable', 'max:0'],
            'source_url' => ['nullable', 'url', 'max:500'],
        ], [
            'mobile.regex' => 'شماره موبایل معتبر وارد کنید.',
            'privacy.accepted' => 'پذیرش شرایط حریم خصوصی الزامی است.',
        ]);

        $validated = $validator->validate();
        $ticket = SupportTicket::query()->create([
            'tracking_code' => $this->trackingCode(),
            'type' => $validated['type'],
            'name' => trim($validated['name']),
            'mobile' => $this->canonicalMobile($validated['mobile']),
            'email' => filled($validated['email'] ?? null) ? mb_strtolower(trim($validated['email'])) : null,
            'organization' => filled($validated['organization'] ?? null) ? trim($validated['organization']) : null,
            'message' => trim($validated['message']),
            'source_url' => $validated['source_url'] ?? null,
            'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), (string) config('app.key')) : null,
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        return response()->json([
            'message' => 'درخواست شما با موفقیت ثبت شد.',
            'tracking_code' => $ticket->tracking_code,
        ], 201);
    }

    private function normalizeDigits(string $value): string
    {
        return str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            preg_replace('/[\s\-()]/u', '', trim($value)) ?? ''
        );
    }

    private function canonicalMobile(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', $mobile) ?? '';
        if (str_starts_with($digits, '0098')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '98')) {
            $digits = '0'.substr($digits, 2);
        }
        if (str_starts_with($digits, '9')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    private function trackingCode(): string
    {
        do {
            $code = 'AY-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (SupportTicket::query()->where('tracking_code', $code)->exists());

        return $code;
    }
}
