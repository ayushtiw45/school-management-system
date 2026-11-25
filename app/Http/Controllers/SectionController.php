<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SectionController extends Controller
{
    public function forClass(SchoolClass $schoolClass)
    {
        return $schoolClass->sections()->get(['id', 'name']);
    }

    public function store(Request $request, SchoolClass $schoolClass)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20', Rule::unique('sections')->where('school_class_id', $schoolClass->id)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        $schoolClass->sections()->create($data);

        return back()->with('success', "Section {$data['name']} added to {$schoolClass->name}.");
    }

    public function update(Request $request, Section $section)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20', Rule::unique('sections')->where('school_class_id', $section->school_class_id)->ignore($section->id)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        $section->update($data);

        return back()->with('success', 'Section updated.');
    }

    public function destroy(Section $section)
    {
        if ($section->students()->exists()) {
            return back()->with('error', 'This section has students. Move them first.');
        }
        $section->delete();

        return back()->with('success', 'Section deleted.');
    }
}
