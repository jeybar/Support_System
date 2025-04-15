<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HardwareChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'component',
        'old_value',
        'new_value',
        'changed_by',
        'change_date',
        'notes',
    ];

    protected $casts = [
        'change_date' => 'date',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}