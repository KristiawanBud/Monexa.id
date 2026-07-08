<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model {
    use HasUlids;
    protected $fillable = ['user_id','category_id','period','amount','notif_threshold'];
    protected function casts(): array { return ['amount'=>'decimal:2']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function category(): BelongsTo { return $this->belongsTo(TransactionCategory::class); }
}
