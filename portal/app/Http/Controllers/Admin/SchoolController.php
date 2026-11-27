<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function index(Request $request)
    {
        $schools = User::where('is_admin', false)
            ->withCount(['downloads', 'licenses'])
            ->when($request->q, fn ($q, $t) => $q->where(fn ($q) => $q->where('school_name', 'like', "%$t%")->orWhere('name', 'like', "%$t%")
                ->orWhere('email', 'like', "%$t%")->orWhere('phone', 'like', "%$t%")->orWhere('city', 'like', "%$t%")))
            ->when($request->filter === 'licensed', fn ($q) => $q->has('licenses'))
            ->when($request->filter === 'not_licensed', fn ($q) => $q->doesntHave('licenses'))
            ->when($request->filter === 'not_downloaded', fn ($q) => $q->doesntHave('downloads'))
            ->latest()->paginate(40)->withQueryString();

        if ($request->query('export') === 'csv') {
            return response()->streamDownload(function () use ($request) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Joined', 'School', 'Contact', 'Phone', 'Email', 'City', 'State', 'Downloads', 'Licenses', 'Last login']);
                User::where('is_admin', false)->withCount(['downloads', 'licenses'])->orderBy('id')->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $u) {
                        fputcsv($out, [$u->created_at->format('Y-m-d'), $u->school_name, $u->name, $u->phone, $u->email, $u->city, $u->state, $u->downloads_count, $u->licenses_count, $u->last_login_at?->format('Y-m-d H:i')]);
                    }
                });
            }, 'tds-schools-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('admin.schools', compact('schools'));
    }

    public function show(User $user)
    {
        abort_if($user->is_admin, 404);
        $user->load(['licenses', 'payments', 'subscriptions' => fn ($q) => $q->where('status', '!=', 'created'), 'downloads' => fn ($q) => $q->latest('id')->take(20)]);

        return view('admin.school', ['school' => $user]);
    }
}
