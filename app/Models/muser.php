<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Csalary;

class muser extends Authenticatable
{
    use HasFactory;
    protected $table = 'muser';
    protected $primaryKey = 'nid';
    public $timestamps = false;
    protected $fillable = [
        'cemail',
        'cmailaddress',
        'cphone',
        'cktp',
        'cname',
        'cfullname',
        'caccnumber',
        'cpassword',
        'dtanggalmasuk',
        'dcreated',
        'fadmin',
        'fsuper',
        'fhrd',
        'factive',
        'fsenior',
        'niddept',
        'niddeptpayroll',
        'cdeptname',
        'finger_id',
        'rekening_id',
        'bank',
        'fface_approved',
        'fnotif',
        'ccompany',
        'faudit',
    ];
    protected $hidden = [
        'cpassword',
    ];
    protected $casts = [
        'dcreated' => 'datetime:Y-m-d H:i:s',
        'fadmin' => 'boolean',
        'fsuper' => 'boolean',
        'fhrd' => 'boolean',
        'fsenior' => 'boolean',
        'finger_id' => 'integer',
        'factive' => 'boolean',
        'fnotif' => 'boolean',
        'fface_approved' => 'boolean',
        'faudit' => 'boolean',
    ];
    public function getAuthPassword()
    {
        return $this->cpassword;
    }
    public function isSuperAdmin()
    {
        return $this->fsuper == 1;
    }
    public function isAdmin()
    {
        return $this->fadmin == 1;
    }
    public function isHrd()
    {
        return $this->fhrd == 1;
    }
    public function isSenior()
    {
        return $this->fsenior == 1;
    }
    public function department()
    {
        return $this->belongsTo(mdepartment::class, 'niddept', 'nid');
    }
}
