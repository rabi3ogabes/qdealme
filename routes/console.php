<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('flyers:roll')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('queue:prune-batches')->daily();
Schedule::command('flyers:pull-feeds')->dailyAt('06:00')->timezone(config('qdealme.timezone'))->withoutOverlapping();
