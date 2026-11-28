<?php

namespace App\Http\Controllers\Transport;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

trait StoresBills
{
    protected const BILL_RULE = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:4096'];

    /** Saves an uploaded "bill" file (replacing the old one) and returns its path for the model. */
    protected function storeBill(Request $request, string $folder, ?Model $model = null): array
    {
        if (! $request->hasFile('bill')) {
            return [];
        }
        if ($model?->bill_path) {
            Storage::delete($model->bill_path);
        }

        return ['bill_path' => $request->file('bill')->store('transport/'.\App\Support\Tenant::id().'/'.$folder)];
    }

    protected function deleteWithBill(Model $model): void
    {
        if ($model->bill_path) {
            Storage::delete($model->bill_path);
        }
        $model->delete();
    }

    protected function billResponse(Model $model)
    {
        abort_unless($model->bill_path && Storage::exists($model->bill_path), 404);

        return Storage::response($model->bill_path);
    }
}
