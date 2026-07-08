<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillPayment extends Model {
    use HasUlids;
    public $timestamps = false;
    protected $fillable = ['bill_id','wallet_id','transaction_id','amount_paid','paid_at','source','for_period','note'];
    protected function casts(): array { return ['amount_paid'=>'decimal:2','paid_at'=>'date']; }
    public function bill(): BelongsTo { return $this->belongsTo(Bill::class); }
    public function wallet(): BelongsTo { return $this->belongsTo(UserWallet::class); }
}
