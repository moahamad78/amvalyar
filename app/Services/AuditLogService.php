<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditLogService
{
    public function log(
        string $action,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null,
        ?Request $request = null
    ): AuditLog {

        $user = auth()->user();

        $companyId =
            $user?->isSuperAdmin()
                ? (
                    method_exists(
                        $subject,
                        'getAttribute'
                    )
                        ? $subject?->getAttribute(
                            'company_id'
                        )
                        : null
                )
                : $user?->company_id;


        $subjectLabel = null;

        if ($subject !== null) {

            $subjectLabel =
                $subject->getAttribute('name')
                ?? $subject->getAttribute('title')
                ?? $subject->getAttribute('code')
                ?? $subject->getAttribute('username')
                ?? null;
        }


        $request ??=
            request();


        return AuditLog::query()->create([
            'company_id' =>
                $companyId,

            'user_id' =>
                $user?->id,

            'action' =>
                $action,

            'subject_type' =>
                $subject
                    ? $subject::class
                    : null,

            'subject_id' =>
                $subject?->getKey(),

            'subject_label' =>
                $subjectLabel,

            'old_values' =>
                $oldValues !== []
                    ? $oldValues
                    : null,

            'new_values' =>
                $newValues !== []
                    ? $newValues
                    : null,

            'description' =>
                $description,

            'ip_address' =>
                $request?->ip(),

            'user_agent' =>
                $request?->userAgent(),
        ]);
    }
}