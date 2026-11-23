<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('tickets:auto-close')->hourly()->withoutOverlapping();
Schedule::command('tickets:notify-overdue')->everyFiveMinutes()->withoutOverlapping();
