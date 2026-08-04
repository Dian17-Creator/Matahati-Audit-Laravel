<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MauditAudit extends Model
{
    use HasFactory;

    protected $table = 'maudit_audits';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    protected $fillable = [
        'cdocid',
        'nid_dept',
        'nid_auditor',
        'cstatus',
        'started_at',
        'updated_at',
        'submitted_at',
        'daudit',
        'ntotnilai',
        'nnilaimax',
        'npersen',
        'cauditee',
        'cphoto_path',
    ];

    protected $casts = [
        'started_at'  => 'datetime:Y-m-d H:i:s',
        'updated_at'  => 'datetime:Y-m-d H:i:s',
        'submitted_at' => 'datetime:Y-m-d H:i:s',
        'daudit'      => 'date:Y-m-d',

        'ntotnilai'   => 'decimal:2',
        'nnilaimax'   => 'decimal:2',
        'npersen'     => 'decimal:2',
    ];

    /**
     * Department yang diaudit
     */
    public function department()
    {
        return $this->belongsTo(mdepartment::class, 'nid_dept', 'nid');
    }

    /**
     * Auditor
     */
    public function auditor()
    {
        return $this->belongsTo(muser::class, 'nid_auditor', 'nid');
    }

    /**
     * Responses dari Audit
     */
    public function responses()
    {
        return $this->hasMany(MauditResponses::class, 'nid_audit', 'nid');
    }
}
