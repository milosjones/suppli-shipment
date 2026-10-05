<?php

namespace App\Models;

use App\Enums\ImportStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'user_id', 'file_name', 'file_path', 'file_hash', 'status', 'dry_run',
    'total_rows', 'imported_rows', 'skipped_rows', 'rejected_rows', 'blank_rows', 'warning_count',
    'shipments_created', 'containers_created', 'normalisations', 'issues', 'error_message', 'started_at', 'finished_at',
])]
class Import extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'dry_run' => 'boolean',
            'normalisations' => 'array',
            'issues' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
