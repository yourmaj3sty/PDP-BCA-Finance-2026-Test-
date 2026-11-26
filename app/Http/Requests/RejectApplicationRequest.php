<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CreditApplication;
use Illuminate\Foundation\Http\FormRequest;

final class RejectApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CreditApplication|null $application */
        $application = $this->route('application');

        return $application !== null && ($this->user()?->can('reject', $application) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('notes') && $this->has('rejection_note')) {
            $this->merge(['notes' => $this->input('rejection_note')]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'notes.required' => 'Catatan alasan penolakan wajib diisi.',
            'notes.min' => 'Catatan penolakan minimal 5 karakter.',
        ];
    }
}

