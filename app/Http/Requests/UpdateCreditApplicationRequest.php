<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\CreditApplication;

final class UpdateCreditApplicationRequest extends BaseCreditApplicationRequest
{
    public function authorize(): bool
    {
        /** @var CreditApplication|null $application */
        $application = $this->route('application');

        return $application !== null && ($this->user()?->can('update', $application) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isMarketing = $this->user()?->hasRole(UserRole::Marketing) ?? false;

        return array_merge(
            $this->sharedFieldRules(requireLoanFields: $isMarketing),
            $this->documentRules(requireInitialDocs: false),
        );
    }
}

