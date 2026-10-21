<?php

namespace App\Console\Commands;

use App\Actions\Flyers\RollFlyerStatuses;
use Illuminate\Console\Command;

class RollFlyersCommand extends Command
{
    protected $signature = 'flyers:roll';

    protected $description = 'Publish scheduled flyers whose start date has arrived and expire flyers that have ended';

    public function handle(RollFlyerStatuses $roll): int
    {
        $result = $roll->handle();

        $this->info(sprintf('Published %d flyer(s), expired %d flyer(s).', $result['published'], $result['expired']));

        return self::SUCCESS;
    }
}
