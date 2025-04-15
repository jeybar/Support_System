<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelDefaultSpec extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_id',
        'spec_name',
        'spec_value',
    ];

    public function assetModel()
    {
        return $this->belongsTo(AssetModel::class, 'model_id');
    }
}