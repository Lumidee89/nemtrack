<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Visitor;
use App\Models\VisitorInvitation;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VisitorController extends Controller
{
    public function index(Request $request)
    {
        $items = Visitor::where('organization_id', $request->user()->organization_id)->latest()->paginate(20);
        return response()->json(['success' => true, 'message' => 'Visitors retrieved.', 'data' => $items]);
    }

    public function invitations(Request $request)
    {
        $items = VisitorInvitation::with(['visitor', 'host'])->where('organization_id', $request->user()->organization_id)->latest()->paginate(20);
        return response()->json(['success' => true, 'message' => 'Invitations retrieved.', 'data' => $items]);
    }

    public function invite(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:30'], 'email' => ['nullable', 'email'], 'purpose' => ['required', 'string', 'max:160'],
            'valid_from' => ['required', 'date'], 'valid_until' => ['required', 'date', 'after:valid_from'],
            'entry_type' => ['nullable', 'in:single,multiple,recurring'], 'maximum_entries' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $invitation = DB::transaction(function () use ($request, $data) {
            $visitor = Visitor::firstOrCreate(
                ['organization_id' => $request->user()->organization_id, 'phone' => $data['phone']],
                collect($data)->only(['first_name', 'last_name', 'email'])->all(),
            );
            abort_if($visitor->watchlisted, 403, 'Visitor is on the organization watchlist.');
            return VisitorInvitation::create([
                'uuid' => (string) Str::uuid(), 'organization_id' => $request->user()->organization_id,
                'visitor_id' => $visitor->id, 'host_user_id' => $request->user()->id,
                'invitation_code' => strtoupper(Str::random(6)), 'qr_token' => hash('sha256', Str::random(64)),
                'purpose' => $data['purpose'], 'valid_from' => $data['valid_from'], 'valid_until' => $data['valid_until'],
                'entry_type' => $data['entry_type'] ?? 'single', 'maximum_entries' => $data['maximum_entries'] ?? 1, 'status' => 'active',
            ]);
        });
        AuditService::record($request, 'visitor.invitation.created', $invitation, $invitation->toArray());
        return response()->json(['success' => true, 'message' => 'Visitor invitation created successfully.', 'data' => ['invitation' => $invitation->load('visitor')]], 201);
    }

    public function verify(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'string']]);
        $invitation = VisitorInvitation::with('visitor')->where('organization_id', $request->user()->organization_id)
            ->where(fn ($q) => $q->where('qr_token', $data['token'])->orWhere('invitation_code', strtoupper($data['token'])))->first();
        if (! $invitation) return $this->invalid('Visitor pass was not found.', 'VISITOR_PASS_INVALID', 404);
        if ($invitation->status === 'revoked') return $this->invalid('Visitor pass was revoked.', 'VISITOR_PASS_REVOKED');
        if ($invitation->valid_until->isPast()) return $this->invalid('Visitor pass has expired.', 'VISITOR_PASS_EXPIRED');
        if ($invitation->entry_count >= $invitation->maximum_entries) return $this->invalid('Visitor pass has already been used.', 'VISITOR_PASS_USED');
        return response()->json(['success' => true, 'message' => 'Visitor pass is valid.', 'data' => ['invitation' => $invitation]]);
    }

    private function invalid(string $message, string $code, int $status = 422) { return response()->json(compact('message', 'code') + ['success' => false, 'errors' => null], $status); }
}
