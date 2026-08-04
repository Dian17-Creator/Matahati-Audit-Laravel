<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditDetailRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'id' => 'required|integer|exists:maudit_audits,nid'
        ];
    }
}
