<?php

namespace App\Console\Commands;

use App\Services\Doctor\PresenceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('presence:close-stale')]
#[Description('Close presence sessions whose heartbeat has stopped')]
class CloseStalePresence extends Command
{
    public function handle(PresenceService $presence): int
    {
        $this->components->info('Closed '.$presence->closeStale().' stale presence sessions.');

        return self::SUCCESS;
    }
}
