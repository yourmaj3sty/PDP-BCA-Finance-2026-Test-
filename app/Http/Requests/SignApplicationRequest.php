<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CreditApplication;
use Illuminate\Foundation\Http\FormRequest;

final class SignApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route('token') !== null) {
            return true;
        }

        /** @var CreditApplication|null $application */
        $application = $this->route('application');

        return $application !== null && ($this->user()?->can('sign', $application) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agreed' => ['required', 'accepted'],
            'signature_image' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'kontrak_ttd' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'agreed.accepted' => 'Konfirmasi persetujuan perjanjian kredit wajib dicentang.',
            'signature_image.mimes' => 'Tanda tangan harus berupa gambar PNG atau JPG.',
            'kontrak_ttd.mimes' => 'Bukti kontrak harus berupa file JPG, PNG, atau PDF.',
            '*.max' => 'Ukuran file maksimal 2 MB.',
        ];
    }
}

