<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $theme
 */

class UserProfile extends Model
{
    use HasUlids;

    protected $fillable = ['user_id', 'currency', 'timezone', 'notif_wa_enabled', 'monthly_report_enabled', 'monthly_report_day', 'saham_enabled', 'app_logo_url', 'app_name', 'theme'];

    protected function casts(): array
    {
        return ['notif_wa_enabled' => 'boolean', 'monthly_report_enabled' => 'boolean', 'saham_enabled' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
