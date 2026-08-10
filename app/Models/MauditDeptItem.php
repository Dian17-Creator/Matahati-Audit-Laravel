<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MauditDeptItem extends Model
{
    use HasFactory;

    protected $table = 'maudit_deptitem';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = [
        'nid_dept',
        'nid_item',
    ];

    protected $casts = [
        'nid_dept' => 'integer',
        'nid_item' => 'integer',
    ];

    public function department()
    {
        return $this->belongsTo(
            mdepartment::class,
            'nid_dept',
            'nid'
        );
    }

    public function item()
    {
        return $this->belongsTo(
            MauditItem::class,
            'nid_item',
            'nid'
        );
    }
}
