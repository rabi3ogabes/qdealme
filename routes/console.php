<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('flyers:roll')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('queue:prune-batches')->daily();
