<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\Transport\TransportFile;
use App\Models\Transport\TransportStaff;
use App\Models\Transport\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Photos and documents (RC, insurance copy, licence, verification…) of vehicles and staff. */
class FileController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'owner_type' => ['required', Rule::in(['vehicle', 'staff'])], 'owner_id' => ['required', 'integer'],
            'kind' => ['required', Rule::in(['photo', 'document'])], 'title' => ['nullable', 'string', 'max:100'],
            'files' => ['required', 'array', 'max:10'], 'files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:4096'],
        ], ['files.*.max' => 'Each file must be under 4 MB.']);
        $owner = $data['owner_type'] === 'vehicle' ? Vehicle::findOrFail($data['owner_id']) : TransportStaff::findOrFail($data['owner_id']);
        foreach ($request->file('files') as $file) {
            TransportFile::create(['owner_type' => $data['owner_type'], 'owner_id' => $owner->id, 'kind' => $data['kind'],
                'title' => $data['title'] ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'path' => $file->store('transport/'.\App\Support\Tenant::id().'/'.$data['owner_type'])]);
        }

        return back()->with('success', count($request->file('files')).' file(s) uploaded.');
    }

    public function show(TransportFile $file)
    {
        abort_unless(Storage::exists($file->path), 404);

        return Storage::response($file->path);
    }

    public function destroy(TransportFile $file)
    {
        Storage::delete($file->path);
        $file->delete();

        return back()->with('success', 'File deleted.');
    }
}
