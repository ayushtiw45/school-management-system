<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\DataExchange;
use App\Services\FeeService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Import & Export page: Excel download of data, bulk upload with preview. */
class DataController extends Controller
{
    public function index()
    {
        return view('data.index', ['exports' => DataExchange::EXPORTS, 'imports' => DataExchange::imports()]);
    }

    public function export(string $type, DataExchange $data)
    {
        abort_unless(isset(DataExchange::EXPORTS[$type]), 404);
        [$name] = $data->export($type);

        return $this->download($data->exportXlsx($type), $name.'.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function exportAll(DataExchange $data)
    {
        return $this->download($data->exportAllZip(), 'all-data.zip', 'application/zip');
    }

    public function template(string $type, DataExchange $data)
    {
        abort_unless(isset(DataExchange::imports()[$type]), 404);

        return $this->download($data->templateXlsx($type), 'template-'.$type.'.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', false);
    }

    public function upload(Request $request, string $type, DataExchange $data)
    {
        abort_unless(isset(DataExchange::imports()[$type]), 404);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120']],
            ['file.mimes' => 'Upload an Excel (.xlsx) or .csv file.', 'file.max' => 'The file must be under 5 MB.']);
        $file = $request->file('file');
        try {
            $result = $data->parse($type, $file->getRealPath(), $file->getClientOriginalExtension() ?: 'xlsx');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        if ($result['total'] > 3000) {
            return back()->with('error', 'Please upload at most 3,000 rows at a time.');
        }
        $token = Str::random(24);
        Storage::put($this->path($token), json_encode(['type' => $type, 'name' => $file->getClientOriginalName()] + $result));

        return redirect()->route('data.preview', $token);
    }

    public function preview(string $token)
    {
        $import = $this->load($token);

        return view('data.preview', ['token' => $token, 'import' => $import, 'meta' => DataExchange::imports()[$import['type']]]);
    }

    public function confirm(Request $request, string $token, DataExchange $data, FeeService $fees)
    {
        $import = $this->load($token);
        abort_if(empty($import['valid']), 422, 'There are no correct rows to import.');
        $n = $data->save($import['type'], $import['valid'], $request->only(['status', 'fees', 'one_time']), $fees);
        Storage::delete($this->path($token));
        $where = ['students' => 'students.index', 'staff' => 'staff.employees.index', 'dues' => 'fees.pending'][$import['type']];

        return redirect()->route($where)->with('success', "{$n} record(s) imported from {$import['name']}.".($import['errors'] ? ' '.count($import['errors']).' row(s) with problems were skipped.' : ''));
    }

    private function path(string $token): string
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{24}$/', $token), 404);

        return 'imports/'.Tenant::id().'/'.$token.'.json';
    }

    private function load(string $token): array
    {
        $path = $this->path($token);
        abort_unless(Storage::exists($path), 404, 'This upload has expired. Please upload the file again.');

        return json_decode(Storage::get($path), true);
    }

    private function download(string $content, string $file, string $mime, bool $withSchool = true)
    {
        $name = ($withSchool ? Str::slug((string) Setting::get('school_name')).'-' : '').str_replace('.', '-'.now()->format('Y-m-d').'.', $file);

        return response($content, 200, ['Content-Type' => $mime, 'Content-Disposition' => 'attachment; filename="'.$name.'"']);
    }
}
