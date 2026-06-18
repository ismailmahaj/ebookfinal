<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEbookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Prepare data for validation (FormData may send category_ids as JSON string).
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
        $ebookId = $this->route('id') ?? $this->route('ebook');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'author' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'isbn' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('ebooks', 'isbn')->ignore($ebookId),
            ],
            'category_ids' => ['sometimes', 'required', 'array', 'min:1'],
            'category_ids.*' => ['exists:categories,id'],
            'preview_pages' => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'pdf_file' => [
                'sometimes',
                'file',
                'max:102400',
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    $mime = $value->getMimeType();
                    $allowedMimes = ['application/pdf', 'application/x-pdf'];
                    if ($ext !== 'pdf' && !in_array($mime, $allowedMimes, true)) {
                        $fail('Le fichier doit être un PDF.');
                    }
                },
            ],
            'cover_image' => ['sometimes', 'image', 'mimes:jpeg,jpg,png', 'max:5120'], // Max 5MB
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
            'pdf_file.mimes' => 'Le fichier doit être au format PDF.',
            'pdf_file.max' => 'Le fichier PDF ne doit pas dépasser 100 Mo.',
            'cover_image.image' => 'Le fichier doit être une image.',
            'cover_image.mimes' => 'L\'image doit être au format JPEG, JPG ou PNG.',
            'cover_image.max' => 'L\'image ne doit pas dépasser 5 Mo.',
            'preview_pages.min' => 'Le nombre de pages de preview doit être au moins 1.',
            'preview_pages.max' => 'Le nombre de pages de preview ne doit pas dépasser 100.',
        ];
    }
}
