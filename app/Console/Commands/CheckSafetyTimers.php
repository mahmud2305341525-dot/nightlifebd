<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\SafetyTimer;
use App\Services\AlertService;
use Illuminate\Console\Command;

class CheckSafetyTimers extends Command
{
    protected $signature = 'safety:check-timers';

    protected $description = 'Alert emergency contacts when a safety timer expired without confirmation (dead-man switch)';

    public function handle(AlertService $alerts): int
    {
        $due = SafetyTimer::with('user.emergencyContacts')
            ->whereNull('confirmed_at')->whereNull('alerted_at')
            ->where('due_at', '<=', now())->get();

        foreach ($due as $t) {
            $msg = "{$t->user->name} set a safety timer for ".$t->due_at->format('h:i A').' and has not confirmed they are safe.'
                .($t->note ? " Note: {$t->note}." : '')
                .' Please call them now. In an emergency dial 999.';
            $sent = $alerts->notifyContacts($t->user, 'NightLifeBD safety alert', $msg);
            $t->update(['alerted_at' => now()]);
            AuditLog::record($t->user_id, 'timer.alert', [
                'timer_id' => $t->id,
                'contacts' => $sent['contacts'],
                'email' => $sent['email'],
                'telegram' => $sent['telegram'],
                'is_simulation' => $sent['is_simulation'],
            ]);
            $this->line("Alerted contacts of user #{$t->user_id}");
        }
        $this->info($due->count().' timer(s) processed.');

        return self::SUCCESS;
    }
}
