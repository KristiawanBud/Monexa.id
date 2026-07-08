<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingDeposit extends Model {
    use HasUlids;
    public $timestamps = false;
    protected $fillable = ['saving_goal_id','wallet_id','amount','note','deposited_at'];
    protected function casts(): array { return ['amount'=>'decimal:2','deposited_at'=>'date']; }
    public function goal(): BelongsTo { return $this->belongsTo(SavingGoal::class, 'saving_goal_id'); }
    public function wallet(): BelongsTo { return $this->belongsTo(UserWallet::class); }
}
