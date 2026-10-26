<?php

use Illuminate\Support\Facades\Schedule;

// Abandoned timed sets are closed lazily on next visit; prune old framework rows.
Schedule::command('queue:prune-failed --hours=168')->daily();
