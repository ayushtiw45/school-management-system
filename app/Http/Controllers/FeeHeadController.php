<?php

namespace App\Http\Controllers;

use App\Models\FeeHead;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeeHeadController extends Controller
{
    public function index()
    {
        return view('fees.heads', [
            'heads' => FeeHead::withCount('structures')->orderBy('name')->get(),
            'editing' => null,
        ]);
    }

    public function create()
    {
        return redirect()->route('fee-heads.index');
    }

    public function store(Request $request)
    {
        FeeHead::create($this->validated($request));

        return back()->with('success', 'Fee category added.');
    }

    public function edit(FeeHead $feeHead)
    {
        return view('fees.heads', [
            'heads' => FeeHead::withCount('structures')->orderBy('name')->get(),
            'editing' => $feeHead,
        ]);
    }

    public function update(Request $request, FeeHead $feeHead)
    {
        $feeHead->update($this->validated($request, $feeHead));

        return redirect()->route('fee-heads.index')->with('success', 'Fee category updated.');
    }

    public function destroy(FeeHead $feeHead)
    {
        if ($feeHead->structures()->exists()) {
            return back()->with('error', 'This category is used in the fee structure. Remove it from there first.');
        }
        $feeHead->delete();

        return back()->with('success', 'Fee category deleted.');
    }

    private function validated(Request $request, ?FeeHead $head = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('fee_heads')->ignore($head?->id)],
            'frequency' => ['required', Rule::in(array_keys(FeeHead::FREQUENCIES))],
            'concession_applicable' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $data['concession_applicable'] = $request->boolean('concession_applicable');

        return $data;
    }
}
