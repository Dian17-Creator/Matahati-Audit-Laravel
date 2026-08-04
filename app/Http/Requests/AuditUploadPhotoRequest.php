<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditUploadPhotoRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'response_id' => 'required|integer|exists:maudit_responses,nid',
            'photo' => 'required|image|mimes:jpeg,png,jpg,webp|max:20480',
            'cket' => 'nullable|string',
            'caction' => 'nullable|string'
        ];
    }
}
