<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(SupportTicket::STATUSES)],
            'type' => ['nullable', Rule::in(SupportTicket::TYPES)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $tickets = SupportTicket::query()
            ->with('handler:id,name')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['q'] ?? null, function ($query, $term): void {
                $query->where(function ($inner) use ($term): void {
                    $inner->where('tracking_code', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%")
                        ->orWhere('mobile', 'like', "%{$term}%")
                        ->orWhere('organization', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = SupportTicket::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('support-tickets.index', compact('tickets', 'counts'));
    }

    public function update(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(SupportTicket::STATUSES)],
            'admin_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $supportTicket->forceFill([
            ...$validated,
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ])->save();

        return back()->with('success', 'وضعیت درخواست پشتیبانی به‌روزرسانی شد.');
    }
}
