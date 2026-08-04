<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditListRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'department_id' => 'required|integer|exists:mdepartment,nid',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'page' => 'nullable|integer|min:1'
        ];
    }
}
