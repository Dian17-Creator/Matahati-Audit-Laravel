<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MauditFoto extends Model
{
    use HasFactory;

    protected $table = 'maudit_foto';

    protected $primaryKey = 'nid';

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
        'nid_resp'    => 'integer',
        'nsequence'   => 'integer',
        'uploaded_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Response audit yang memiliki foto ini.
     */
    public function response()
    {
        return $this->belongsTo(MauditResponses::class, 'nid_resp', 'nid');
    }
}
