<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MauditQuest extends Model
{
    use HasFactory;

    protected $table = 'maudit_quest';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    protected $fillable = [
        'nid_kat',
        'cquest',
        'nsequence',
        'factive',
        'created_at',
    ];

    protected $casts = [
        'nid_kat'    => 'integer',
        'nsequence'  => 'integer',
        'factive'    => 'boolean',
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Kategori pertanyaan
     */
    public function category()
    {
        return $this->belongsTo(MauditKat::class, 'nid_kat', 'nid');
    }

    /**
     * Scope hanya pertanyaan aktif
     */
    public function scopeActive($query)
    {
        return $query->where('factive', 1);
    }

    /**
     * Responses yang menggunakan pertanyaan ini
     */
    public function responses()
    {
        return $this->hasMany(MauditResponses::class, 'nid_quest', 'nid');
    }

    /**
     * Department yang di-mapping dengan pertanyaan ini
     */
    public function departments()
    {
        return $this->belongsToMany(mdepartment::class, 'maudit_deptquest', 'nid_quest', 'nid_dept');
    }
}
