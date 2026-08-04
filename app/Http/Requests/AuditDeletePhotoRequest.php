<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditDeletePhotoRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'photo_id' => 'required|integer|exists:maudit_foto,nid'
        ];
    }
}
