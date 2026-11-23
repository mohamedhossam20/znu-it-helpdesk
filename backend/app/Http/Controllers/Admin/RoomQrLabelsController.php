<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\RoomQrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Printable A4 sheet of room QR labels, opened in a new tab from the admin
 * panel. Either ?rooms[]=… (selected rooms) or ?building=… (whole building).
 */
class RoomQrLabelsController extends Controller
{
    private const MAX_LABELS = 500;

    public function __invoke(Request $request, RoomQrCode $qr): View
    {
        Gate::authorize('viewAny', Room::class);

        $validated = $request->validate([
            'rooms' => ['required_without:building', 'array', 'max:'.self::MAX_LABELS],
            'rooms.*' => ['integer'],
            'building' => ['required_without:rooms', 'integer'],
        ]);

        $rooms = Room::query()
            ->with('floor.building')
            ->where('is_active', true)
            ->when($validated['rooms'] ?? null, fn ($q, $ids) => $q->whereKey($ids))
            ->when($validated['building'] ?? null, fn ($q, $buildingId) => $q->whereRelation('floor', 'building_id', $buildingId))
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->orderBy('floors.building_id')
            ->orderBy('floors.level')
            ->orderBy('rooms.code')
            ->select('rooms.*')
            ->limit(self::MAX_LABELS)
            ->get();

        abort_if($rooms->isEmpty(), 404, __('No active rooms to print.'));

        return view('admin.room-qr-labels', [
            'labels' => $rooms->map(fn (Room $room) => [
                'room' => $room,
                'svg' => $qr->svg($room),
                'url' => $qr->url($room),
            ]),
            'organization' => config('helpdesk.organization_name'),
        ]);
    }
}
