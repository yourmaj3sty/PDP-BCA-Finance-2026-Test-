<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CreditApplication;

final class StoreCreditApplicationRequest extends BaseCreditApplicationRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CreditApplication::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(
            $this->sharedFieldRules(requireLoanFields: false),
            $this->documentRules(requireInitialDocs: true),
        );
    }
}

