<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\UpdateLog;
use App\Services\Updater;
use Illuminate\Http\Request;

class UpdateController extends Controller
{
    private function pendingPath(): string
    {
        $dir = storage_path('app/updates');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        return $dir.'/pending.zip';
    }

    public function index(Updater $updater)
    {
        $pending = null;
        if (is_file($this->pendingPath())) {
            try {
                $pending = $updater->inspect($this->pendingPath());
            } catch (\Throwable $e) {
                @unlink($this->pendingPath());
            }
        }

        $backups = collect(glob(Updater::backupDir().'/*') ?: [])
            ->map(fn ($f) => ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)])
            ->sortByDesc('time')->values();

        return view('super.update', [
            'version' => $updater->currentVersion(),
            'pending' => $pending,
            'logs' => UpdateLog::with('user')->latest('id')->take(20)->get(),
            'backups' => $backups,
            'limits' => ['upload' => ini_get('upload_max_filesize'), 'post' => ini_get('post_max_size')],
        ]);
    }

    /** Step 1: upload and check the package (nothing changes yet). */
    public function upload(Request $request, Updater $updater)
    {
        $request->validate([
            'package' => ['required', 'file', 'max:307200'],
            'password' => ['required', 'current_password'],
        ], [
            'package.uploaded' => 'The file could not be uploaded. It may be bigger than the server allows ('.ini_get('upload_max_filesize').'). Increase "upload_max_filesize" and "post_max_size" in hPanel → PHP Configuration → PHP options.',
            'password.current_password' => 'Wrong password.',
        ]);

        $request->file('package')->move(dirname($this->pendingPath()), basename($this->pendingPath()));
        try {
            $m = $updater->inspect($this->pendingPath());
        } catch (\Throwable $e) {
            @unlink($this->pendingPath());

            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('super.update')->with('success', "Package checked: version {$m['version']} with {$m['files']} files. Review below and click Update now.");
    }

    /** Step 2: apply the checked package. */
    public function apply(Request $request, Updater $updater)
    {
        abort_unless(is_file($this->pendingPath()), 404);

        try {
            $log = $updater->apply($this->pendingPath(), $request->user()->id);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('super.update')->with('error', $e->getMessage());
        } finally {
            @unlink($this->pendingPath());
        }

        return redirect()->route('super.update')->with('success', "Updated from {$log->from_version} to {$log->to_version}. Backups were saved first.");
    }

    public function cancel()
    {
        @unlink($this->pendingPath());

        return back()->with('success', 'Pending update removed.');
    }

    public function rollback(Request $request, UpdateLog $log, Updater $updater)
    {
        $request->validate(['password' => ['required', 'current_password']], ['password.current_password' => 'Wrong password.']);
        try {
            $r = $updater->rollback($log, $request->user()->id);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Code restored to version {$r->to_version}.");
    }

    public function download(string $file)
    {
        $path = Updater::backupDir().'/'.basename($file);
        abort_unless(is_file($path), 404);

        return response()->download($path);
    }
}
