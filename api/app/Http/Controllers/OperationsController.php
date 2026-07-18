<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\JobOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

class OperationsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->canManageTeam(), 403, 'Cette vue est réservée aux responsables.');
        $organizationId = $request->user()->organization_id;
        $applications = Application::query()->whereHas('offer', fn ($query) => $query->where('organization_id', $organizationId));

        return response()->json([
            'status' => 'ok',
            'queues' => [
                'screening' => $this->queueSize('candidate-intake'),
                'scoring' => $this->queueSize('candidate-scoring'),
                'notifications' => $this->queueSize('notifications'),
            ],
            'processing_failures' => (clone $applications)->where('status', 'processing_failed')->count(),
            'notification_failures' => (clone $applications)->whereIn('notification_state', ['failed', 'attention_required'])->count(),
            'closing_campaigns' => JobOffer::query()->where('organization_id', $organizationId)->where('status', 'closing')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    private function queueSize(string $queue): ?int
    {
        try {
            return Queue::size($queue);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
