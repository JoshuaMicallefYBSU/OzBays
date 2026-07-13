<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Models\User;
use App\Notifications\SystemNotification;
use Spatie\Permission\Models\Role;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    // Marks a notification read, then sends the user to its URL, its detail
    // view (if it has a body), or the notification list as a fallback.
    public function read(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        if (!empty($notification->data['url'])) {
            return redirect($notification->data['url']);
        }

        if (!empty($notification->data['body'])) {
            return redirect()->route('notifications.show', $notification->id);
        }

        return redirect()->route('notifications.index');
    }

    // Detail view for notifications with a body but no click-through URL.
    public function show(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        return view('notifications.show', compact('notification'));
    }

    // AJAX-only: marks a single notification read in place, without navigating.
    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        return response()->json(['status' => 'ok']);
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    // -- Admin: view sent notifications, grouped into the batch they were sent as --

    public function adminIndex()
    {
        // All recipients of one send share identical `data`, so grouping by
        // its extracted fields (plus a minute bucket, in case two unrelated
        // sends happen to share the exact same text) reconstructs the batch
        // without needing a dedicated batch id column.
        $batches = DB::table('notifications')
            ->select([
                'type',
                'data->title as title',
                'data->message as message',
                'data->url as url',
                'data->icon as icon',
                'data->sender as sender',
            ])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as bucket")
            ->selectRaw('MIN(created_at) as sent_at')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN read_at IS NOT NULL THEN 1 ELSE 0 END) as read_count')
            ->groupBy('type', 'data->title', 'data->message', 'data->url', 'data->icon', 'data->sender')
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:%i')")
            ->orderByDesc('sent_at')
            ->paginate(20);

        return view('dashboard.admin.notifications.index', compact('batches'));
    }

    // Recipients of a single batch (identified by the same fields it's grouped
    // by in adminIndex, since there's no dedicated batch id), split into who's
    // seen it and who hasn't.
    public function recipients(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string',
            'title' => 'required|string',
            'message' => 'required|string',
            'url' => 'nullable|string',
            'icon' => 'required|string',
            'sender' => 'nullable|string',
            'bucket' => 'required|string',
        ]);
        $data += ['url' => null, 'sender' => null];

        $query = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('type', $data['type'])
            ->where('data->title', $data['title'])
            ->where('data->message', $data['message'])
            ->where('data->icon', $data['icon'])
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') = ?", [$data['bucket']]);

        $data['url'] ? $query->where('data->url', $data['url']) : $query->whereNull('data->url');
        $data['sender'] ? $query->where('data->sender', $data['sender']) : $query->whereNull('data->sender');

        $readAtByUserId = $query->pluck('read_at', 'notifiable_id');

        $recipients = User::whereIn('id', $readAtByUserId->keys())
            ->orderBy('fname')
            ->orderBy('lname')
            ->get()
            ->map(fn ($user) => (object) [
                'user' => $user,
                'read_at' => $readAtByUserId[$user->id],
            ])
            ->sortBy(fn ($r) => $r->read_at ? 1 : 0)
            ->values();

        return view('dashboard.admin.notifications.recipients', compact('recipients') + $data);
    }

    // -- Admin: compose & send a notification to one or more roles --

    public function create()
    {
        $roles = Role::orderBy('name')->get();

        // Per-role user id lists let the form compute an exact (non-duplicated)
        // recipient count client-side as roles are checked/unchecked.
        $roleUserIds = $roles->mapWithKeys(fn ($role) => [
            $role->id => User::where('deleted', false)
                ->whereHas('roles', fn ($query) => $query->where('roles.id', $role->id))
                ->pluck('id'),
        ]);

        $allUserCount = User::where('deleted', false)->count();

        return view('dashboard.admin.notifications.create', compact('roles', 'roleUserIds', 'allUserCount'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'recipient_mode' => 'required|in:all,roles',
            'roles' => 'required_if:recipient_mode,roles|array',
            'roles.*' => 'exists:roles,id',
            'sender_type' => 'required|in:name_role,system',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:255',
            'body' => 'nullable|string',
            'url' => 'nullable|url',
            'icon' => 'nullable|string|max:50',
        ]);

        $sender = $data['sender_type'] === 'system'
            ? 'System'
            : $request->user()->fullName('FL') . ', ' . $request->user()->highestRole()->name;

        $recipients = User::where('deleted', false)
            ->when(
                $data['recipient_mode'] === 'roles',
                fn ($query) => $query->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $data['roles']))
            )
            ->get();

        Notification::send($recipients, new SystemNotification(
            title: $data['title'],
            message: $data['message'],
            url: $data['url'] ?? null,
            icon: $data['icon'] ?? 'fa-bell',
            sender: $sender,
            body: $data['body'] ?? null,
        ));

        return redirect()->route('dashboard.admin.notifications.create')->with('success', "Notification sent to {$recipients->count()} user(s).");
    }
}
