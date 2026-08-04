<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class mdepartment extends Model
{
    use HasFactory;

    protected $table = 'mdepartment';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    protected $fillable = [
        'cname',
        'dcreated',
        'ccompany',
    ];

    protected $casts = [
        'dcreated' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Semua user yang berada pada department ini
     */
    public function users()
    {
        return $this->hasMany(muser::class, 'niddept', 'nid');
    }

    /**
     * Semua user yang payroll department-nya di sini
     */
    public function payrollUsers()
    {
        return $this->hasMany(muser::class, 'niddeptpayroll', 'nid');
    }

    /**
     * Semua audit pada department ini
     */
    public function audits()
    {
        return $this->hasMany(MauditAudit::class, 'nid_dept', 'nid');
    }

    /**
     * Pertanyaan audit yang di-mapping ke department ini
     */
    public function auditQuestions()
    {
        return $this->belongsToMany(MauditQuest::class, 'maudit_deptquest', 'nid_dept', 'nid_quest');
    }
}
