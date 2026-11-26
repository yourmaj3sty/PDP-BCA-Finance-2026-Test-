<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CreditApplication;
use Illuminate\Foundation\Http\FormRequest;

final class ApproveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CreditApplication|null $application */
        $application = $this->route('application');

        return $application !== null && ($this->user()?->can('approve', $application) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

