<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('permission.edit', 'cerrajero');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $permissionId = $this->route('permission')->id ?? null;

        return [
            'name' => ['sometimes', 'string', 'max:125', Rule::unique('permissions')->ignore($permissionId)],
            'guard_name' => ['sometimes', 'string', 'max:125'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name.unique' => 'Este nombre de permiso ya existe',
        ];
    }
}

