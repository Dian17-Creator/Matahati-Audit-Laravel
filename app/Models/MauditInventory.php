<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MauditInventory extends Model
{
    use HasFactory;

    protected $table = 'maudit_inventory';

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
        'cauditre',
        'cphoto_path',
    ];

    protected $casts = [
        'nid' => 'integer',
        'nid_dept' => 'integer',
        'nid_auditor' => 'integer',

        'started_at' => 'datetime',
        'updated_at' => 'datetime',
        'submitted_at' => 'datetime',

        'daudit' => 'date',
    ];

    /**
     * Departemen yang diaudit
     */
    public function department()
    {
        return $this->belongsTo(
            Mdepartment::class,
            'nid_dept',
            'nid'
        );
    }

    /**
     * Auditor yang melakukan audit
     */
    public function auditor()
    {
        return $this->belongsTo(
            Muser::class,
            'nid_auditor',
            'nid'
        );
    }
}
