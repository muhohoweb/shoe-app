<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;


class ScheduleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:schedule-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $schedules = Schedule::where('is_enabled', 1)->get();

        foreach ($schedules as $schedule) {
            $now = Carbon::now();
            $scheduledTime = Carbon::parse($schedule->scheduled_time);



            if ($now->format('H:i') === $scheduledTime->format('H:i')) {
                if ($this->shouldRun($schedule, $now)) {
                    Log::info("Schedule {$schedule->id} executed successfully");
                    $schedule->update(['last_run_at' => $now]);
                }
            }else{
                Log::info("Not time to run ".$scheduledTime);
            }
        }
    }

    private function shouldRun($schedule, $now)
    {
        if (!$schedule->last_run_at) {
            return true;
        }

        $lastRun = Carbon::parse($schedule->last_run_at);

        return match($schedule->frequency) {
            'daily' => $lastRun->diffInDays($now) >= 1,
            'weekly' => $lastRun->diffInWeeks($now) >= 1,
            'monthly' => $lastRun->diffInMonths($now) >= 1,
            default => false,
        };
    }
}
