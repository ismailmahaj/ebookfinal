<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEbookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Prepare data for validation (FormData may send arrays as JSON string or category_ids[]).
     */
    protected function prepareForValidation(): void
    {
        $ids = $this->input('category_ids');
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $arr = is_array($decoded) ? $decoded : array_filter(array_map('intval', explode(',', $ids)));
            $this->merge(['category_ids' => array_values(array_map('intval', $arr))]);
        }
        if ($this->has('is_featured') && is_string($this->is_featured)) {
            $this->merge(['is_featured' => in_array(strtolower($this->is_featured), ['1', 'true', 'on'], true)]);
        }
        if ($this->has('is_active') && is_string($this->is_active)) {
            $this->merge(['is_active' => in_array(strtolower($this->is_active), ['1', 'true', 'on'], true)]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'isbn' => ['nullable', 'string', 'max:50', 'unique:ebooks,isbn'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['exists:categories,id'],
            'preview_pages' => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'pdf_file' => [
                'required',
                'file',
                'max:102400', // 100 MB
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    $mime = $value->getMimeType();
                    $allowedMimes = ['application/pdf', 'application/x-pdf'];
                    if ($ext !== 'pdf' && !in_array($mime, $allowedMimes, true)) {
                        $fail('Le fichier doit être un PDF.');
                    }
                },
            ],
            'cover_image' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:5120'], // Max 5MB
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Le titre est requis.',
            'author.required' => 'L\'auteur est requis.',
            'description.required' => 'La description est requise.',
            'category_ids.required' => 'Au moins une catégorie est requise.',
            'category_ids.*.exists' => 'Une ou plusieurs catégories sélectionnées sont invalides.',
            'pdf_file.required' => 'Le fichier PDF est requis.',
            'pdf_file.file' => 'Le PDF n\'a pas pu être envoyé (trop volumineux ou limite PHP). Lancez le serveur avec : php -d upload_max_filesize=100M -d post_max_size=100M artisan serve',
            'pdf_file.uploaded' => 'Le PDF n\'a pas pu être envoyé (trop volumineux ou limite PHP). Lancez le serveur avec : php -d upload_max_filesize=100M -d post_max_size=100M artisan serve',
            'pdf_file.mimes' => 'Le fichier doit être au format PDF.',
            'pdf_file.max' => 'Le fichier PDF ne doit pas dépasser 100 Mo.',
            'cover_image.required' => 'L\'image de couverture est requise.',
            'cover_image.file' => 'L\'image n\'a pas pu être envoyée. Vérifiez la taille (max 5 Mo) et les limites PHP (upload_max_filesize, post_max_size).',
            'cover_image.uploaded' => 'L\'image n\'a pas pu être envoyée. Vérifiez la taille (max 5 Mo) et les limites PHP.',
            'cover_image.image' => 'Le fichier doit être une image.',
            'cover_image.mimes' => 'L\'image doit être au format JPEG, JPG ou PNG.',
            'cover_image.max' => 'L\'image ne doit pas dépasser 5 Mo.',
            'preview_pages.min' => 'Le nombre de pages de preview doit être au moins 1.',
            'preview_pages.max' => 'Le nombre de pages de preview ne doit pas dépasser 100.',
        ];
    }
}
