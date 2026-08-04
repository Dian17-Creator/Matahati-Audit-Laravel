<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditCreateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'department_id' => 'required|integer|exists:mdepartment,nid',
            'auditor_id' => 'nullable|integer|exists:muser,nid'
        ];
    }
}
