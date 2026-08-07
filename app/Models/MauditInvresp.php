<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MauditInvresp extends Model
{
    use HasFactory;

    protected $table = 'maudit_invresp';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'nid_audit',
        'nid_item',
        'nqty_stock',
        'nqty_real',
        'ndiff',
        'ndiff_over',
        'ndiff_under',
        'fna',
        'cket',
    ];

    protected $casts = [
        'nid_audit'   => 'integer',
        'nid_item'    => 'integer',
        'nqty_stock'  => 'decimal:2',
        'nqty_real'   => 'decimal:2',
        'ndiff'       => 'decimal:2',
        'ndiff_over'  => 'decimal:2',
        'ndiff_under' => 'decimal:2',
        'fna'         => 'boolean',
        'updated_at'  => 'datetime',
    ];

    /**
     * Relasi ke item audit.
     */
    public function item()
    {
        return $this->belongsTo(MauditItem::class, 'nid_item', 'nid');
    }
}
