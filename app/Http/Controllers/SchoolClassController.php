<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use Illuminate\Http\Request;

class SchoolClassController extends Controller
{
    public function index()
    {
        $classes = SchoolClass::ordered()
            ->with(['sections' => fn ($q) => $q->withCount(['students' => fn ($q) => $q->where('status', 'active')])])
            ->withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->get();

        return view('classes.index', compact('classes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', \App\Support\Tenant::unique('school_classes', 'name')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        $data['sort_order'] ??= (int) SchoolClass::max('sort_order') + 1;
        SchoolClass::create($data);

        return back()->with('success', "Class {$data['name']} added.");
    }

    public function update(Request $request, SchoolClass $schoolClass)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', \App\Support\Tenant::unique('school_classes', 'name')->ignore($schoolClass->id)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ]);
        $schoolClass->update($data);

        return back()->with('success', 'Class updated.');
    }

    public function destroy(SchoolClass $schoolClass)
    {
        if ($schoolClass->students()->exists()) {
            return back()->with('error', 'This class has students and cannot be deleted.');
        }
        $schoolClass->delete();

        return back()->with('success', 'Class deleted.');
    }
}
