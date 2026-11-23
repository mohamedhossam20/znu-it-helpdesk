<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssetSummaryResource;
use App\Http\Resources\BuildingResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\RoomResource;
use App\Models\Building;
use App\Models\Category;
use App\Models\Floor;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Everything the "new request" form needs to fill its lists.
 */
class LookupController extends Controller
{
    private const RECENT_ROOMS = 5;

    /**
     * The requester's fixed office, their devices, the shared devices of
     * that office and the places they reported from recently.
     */
    public function context(Request $request): JsonResponse
    {
        $user = $request->user();
        $room = $user->defaultRoom?->load('floor.building');

        $myAssets = $user->assets()->with('type')->where('status', '!=', 'retired')->get();
        $roomAssets = $room
            ? $room->assets()->with('type')->shared()->where('status', '!=', 'retired')->get()
            : collect();

        $recentRoomIds = $user->requestedTickets()
            ->latest()
            ->limit(30)
            ->pluck('room_id')
            ->unique()
            ->reject(fn (int $id) => $id === $room?->id)
            ->take(self::RECENT_ROOMS)
            ->values();

        $recentRooms = Room::with('floor.building')
            ->whereKey($recentRoomIds)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (Room $r) => $recentRoomIds->search($r->id))
            ->values();

        return response()->json(['data' => [
            'default_room' => $room ? new RoomResource($room) : null,
            'my_assets' => AssetSummaryResource::collection($myAssets),
            'room_assets' => AssetSummaryResource::collection($roomAssets),
            'recent_rooms' => RoomResource::collection($recentRooms),
        ]]);
    }

    public function buildings(): AnonymousResourceCollection
    {
        return BuildingResource::collection(
            Building::query()
                ->where('is_active', true)
                ->with(['floors' => fn ($q) => $q->whereHas('rooms', fn (Builder $r) => $r->where('is_active', true))])
                ->orderBy('code')
                ->get(),
        );
    }

    public function rooms(Floor $floor): AnonymousResourceCollection
    {
        return RoomResource::collection(
            $floor->rooms()->with('floor.building')->where('is_active', true)->orderBy('code')->get(),
        );
    }

    public function roomAssets(Request $request, Room $room): AnonymousResourceCollection
    {
        abort_unless($room->is_active, 404);

        return AssetSummaryResource::collection(
            $room->assets()
                ->with('type')
                ->where('status', '!=', 'retired')
                // Someone else's personal PC in the same office is not mine to report.
                ->where(fn (Builder $q) => $q->whereNull('assigned_user_id')->orWhere('assigned_user_id', $request->user()->id))
                ->orderBy('asset_tag')
                ->get(),
        );
    }

    public function roomByQr(Request $request, string $token): RoomResource
    {
        $room = Room::query()
            ->where('qr_token', $token)
            ->where('is_active', true)
            ->with(['floor.building', 'assets' => fn ($q) => $q
                ->with('type')
                ->where('status', '!=', 'retired')
                ->where(fn (Builder $a) => $a->whereNull('assigned_user_id')->orWhere('assigned_user_id', $request->user()->id))
                ->orderBy('asset_tag')])
            ->firstOrFail();

        return new RoomResource($room);
    }

    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->with(['children' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('sort_order')
                ->get(),
        );
    }
}
