<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditUpdateAnswersRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'audit_id' => 'required|integer|exists:maudit_audits,nid',
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer|exists:maudit_quest,nid',
            'answers.*.score' => 'nullable|numeric|min:0|max:2',
            'answers.*.is_na' => 'nullable|boolean',
            'answers.*.remark' => 'nullable|string'
        ];
    }
}
