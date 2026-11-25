<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use Illuminate\Http\Request;

class AcademicSessionController extends Controller
{
    public function index()
    {
        $sessions = AcademicSession::withCount('students')->orderByDesc('start_date')->get();

        return view('sessions.index', compact('sessions'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $session = AcademicSession::create($data);
        if ($request->boolean('is_current') || AcademicSession::count() === 1) {
            $session->makeCurrent();
        }

        return back()->with('success', "Session {$session->name} created.");
    }

    public function update(Request $request, AcademicSession $session)
    {
        $session->update($this->validated($request, $session));

        return back()->with('success', 'Session updated.');
    }

    public function makeCurrent(AcademicSession $session)
    {
        $session->makeCurrent();

        return back()->with('success', "{$session->name} is now the current session.");
    }

    public function destroy(AcademicSession $session)
    {
        if ($session->students()->exists() || $session->is_current) {
            return back()->with('error', 'This session is in use and cannot be deleted.');
        }
        $session->delete();

        return back()->with('success', 'Session deleted.');
    }

    private function validated(Request $request, ?AcademicSession $session = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:20', 'unique:academic_sessions,name'.($session ? ','.$session->id : '')],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);
    }
}
