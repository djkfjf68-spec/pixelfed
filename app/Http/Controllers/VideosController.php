<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\StatusService;
use App\Status;
use Auth;

class VideosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        return view('videos.index');
    }

    public function api(Request $request)
    {
        $user = Auth::user();
        $maxId = $request->input('max_id');
        $limit = $request->input('limit', 10);

        $query = Status::whereHas('media', function($q) {
                $q->where('mime', 'like', 'video/%');
            })
            ->whereNull('in_reply_to_id')
            ->whereNull('reblog_of_id')
            ->whereIn('type', ['photo', 'video'])
            ->whereIn('scope', ['public'])
            ->where('created_at', '>', now()->subMonths(3));

        if ($maxId) {
            $query->where('id', '<', $maxId);
        }

        $videos = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($status) {
                return StatusService::get($status->id);
            })
            ->filter()
            ->values();

        return response()->json($videos);
    }
}