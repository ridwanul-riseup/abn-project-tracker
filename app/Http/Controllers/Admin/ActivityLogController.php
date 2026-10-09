<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ActivityFeed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request, ActivityFeed $feed): View
    {
        Gate::authorize('audit.view');

        $query = ActivityLog::query()->with('user');
        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($type = $request->string('subject_type')->toString()) {
            $query->where('subject_type', $type);
        }
        if ($action = $request->string('action')->trim()->toString()) {
            $query->where('action', 'like', addcslashes($action, '%_\\').'%');
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        $logs = $query->latest('id')->paginate(50)->withQueryString();
        // Entries about records the viewer cannot read are shown without label or details.
        $visibleIds = $feed->filter($this->user(), collect($logs->items()))->pluck('id')->flip();

        return view('admin.activity.index', [
            'logs' => $logs,
            'visibleIds' => $visibleIds,
            'users' => User::query()->orderBy('name')->pluck('name', 'id'),
            'types' => ActivityLog::query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type'),
        ]);
    }
}
