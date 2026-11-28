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

    /** Add one or more sections: "C", "C,D,E" or a range "A-F". Existing names are skipped. */
    public function store(Request $request, SchoolClass $schoolClass)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        $added = $this->addSections($schoolClass, self::parseNames($data['name']), $data['capacity'] ?? null);

        return back()->with($added ? 'success' : 'error', $added
            ? 'Section '.implode(', ', $added)." added to {$schoolClass->name}."
            : 'These sections already exist.');
    }

    /** Same sections for many classes at once. */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'names' => ['required', 'string', 'max:60'],
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => [\App\Support\Tenant::exists('school_classes')],
        ], ['class_ids.required' => 'Tick at least one class.']);
        $names = self::parseNames($data['names']);
        $count = 0;
        foreach (SchoolClass::whereIn('id', $data['class_ids'])->get() as $class) {
            $count += count($this->addSections($class, $names, null));
        }

        return back()->with('success', "{$count} section(s) added.");
    }

    public static function parseNames(string $input): array
    {
        $input = strtoupper(trim($input));
        if (preg_match('/^([A-Z])\s*(?:-|TO)\s*([A-Z])$/', $input, $m) && $m[1] <= $m[2]) {
            return range($m[1], $m[2]);
        }

        return array_values(array_unique(array_filter(array_map(fn ($n) => mb_substr(trim($n), 0, 20), preg_split('/[,\s]+/', $input)))));
    }

    private function addSections(SchoolClass $class, array $names, ?int $capacity): array
    {
        $existing = $class->sections()->pluck('name')->map(fn ($n) => strtoupper($n))->all();
        $added = [];
        foreach (array_slice($names, 0, 26) as $name) {
            if (! in_array(strtoupper($name), $existing, true)) {
                $class->sections()->create(['name' => $name, 'capacity' => $capacity]);
                $added[] = $name;
                $existing[] = strtoupper($name);
            }
        }

        return $added;
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
