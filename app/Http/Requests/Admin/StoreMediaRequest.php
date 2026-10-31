<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:102400'],
            'name' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'purpose' => ['sometimes', 'in:reel'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->file('file') instanceof UploadedFile) {
                    return;
                }

                $file = $this->file('file');
                $extension = strtolower($file->getClientOriginalExtension());
                $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());

                if ($this->input('purpose') === 'reel') {
                    $isActualContainer = match ($extension) {
                        'mp4' => in_array($mimeType, ['video/mp4', 'application/mp4'], true),
                        'mov' => $mimeType === 'video/quicktime',
                        default => false,
                    };

                    if (! $isActualContainer) {
                        $validator->errors()->add('file', 'Le fichier doit être un vrai conteneur vidéo MP4 ou MOV.');
                    }

                    return;
                }

                $allowedExtensionsByMime = [
                    'image/jpeg' => ['jpg', 'jpeg'],
                    'image/png' => ['png'],
                    'image/webp' => ['webp'],
                    'image/gif' => ['gif'],
                    'video/mp4' => ['mp4', 'm4v'],
                    'application/mp4' => ['mp4', 'm4v'],
                    'video/webm' => ['webm'],
                    'video/quicktime' => ['mov'],
                    'video/x-m4v' => ['m4v'],
                    'video/ogg' => ['ogg'],
                ];
                $allowedExtensions = $allowedExtensionsByMime[$mimeType] ?? [];

                if (! in_array($extension, $allowedExtensions, true)) {
                    $validator->errors()->add('file', 'Le fichier doit être une image ou une vidéo prise en charge.');
                    return;
                }

                if (str_starts_with($mimeType, 'image/') && $file->getSize() > 10 * 1024 * 1024) {
                    $validator->errors()->add('file', 'Les images ne peuvent pas dépasser 10 Mo.');
                }
            },
        ];
    }
}
