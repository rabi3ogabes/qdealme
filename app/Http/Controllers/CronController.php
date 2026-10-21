<?php

namespace App\Http\Controllers;

use App\Actions\Flyers\PullAllFeeds;
use App\Actions\Flyers\RollFlyerStatuses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Entry point for hosts without a long-running scheduler (Vercel Cron).
 * Vercel sends "Authorization: Bearer <CRON_SECRET>"; anything else is refused.
 */
class CronController extends Controller
{
    public function rollFlyers(Request $request, RollFlyerStatuses $roll): JsonResponse
    {
        $this->authorizeCron($request);

        return response()->json($roll->handle());
    }

    /**
     * Daily pull of every approved store feed into drafts for review.
     */
    public function pullFeeds(Request $request, PullAllFeeds $pullAll): JsonResponse
    {
        $this->authorizeCron($request);

        $summary = $pullAll->handle();

        return response()->json([
            'created' => $summary['created'],
            'unchanged' => $summary['unchanged'],
            'failed' => count($summary['failed']),
        ]);
    }

    private function authorizeCron(Request $request): void
    {
        $secret = (string) config('qdealme.cron_secret');

        abort_if($secret === '', 404);
        abort_unless(hash_equals('Bearer '.$secret, (string) $request->header('Authorization')), 401);
    }
}
