<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class TransactionCategory extends Model {
    public $timestamps = false;
    protected $fillable = ['user_id','type','name','emoji','icon_path','is_system','sort_order'];
    protected function casts(): array { return ['is_system'=>'boolean']; }
    public function transactions(): HasMany { return $this->hasMany(Transaction::class, 'category_id'); }

    public function getIconUrlAttribute(): ?string
    {
        return $this->icon_path ? Storage::url($this->icon_path) : null;
    }

    public static function forUser(?string $userId): \Illuminate\Database\Eloquent\Collection {
        return static::where(function($q) use ($userId) {
            $q->whereNull('user_id')->orWhere('user_id', $userId);
        })->orderBy('type')->orderBy('sort_order')->get();
    }
}
