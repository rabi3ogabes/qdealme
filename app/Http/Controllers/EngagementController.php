<?php

namespace App\Http\Controllers;

use App\Enums\ReportReason;
use App\Models\Flyer;
use App\Models\Report;
use App\Models\Retailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EngagementController extends Controller
{
    public function toggleFollow(Request $request, Retailer $retailer): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $following = $user->follows()->whereKey($retailer->id)->exists();

        $following
            ? $user->follows()->detach($retailer->id)
            : $user->follows()->attach($retailer->id, ['created_at' => now()]);

        $message = $following ? __('engagement.unfollowed', ['store' => $retailer->name]) : __('engagement.followed', ['store' => $retailer->name]);

        if ($request->expectsJson()) {
            return response()->json(['following' => ! $following, 'message' => $message]);
        }

        return back()->with('status', $message);
    }

    public function toggleFavorite(Request $request, Flyer $flyer): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $saved = $user->favorites()->whereKey($flyer->id)->exists();

        $saved
            ? $user->favorites()->detach($flyer->id)
            : $user->favorites()->attach($flyer->id, ['created_at' => now()]);

        $message = $saved ? __('engagement.unsaved') : __('engagement.saved');

        if ($request->expectsJson()) {
            return response()->json(['saved' => ! $saved, 'message' => $message]);
        }

        return back()->with('status', $message);
    }

    public function report(Request $request, Flyer $flyer): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'in:'.implode(',', array_map(fn ($r) => $r->value, ReportReason::cases()))],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        Report::query()->create([
            'flyer_id' => $flyer->id,
            'user_id' => $request->user()?->id,
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'ip_hash' => hash('sha256', (string) $request->ip()),
        ]);

        return back()->with('status', __('engagement.reported'));
    }
}
