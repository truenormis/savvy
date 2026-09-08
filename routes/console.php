<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('currencies:update')->daily();
Schedule::command('recurring:process')->daily();
Schedule::command('uploads:prune')->hourly();
