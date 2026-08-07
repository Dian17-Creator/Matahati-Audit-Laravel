<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MauditItemgrp extends Model
{
    use HasFactory;

    protected $table = 'maudit_itemgrp';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    protected $fillable = [
        'cnama',
        'cket',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Relasi ke items.
     */
    public function items()
    {
        return $this->hasMany(MauditItem::class, 'nid_grp', 'nid');
    }
}
