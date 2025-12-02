<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RolePermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $action = $this->route()->getActionMethod();
        
        if ($action === 'grantPermission') {
            return $this->user()->can('role.grant_permission', 'cerrajero');
        }
        
        return $this->user()->can('role.revoke_permission', 'cerrajero');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'permission' => ['required', 'string', 'max:125', 'exists:permissions,name'],
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
            'permission.required' => 'El permiso es obligatorio',
            'permission.exists' => 'El permiso especificado no existe',
        ];
    }
}

