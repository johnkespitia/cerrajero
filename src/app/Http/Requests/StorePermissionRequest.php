<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('permission.create', 'cerrajero');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:125', 'unique:permissions,name'],
            'guard_name' => ['required', 'string', 'max:125'],
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
            'name.required' => 'El nombre del permiso es obligatorio',
            'name.unique' => 'Este nombre de permiso ya existe',
            'guard_name.required' => 'El guard name es obligatorio',
        ];
    }
}

