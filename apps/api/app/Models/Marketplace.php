<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Marketplace extends Model
{
    protected $fillable = ['amazon_marketplace_id', 'country_code', 'name', 'currency', 'domain', 'region'];
}
