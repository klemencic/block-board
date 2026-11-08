<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Building;
use App\Services\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcements) {}

    /**
     * Lastnik vidi vsa obvestila s stanjem dostave, najemnik obvestila za stavbo ali svoje nadstropje.
     * Razvrščena so od najnovejšega do najstarejšega (US-14, #135).
     */
    public function index(Request $request, Building $building): JsonResponse
    {
        $user = $request->user();
        $isLandlord = $user->isLandlordOf($building);
        abort_unless($isLandlord || $user->hasRole($building, Role::Tenant), 403);

        $query = $isLandlord
            ? Announcement::where('building_id', $building->id)
            : Announcement::visibleToTenant($user, $building->id);

        $announcements = $query->with('author')->latest('published_at')->latest('id')->get();

        return response()->json(['data' => $announcements->map(fn ($a) => $this->data($a, $isLandlord))]);
    }

    public function show(Request $request, Announcement $announcement): JsonResponse
    {
        Gate::authorize('view', $announcement);

        return response()->json(['data' => $this->data($announcement->load('author'), $request->user()->isLandlordOf($announcement->building_id))]);
    }

    /** Objava obvestila za celo stavbo ali eno nadstropje (US-14, #133, #134). */
    public function store(Request $request, Building $building): JsonResponse
    {
        Gate::authorize('manage', $building);

        $floors = $building->apartments()->distinct()->pluck('floor')->all();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'floor' => ['nullable', 'integer', Rule::in($floors)],
        ], [
            'floor.in' => 'Izbrano nadstropje ne pripada stavbi.',
        ], ['title' => 'naslov', 'body' => 'besedilo', 'floor' => 'nadstropje']);

        $announcement = $this->announcements->publish($building, $request->user(), $data);

        return response()->json(['data' => $this->data($announcement->load('author'), true)], 201);
    }

    private function data(Announcement $announcement, bool $forLandlord): array
    {
        $data = [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'floor' => $announcement->floor,
            'target_label' => $announcement->targetLabel(),
            'published_at' => $announcement->published_at,
            'author' => $announcement->author->name,
        ];

        if ($forLandlord) {
            $recipients = $announcement->recipients()->get();
            $data['delivery'] = [
                'recipients' => $recipients->unique('user_id')->count(),
                'sent' => $recipients->whereNotNull('mail_sent_at')->unique('user_id')->count(),
                'failed' => $recipients->whereNotNull('mail_failed_at')->unique('user_id')->count(),
            ];
        }

        return $data;
    }
}
