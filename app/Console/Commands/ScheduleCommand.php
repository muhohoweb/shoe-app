<?php

namespace App\Console\Commands;

use App\Models\Order;
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

    public function handle()
    {
        $schedules = Schedule::where('is_enabled', 1)->get();

        foreach ($schedules as $schedule) {
            $now = now();
            $scheduledTime = Carbon::parse($schedule->scheduled_time);

            if ($now->format('H') !== $scheduledTime->format('H')) {
                continue;
            }

            if ($this->shouldRun($schedule, $now)) {
                Order::query()
                    ->where(function($q) {
                        $q->where('status', 'completed')
                            ->orWhere('status', 'cancelled');
                    })
                    ->where('payment_status', 'paid')
                    ->delete();

                Log::info("Schedule {$schedule->id} executed successfully");
                $schedule->update(['last_run_at' => $now]);
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
            'daily' => $now->diffInDays($lastRun) >= 1,
            'weekly' => $now->diffInWeeks($lastRun) >= 1,
            'monthly' => $now->diffInMonths($lastRun) >= 1,
            default => false,
        };
    }
}
