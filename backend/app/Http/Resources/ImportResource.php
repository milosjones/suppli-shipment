<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportResource extends JsonResource
{
    private bool $includeReport = false;

    public function withReport(): static
    {
        $this->includeReport = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'file_hash' => $this->file_hash,
            'status' => $this->status,
            'dry_run' => $this->dry_run,
            'total_rows' => $this->total_rows,
            'imported_rows' => $this->imported_rows,
            'skipped_rows' => $this->skipped_rows,
            'rejected_rows' => $this->rejected_rows,
            'blank_rows' => $this->blank_rows,
            'warning_count' => $this->warning_count,
            'shipments_created' => $this->shipments_created,
            'containers_created' => $this->containers_created,
            'error_message' => $this->error_message,
            'normalisations' => $this->when($this->includeReport, fn () => $this->normalisations ?? []),
            'issues' => $this->when($this->includeReport, fn () => $this->issues ?? []),
            'user' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'duration_ms' => $this->started_at && $this->finished_at
                ? (int) $this->started_at->diffInMilliseconds($this->finished_at)
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
