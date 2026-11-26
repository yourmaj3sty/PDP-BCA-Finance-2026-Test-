<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CreditApplication;
use Illuminate\Foundation\Http\FormRequest;

final class DisburseApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CreditApplication|null $application */
        $application = $this->route('application');

        return $application !== null && ($this->user()?->can('disburse', $application) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'disbursement_ref' => ['required', 'string', 'min:4', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'disbursement_ref.required' => 'Nomor referensi transfer/pencairan wajib diisi.',
            'disbursement_ref.min' => 'Nomor referensi transfer minimal 4 karakter.',
        ];
    }
}

