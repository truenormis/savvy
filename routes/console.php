<?php

use App\Support\AppTime;
use Illuminate\Support\Facades\Schedule;

Schedule::command('currencies:update')->daily();
Schedule::command('recurring:process')->daily()->timezone(AppTime::timezone());
Schedule::command('uploads:prune')->hourly();
