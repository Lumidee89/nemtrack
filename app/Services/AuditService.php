<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditService
{
    public static function record(Request $request, string $action, object $entity, ?array $values = null): void
    {
        DB::table('audit_logs')->insert([
            'organization_id' => $request->user()?->organization_id,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'entity_type' => class_basename($entity),
            'entity_id' => $entity->id ?? null,
            'new_values' => $values ? json_encode($values) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($request->user()) {
            $label = str_replace('.', ' ', $action);
            $recipientIds = DB::table('users')->where('role', 'super_admin')->pluck('id');
            if (str_starts_with($action, 'panic.')) {
                $recipientIds = $recipientIds->merge(DB::table('users')->where('organization_id', $entity->organization_id ?? $request->user()->organization_id)->whereIn('role', ['organization_admin', 'security_officer'])->pluck('id'));
            }
            $recipientIds = $recipientIds->push($request->user()->id)->unique();
            foreach ($recipientIds as $recipientId) {
                DB::table('app_notifications')->insert([
                    'user_id' => $recipientId,
                    'organization_id' => $entity->organization_id ?? $request->user()->organization_id,
                    'type' => str_starts_with($action, 'panic.') ? 'critical' : 'activity',
                    'title' => ucfirst($label),
                    'message' => str_starts_with($action, 'panic.') ? 'Emergency at '.($entity->location_name ?? 'an access point').'. Open the response console immediately.' : $request->user()->name.' completed this operation; it was added to the security audit trail.',
                    'action_url' => str_starts_with($action, 'panic.') ? '/portal/pbs' : '/portal/audit-logs',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }
}
