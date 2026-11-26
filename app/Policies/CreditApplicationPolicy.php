<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\CreditApplication;
use App\Models\User;

final class CreditApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(
            UserRole::SalesDealer,
            UserRole::Marketing,
            UserRole::AtasanMarketing,
            UserRole::AdminBackoffice,
        );
    }

    public function view(User $user, CreditApplication $application): bool
    {
        if ($user->hasRole(UserRole::SalesDealer)) {
            return $application->created_by === $user->id;
        }

        return $user->hasRole(
            UserRole::Marketing,
            UserRole::AtasanMarketing,
            UserRole::AdminBackoffice,
        );
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::SalesDealer);
    }

    public function update(User $user, CreditApplication $application): bool
    {
        if (! $application->status->isEditable()) {
            return false;
        }

        if ($user->hasRole(UserRole::Marketing)) {
            return true;
        }

        // Sales Dealer hanya boleh mengubah draft miliknya sendiri; revisi setelah ditolak dikerjakan Marketing.
        return $user->hasRole(UserRole::SalesDealer)
            && $application->status === ApplicationStatus::DraftDealer
            && $application->created_by === $user->id;
    }

    public function submit(User $user, CreditApplication $application): bool
    {
        return $user->hasRole(UserRole::Marketing) && $application->status->isEditable();
    }

    public function approve(User $user, CreditApplication $application): bool
    {
        return $user->hasRole(UserRole::AtasanMarketing)
            && $application->status === ApplicationStatus::Submitted;
    }

    public function reject(User $user, CreditApplication $application): bool
    {
        return $user->hasRole(UserRole::AtasanMarketing)
            && $application->status === ApplicationStatus::Submitted;
    }

    public function viewDocuments(User $user, CreditApplication $application): bool
    {
        return $this->view($user, $application) && $application->status->canPrintDocuments();
    }

    public function sendPo(User $user, CreditApplication $application): bool
    {
        return $user->hasRole(UserRole::Marketing) && $application->status->canPrintDocuments();
    }

    public function sign(User $user, CreditApplication $application): bool
    {
        return $user->hasRole(UserRole::Marketing, UserRole::AdminBackoffice)
            && $application->status === ApplicationStatus::Approved;
    }

    public function disburse(User $user, CreditApplication $application): bool
    {
        return $user->hasRole(UserRole::AdminBackoffice)
            && $application->status === ApplicationStatus::Signed;
    }
}

