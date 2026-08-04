<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MauditKat extends Model
{
    use HasFactory;

    protected $table = 'maudit_kat';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    protected $fillable = [
        'cnama',
        'cket',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];


    //Daftar pertanyaan dalam kategori
    public function questions()
    {
        return $this->hasMany(MauditQuest::class, 'nid_kat', 'nid');
    }
}
