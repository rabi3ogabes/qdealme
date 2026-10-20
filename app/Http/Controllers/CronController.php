<?php

namespace App\Http\Controllers;

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
        $secret = (string) config('qdealme.cron_secret');

        abort_if($secret === '', 404);
        abort_unless(hash_equals('Bearer '.$secret, (string) $request->header('Authorization')), 401);

        return response()->json($roll->handle());
    }
}
