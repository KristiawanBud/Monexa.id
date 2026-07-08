<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model {
    use HasUlids;
    protected $fillable = ['user_id','plan','status','trial_ends_at','starts_at','ends_at','amount','payment_method'];
    protected function casts(): array { return ['trial_ends_at'=>'datetime','starts_at'=>'datetime','ends_at'=>'datetime','amount'=>'decimal:2']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function isActive(): bool { return $this->status === 'active'; }
}
