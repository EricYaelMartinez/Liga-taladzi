<?php

namespace App\Domain\Finance\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceReceiptSequence extends Model
{
    protected $primaryKey = 'league_id';
    public $incrementing = false;
    protected $fillable = ['league_id', 'next_number'];
    protected function casts(): array { return ['next_number' => 'integer']; }
}
