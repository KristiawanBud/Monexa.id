<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportSession extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id', 'filename', 'file_path', 'source_app',
        'status', 'preview_data', 'column_mapping',
        'total_rows', 'imported_rows', 'skipped_rows', 'error_rows',
        'errors', 'ai_provider', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'preview_data'   => 'array',
            'column_mapping' => 'array',
            'errors'         => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getSourceLabelAttribute(): string
    {
        return match($this->source_app) {
            'bca'           => 'BCA Mobile',
            'mandiri'       => 'Mandiri Online',
            'bni'           => 'BNI Mobile',
            'bri'           => 'BRImo',
            'jenius'        => 'Jenius',
            'gopay'         => 'GoPay',
            'ovo'           => 'OVO',
            'dana'          => 'DANA',
            'shopeepay'     => 'ShopeePay',
            'generic_csv'   => 'CSV Umum',
            'generic_excel' => 'Excel Umum',
            'ai_detect'     => 'AI Auto-detect',
            default         => $this->source_app,
        };
    }
}
