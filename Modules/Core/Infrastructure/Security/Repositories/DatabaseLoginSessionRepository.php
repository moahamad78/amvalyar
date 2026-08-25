<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Security\Repositories;

use Modules\Core\Application\Security\Repositories\LoginSessionRepositoryInterface;
use Modules\Core\Domain\Security\Entities\LoginSession;
use Modules\Core\Domain\Security\ValueObjects\SessionId;
use Modules\Core\Domain\Security\ValueObjects\UserId;
use Modules\Core\Infrastructure\Security\Models\LoginSessionModel;

final class DatabaseLoginSessionRepository implements LoginSessionRepositoryInterface
{
    public function save(LoginSession $session): void
    {
        $model = LoginSessionModel::query()
            ->where('session_id', $session->sessionId()->value())
            ->first();

        if ($model === null) {
            $model = new LoginSessionModel();
        }

        $model->session_id = $session->sessionId()->value();
        $model->user_id = $session->userId()->value();
        $model->ip_address = $session->ipAddress();
        $model->user_agent = $session->userAgent();
        $model->computer_name = $session->computerName();
        $model->started_at = $session->createdAt();
        $model->last_activity_at = $session->lastActivityAt();
        $model->revoked_at = $session->revokedAt();

        $model->save();
    }

    public function findById(SessionId $sessionId): ?LoginSession
    {
        $model = LoginSessionModel::query()
            ->where('session_id', $sessionId->value())
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findActiveByUserId(UserId $userId): ?LoginSession
    {
        $model = LoginSessionModel::query()
            ->where('user_id', $userId->value())
            ->whereNull('revoked_at')
            ->latest('started_at')
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function delete(SessionId $sessionId): void
    {
        LoginSessionModel::query()
            ->where('session_id', $sessionId->value())
            ->delete();
    }

    private function toDomain(LoginSessionModel $model): LoginSession
    {
        return LoginSession::restore(
            SessionId::fromString($model->session_id),
            UserId::fromInt($model->user_id),
            $model->ip_address,
            $model->user_agent,
            $model->computer_name,
            $model->started_at->toDateTimeImmutable(),
            $model->last_activity_at->toDateTimeImmutable(),
            $model->revoked_at?->toDateTimeImmutable()
        );
    }
}