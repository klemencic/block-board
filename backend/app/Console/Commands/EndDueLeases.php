<?php

namespace App\Console\Commands;

use App\Services\LeaseLifecycle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('leases:end-due')]
#[Description('Zaključi najeme, ki jim je potekel odpovedni rok ali datum konca')]
class EndDueLeases extends Command
{
    public function handle(LeaseLifecycle $lifecycle): int
    {
        $count = $lifecycle->endDueLeases();
        $this->info("Zaključenih najemov: {$count}");

        return self::SUCCESS;
    }
}
