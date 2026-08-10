<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MauditInvFoto extends Model
{
    protected $table = 'maudit_invfoto';

    protected $primaryKey = 'nid';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'nid_resp',
        'nsequence',
        'cket',
        'caction',
        'cphoto_path',
        'uploaded_at',
    ];

    protected $casts = [
        'nid' => 'integer',
        'nid_resp' => 'integer',
        'nsequence' => 'integer',
        'uploaded_at' => 'datetime',
    ];
}
