<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuditLogger
{
    /** Metadata must contain only explicitly selected non-sensitive fields.
     * @param  array<string,mixed>  $metadata
     */
    public function record(string $action, Model $subject, User $actor, array $metadata = []): void
    {
        DB::table('audit_logs')->insert(['actor_id' => $actor->id, 'action' => $action, 'subject_type' => $subject->getTable(),
            'subject_id' => $subject->getKey(), 'request_id' => request()->attributes->get('request_id'),
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
