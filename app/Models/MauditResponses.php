<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MauditResponses extends Model
{
    use HasFactory;

    protected $table = 'maudit_responses';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    protected $fillable = [
        'nid_audit',
        'nid_quest',
        'nnilai',
        'fna',
        'cket',
        'updated_at',
    ];

    protected $casts = [
        'nid_audit'  => 'integer',
        'nid_quest'  => 'integer',
        'nnilai'     => 'decimal:1',
        'fna'        => 'boolean',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Header Audit
     */
    public function audit()
    {
        return $this->belongsTo(MauditAudit::class, 'nid_audit', 'nid');
    }

    /**
     * Pertanyaan Audit
     */
    public function question()
    {
        return $this->belongsTo(MauditQuest::class, 'nid_quest', 'nid');
    }

    /**
     * Foto-foto untuk response ini
     */
    public function photos()
    {
        return $this->hasMany(MauditFoto::class, 'nid_resp', 'nid');
    }
}
