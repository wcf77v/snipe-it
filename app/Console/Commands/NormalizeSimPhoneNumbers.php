<?php

namespace App\Console\Commands;

use App\Services\SimPhoneMatcher;
use Illuminate\Console\Command;

class NormalizeSimPhoneNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'snipeit:sims-normalize-phones';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recompute the normalized (E.164) phone and mobile numbers of all users so SIM cards can be matched to them. Run after installing the SIM module, after changing the default SIM country, or after importing users without going through the app.';

    public function handle(): int
    {
        $changed = SimPhoneMatcher::normalizeAllUsers();

        $this->info("Updated normalized phone numbers for {$changed} user(s).");

        return self::SUCCESS;
    }
}
