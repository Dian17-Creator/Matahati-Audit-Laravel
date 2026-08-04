<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditSubmitRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'audit_id' => 'required|integer|exists:maudit_audits,nid',
            'auditee_name' => 'required|string|max:150',
            'verification_photo' => 'required|image|mimes:jpeg,png,jpg,webp|max:8192'
        ];
    }
}
