<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserStatusLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

class UserStatusService
{
    public function suspend(User $target, User $responsible, string $reason): UserStatusLog
    {
        throw_if(
            $responsible->cannot('suspend', $target),
            AuthorizationException::class,
            'No tienes permiso para suspender este usuario.'
        );

        throw_if(
            $target->isSuspended(),
            LogicException::class,
            'El usuario ya está suspendido.'
        );

        return $this->transition($target, $responsible, UserStatus::Suspended, $reason);
    }

    public function reactivate(User $target, User $responsible, ?string $reason = null): UserStatusLog
    {
        throw_if(
            $responsible->cannot('reactivate', $target),
            AuthorizationException::class,
            'No tienes permiso para reactivar este usuario.'
        );

        throw_if(
            $target->isActive(),
            LogicException::class,
            'El usuario ya está activo.'
        );

        return $this->transition($target, $responsible, UserStatus::Active, $reason);
    }

    private function transition(User $target, User $responsible, UserStatus $toStatus, ?string $reason): UserStatusLog
    {
        return DB::transaction(function () use ($target, $responsible, $toStatus, $reason): UserStatusLog {
            $target->refresh();
            $fromStatus = $target->status;

            $target->forceFill(['status' => $toStatus])->save();

            return UserStatusLog::create([
                'user_id' => $target->id,
                'changed_by' => $responsible->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'reason' => $reason,
            ]);
        });
    }
}
