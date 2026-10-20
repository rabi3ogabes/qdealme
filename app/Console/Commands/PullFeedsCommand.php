<?php

namespace App\Console\Commands;

use App\Actions\Flyers\PullAllFeeds;
use Illuminate\Console\Command;

class PullFeedsCommand extends Command
{
    protected $signature = 'flyers:pull-feeds';

    protected $description = 'Pull the latest flyer from every approved store feed into drafts for review';

    public function handle(PullAllFeeds $pullAll): int
    {
        $summary = $pullAll->handle();

        $this->info(sprintf('New drafts: %d. Unchanged: %d. Failed: %d.', $summary['created'], $summary['unchanged'], count($summary['failed'])));

        foreach ($summary['failed'] as $store => $reason) {
            $this->warn("{$store}: {$reason}");
        }

        return self::SUCCESS;
    }
}
