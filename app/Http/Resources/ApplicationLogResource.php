<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ApplicationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApplicationLog
 */
final class ApplicationLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'from_status' => $this->from_status?->value,
            'from_status_label' => $this->from_status?->label(),
            'to_status' => $this->to_status->value,
            'to_status_label' => $this->to_status->label(),
            'notes' => $this->notes,
            'user_name' => $this->whenLoaded('user', fn (): string => $this->user?->name ?? 'Konsumen (Tautan Kontrak)'),
            'user_role_label' => $this->whenLoaded('user', fn (): string => $this->user?->role?->label() ?? 'Konsumen'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
