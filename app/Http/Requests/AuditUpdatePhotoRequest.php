<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditUpdatePhotoRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'id' => 'required|integer|exists:maudit_foto,nid',
            'observation' => 'nullable|string',
            'recommendation' => 'nullable|string'
        ];
    }
}
