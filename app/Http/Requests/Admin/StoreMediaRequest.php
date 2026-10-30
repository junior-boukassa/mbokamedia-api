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
                if ($this->input('purpose') !== 'reel' || ! $this->file('file') instanceof UploadedFile) {
                    return;
                }

                $file = $this->file('file');
                $extension = strtolower($file->getClientOriginalExtension());
                $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
                $isActualContainer = match ($extension) {
                    'mp4' => in_array($mimeType, ['video/mp4', 'application/mp4'], true),
                    'mov' => $mimeType === 'video/quicktime',
                    default => false,
                };

                if (! $isActualContainer) {
                    $validator->errors()->add('file', 'Le fichier doit être un vrai conteneur vidéo MP4 ou MOV.');
                }
            },
        ];
    }
}
