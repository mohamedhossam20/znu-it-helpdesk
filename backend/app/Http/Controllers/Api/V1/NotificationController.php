<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $page = $user->notifications()->latest()->paginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'kind' => $n->data['kind'] ?? null,
                'title' => $n->data['title'] ?? '',
                'body' => $n->data['body'] ?? '',
                'ticket_number' => $n->data['ticket_number'] ?? null,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ]),
            'meta' => [
                'unread_count' => $user->unreadNotifications()->count(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function read(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['sometimes', 'array', 'max:100'],
            'ids.*' => ['uuid'],
            'all' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $query = $user->unreadNotifications();

        if (! $request->boolean('all')) {
            $query->whereIn('id', $data['ids'] ?? []);
        }

        $query->update(['read_at' => now()]);

        return response()->json(['meta' => ['unread_count' => $user->unreadNotifications()->count()]]);
    }
}
