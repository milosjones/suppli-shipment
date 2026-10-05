<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('tracking:sync')->hourly()->withoutOverlapping();
Schedule::command('webhooks:deliver-due')->everyFiveMinutes()->withoutOverlapping();
