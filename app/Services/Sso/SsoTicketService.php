<?php

namespace App\Services\Sso;

use App\DTOs\ProvisionResult;
use App\Models\SsoLoginTicket;
use Illuminate\Support\Str;

class SsoTicketService
{
    public function issue(ProvisionResult $result): string
    {
        $ticket = Str::random(64);

        SsoLoginTicket::create([
            'ticket' => $ticket,
            'user_id' => $result->user->id,
            'requires_2fa' => $result->requiresTwoFactor,
            'expires_at' => now()->addSeconds(config('sso.ticket_ttl', 120)),
        ]);

        return $ticket;
    }

    /**
     * Atomically consume a ticket exactly once. Returns the row or null.
     */
    public function consume(string $ticket): ?SsoLoginTicket
    {
        $affected = SsoLoginTicket::where('ticket', $ticket)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['consumed_at' => now()]);

        if ($affected !== 1) {
            return null;
        }

        return SsoLoginTicket::where('ticket', $ticket)->first();
    }

    public function purgeExpired(): int
    {
        return SsoLoginTicket::where('expires_at', '<', now())->delete();
    }
}
