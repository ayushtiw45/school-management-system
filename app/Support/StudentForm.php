<?php

namespace App\Support;

use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Shared validation + saving for the admission form and the student edit form. */
class StudentForm
{
    public static function rules(bool $public = false): array
    {
        $rules = [
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'dob' => ['required', 'date', 'before:today'],
            'blood_group' => ['nullable', 'string', 'max:5'],
            'religion' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'father_name' => ['required', 'string', 'max:100'],
            'father_phone' => ['required', 'string', 'max:20'],
            'father_occupation' => ['nullable', 'string', 'max:100'],
            'mother_name' => ['nullable', 'string', 'max:100'],
            'mother_phone' => ['nullable', 'string', 'max:20'],
            'guardian_name' => ['nullable', 'string', 'max:100'],
            'guardian_relation' => ['nullable', 'string', 'max:50'],
            'guardian_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'previous_school' => ['nullable', 'string', 'max:150'],
            'previous_class' => ['nullable', 'string', 'max:30'],
            'tc_number' => ['nullable', 'string', 'max:50'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*.title' => ['nullable', 'string', 'max:100'],
            'documents.*.file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ];

        if (! $public) {
            $rules += [
                'admission_date' => ['required', 'date'],
                'roll_no' => ['nullable', 'string', 'max:20'],
                'concession_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'remarks' => ['nullable', 'string', 'max:1000'],
            ];
        }

        return $rules;
    }

    public static function validate(Request $request, bool $public = false): array
    {
        return $request->validate(self::rules($public));
    }

    /** Section must belong to the chosen class. */
    public static function checkSection(Validator $v, Request $request): void
    {
        if ($request->filled('section_id')) {
            $ok = Section::where('id', $request->section_id)->where('school_class_id', $request->school_class_id)->exists();
            if (! $ok) {
                $v->errors()->add('section_id', 'This section does not belong to the selected class.');
            }
        }
    }

    public static function save(Student $student, array $data, Request $request): Student
    {
        $files = ['photo', 'documents'];
        $student->fill(collect($data)->except($files)->all());
        $student->concession_percent = $data['concession_percent'] ?? $student->concession_percent ?? 0;

        if ($request->hasFile('photo')) {
            $student->photo = $request->file('photo')->store('students/photos');
        }
        $student->save();

        foreach ($request->file('documents', []) as $i => $doc) {
            if (! empty($doc['file'])) {
                $student->documents()->create([
                    'title' => $request->input("documents.$i.title") ?: $doc['file']->getClientOriginalName(),
                    'path' => $doc['file']->store('students/documents'),
                ]);
            }
        }

        return $student;
    }
}
