<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SchedulesController extends Controller
{
    /**
     * Get all scheduled reports
     */
    public function index()
    {
        $schedules = Schedule::orderBy('created_at', 'desc')->get();

//        return response()->json([
//            'success' => true,
//            'data' => $scheduled-jobs
//        ]);

        return Inertia::render('jobs/Index',[
            'success' => true,
            'data' => $schedules
        ]);
    }

    /**
     * Get a specific scheduled report
     */
    public function show($id)
    {
        $schedule = Schedule::find($id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $schedule
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'frequency' => 'required|in:daily,weekly,bi-weekly,monthly,quarterly',
            'scheduled_time' => 'nullable|date_format:H:i',
            'is_enabled' => 'nullable|boolean',
        ]);

        $validated['scheduled_time'] = $validated['scheduled_time'] ?? '08:00';
        $validated['is_enabled'] = $validated['is_enabled'] ?? true;

        $schedule = Schedule::create($validated);

        Log::info('Scheduled report saved', [
            'frequency' => $validated['frequency'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Schedule created successfully',
            'data' => $schedule
        ]);
    }

    public function update(Request $request, $id)
    {
        $schedule = Schedule::find($id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule not found',
            ], 404);
        }

        $validated = $request->validate([
            'frequency' => 'required|in:daily,weekly,bi-weekly,monthly,quarterly',
            'scheduled_time' => 'nullable|date_format:H:i',
            'is_enabled' => 'nullable|boolean',
        ]);

        $validated['scheduled_time'] = $validated['scheduled_time'] ?? $schedule->scheduled_time;
        $validated['is_enabled'] = $validated['is_enabled'] ?? $schedule->is_enabled;

        $schedule->update($validated);

        Log::info('Scheduled report updated', ['id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Schedule updated successfully',
            'data' => $schedule
        ]);
    }

    public function destroy($id)
    {
        $schedule = Schedule::find($id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule not found',
            ], 404);
        }

        $schedule->delete();

        Log::info('Scheduled report deleted', ['id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Schedule deleted successfully',
        ]);
    }

    public function trigger()
    {
        Log::info('=== Cron trigger endpoint called ===');

        $reports = Schedule::where('is_enabled', true)->get();

        if ($reports->isEmpty()) {
            Log::warning('No active scheduled reports found');
            return response()->json([
                'success' => true,
                'message' => 'No active reports to process',
                'processed' => []
            ]);
        }

        $processed = [];
        foreach ($reports as $report) {
            $report->update(['last_run_at' => Carbon::now()]);

            $processed[] = [
                'frequency' => $report->frequency,
                'executed_at' => Carbon::now()
            ];

            Log::info('Processed scheduled report', [
                'frequency' => $report->frequency,
            ]);
        }

        Log::info("Cron execution completed. Processed {count} report(s)", [
            'count' => count($processed)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cron executed successfully',
            'processed' => $processed
        ]);
    }



    /**
     * Toggle schedule enabled status
     */
    public function toggleStatus($id)
    {
        $schedule = Schedule::find($id);

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule not found',
            ], 404);
        }

        $schedule->update(['is_enabled' => !$schedule->is_enabled]);

        Log::info('Scheduled report status toggled', [
            'id' => $id,
            'is_enabled' => $schedule->is_enabled,
        ]);

        return response()->json([
            'success' => true,
            'message' => $schedule->is_enabled ? 'Schedule enabled' : 'Schedule disabled',
            'data' => $schedule
        ]);
    }


}