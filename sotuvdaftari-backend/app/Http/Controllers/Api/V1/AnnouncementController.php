<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin yuborgan bildirishnomalar (e'lonlar) */
class AnnouncementController extends Controller
{
    use RespondsWithJson;

    /** GET /announcements — oxirgi 50 ta, `is_read` va `unread_count` bilan */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $readIds = DB::table('announcement_reads')->where('user_id', $user->id)->pluck('announcement_id')->all();

        $items = Announcement::visibleTo($user)->latest('id')->limit(50)->get()
            ->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'is_read' => in_array($a->id, $readIds, true),
                'created_at' => $a->created_at?->toIso8601String(),
            ])->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => ['unread_count' => $items->where('is_read', false)->count()],
        ]);
    }

    /** POST /announcements/{id}/read */
    public function read(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        Announcement::visibleTo($user)->findOrFail($id);

        DB::table('announcement_reads')->insertOrIgnore([
            'announcement_id' => $id,
            'user_id' => $user->id,
            'read_at' => now(),
        ]);

        return $this->success();
    }

    /** POST /announcements/read-all */
    public function readAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = Announcement::visibleTo($user)->pluck('id')->map(fn ($id) => [
            'announcement_id' => $id,
            'user_id' => $user->id,
            'read_at' => now(),
        ])->all();

        DB::table('announcement_reads')->insertOrIgnore($rows);

        return $this->success();
    }
}
