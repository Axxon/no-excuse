<?php

namespace App\Console\Commands;

use App\Models\Application;
use Illuminate\Console\Command;

class ReconcileCandidateNotifications extends Command
{
    protected $signature = 'notifications:reconcile';

    protected $description = 'Flag interrupted candidate email deliveries for explicit human review';

    public function handle(): int
    {
        $count = Application::query()
            ->where('notification_state', 'sending')
            ->where('notification_attempted_at', '<=', now()->subMinutes(15))
            ->update([
                'notification_state' => 'attention_required',
                'notification_error' => 'Livraison interrompue : vérifiez le fournisseur avant une relance manuelle.',
            ]);
        $this->info($count.' interrupted notification(s) flagged.');

        return self::SUCCESS;
    }
}
