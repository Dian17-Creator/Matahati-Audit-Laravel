<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MauditDeptQuest extends Model
{
    use HasFactory;

    protected $table = 'maudit_deptquests';

    protected $primaryKey = 'nid_dept';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'nid_dept',
    ];

    public function department()
    {
        return $this->belongsTo(mdepartment::class, 'nid_dept', 'nid');
    }
}
