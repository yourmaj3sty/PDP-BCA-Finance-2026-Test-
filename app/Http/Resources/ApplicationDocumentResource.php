<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ApplicationDocument;
use App\Support\PublicStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApplicationDocument
 */
final class ApplicationDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ext = strtolower(pathinfo($this->file_path, PATHINFO_EXTENSION));

        return [
            'id' => $this->id,
            'document_type' => $this->document_type->value,
            'document_type_label' => $this->document_type->label(),
            'original_name' => $this->original_name,
            'file_url' => PublicStorage::url($this->file_path),
            'is_image' => in_array($ext, ['jpg', 'jpeg', 'png'], true),
            'is_pdf' => $ext === 'pdf',
            'uploaded_by' => $this->whenLoaded('uploader', fn (): ?string => $this->uploader?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
