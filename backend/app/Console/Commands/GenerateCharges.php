<?php

namespace App\Console\Commands;

use App\Services\ChargeGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('charges:generate {--statements : Pošlji tudi mesečne povzetke}')]
#[Description('Ustvari manjkajoče obveznosti za najemnino v tekočem mesecu (idempotentno)')]
class GenerateCharges extends Command
{
    public function handle(ChargeGenerator $generator): int
    {
        $this->info('Novih obveznosti: '.$generator->generate());

        if ($this->option('statements')) {
            $this->info('Poslanih povzetkov: '.$generator->sendStatements());
        }

        return self::SUCCESS;
    }
}
