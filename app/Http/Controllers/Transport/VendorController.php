<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\Transport\TransportVendor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{
    public function index()
    {
        return view('transport.vendors', ['vendors' => TransportVendor::withCount('maintenances')->withSum('maintenances', 'total_cost')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        TransportVendor::create($this->validated($request));

        return back()->with('success', 'Vendor added.');
    }

    public function update(Request $request, TransportVendor $vendor)
    {
        $vendor->update($this->validated($request));

        return back()->with('success', 'Vendor updated.');
    }

    public function destroy(TransportVendor $vendor)
    {
        $vendor->delete();

        return back()->with('success', 'Vendor deleted (their past records are kept).');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'], 'type' => ['required', Rule::in(array_keys(TransportVendor::TYPES))],
            'contact_person' => ['nullable', 'string', 'max:100'], 'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:300'], 'notes' => ['nullable', 'string', 'max:300'],
        ]);
    }
}
