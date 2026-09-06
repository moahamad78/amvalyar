<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupportTicket extends Model
{
    use HasFactory;

    public const TYPES = ['sales', 'demo', 'support', 'other'];

    public const STATUSES = ['new', 'in_progress', 'answered', 'closed'];

    protected $fillable = [
        'tracking_code', 'type', 'name', 'mobile', 'email', 'organization',
        'message', 'status', 'admin_note', 'handled_by', 'handled_at',
        'source_url', 'ip_hash', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'sales' => 'خرید پنل',
            'demo' => 'درخواست دمو',
            'support' => 'پشتیبانی فنی',
            default => 'سایر موارد',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'in_progress' => 'در حال بررسی',
            'answered' => 'پاسخ داده‌شده',
            'closed' => 'بسته‌شده',
            default => 'جدید',
        };
    }
}
