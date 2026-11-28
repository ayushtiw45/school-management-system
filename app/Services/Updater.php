<?php

namespace App\Services;

use App\Models\UpdateLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

/**
 * One-click software update from the owner panel.
 *
 * An update package is a zip made by scripts/build-update.sh. It contains update.json and the app files.
 * Applying it: backup code + database → maintenance mode → copy files (never .env, storage, uploads,
 * the installer) → clear caches → run new migrations → back online. On any error the code backup is restored.
 */
class Updater
{
    public const APP_ID = 'tds-school';

    /** Never overwritten by an update (prefix match on the path inside the package). */
    private const PROTECTED = ['.env', 'storage/', 'public/storage', 'public/install.php', 'database/database.sqlite', '.git/', 'bootstrap/cache/', 'update.json'];

    private const KEEP_BACKUPS = 3;

    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = rtrim($root ?? base_path(), '/\\');
    }

    public static function backupDir(): string
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        return $dir;
    }

    public function currentVersion(): string
    {
        return trim((string) @file_get_contents($this->root.'/VERSION')) ?: 'dev';
    }

    /**
     * Check a package and return its manifest (+ number of files). Throws with a plain message when it can't be used.
     */
    public function inspect(string $zipPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('This is not a valid zip file.');
        }

        try {
            $json = $zip->getFromName('update.json');
            $m = $json ? json_decode($json, true) : null;
            if (! is_array($m) || ($m['app'] ?? null) !== self::APP_ID) {
                throw new RuntimeException('This file is not a TDS School update package (update.json missing or wrong).');
            }
            if (! preg_match('/^\d+\.\d+\.\d+$/', (string) ($m['version'] ?? ''))) {
                throw new RuntimeException('The update package has no valid version number.');
            }
            if (! empty($m['php']) && version_compare(PHP_VERSION, $m['php'], '<')) {
                throw new RuntimeException("This update needs PHP {$m['php']} or newer (the server has ".PHP_VERSION.'). Change it in hPanel → PHP Configuration.');
            }
            if (empty($m['vendor'])) {
                $current = @sha1_file($this->root.'/composer.lock');
                if (($m['composer_lock'] ?? null) !== $current) {
                    throw new RuntimeException('This update needs the full package (with libraries). Ask for the "full" update file.');
                }
            }
            foreach (['VERSION', 'routes/web.php', 'app/Providers/AppServiceProvider.php'] as $must) {
                if ($zip->locateName($must) === false) {
                    throw new RuntimeException("The update package is incomplete ({$must} missing).");
                }
            }

            $files = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (! $this->safePath($name)) {
                    throw new RuntimeException('The update package contains an unsafe path: '.$name);
                }
                if (! str_ends_with($name, '/') && ! $this->isProtected($name)) {
                    $files++;
                }
            }
        } finally {
            $zip->close();
        }

        return $m + ['files' => $files, 'notes' => $m['notes'] ?? []];
    }

    public function apply(string $zipPath, ?int $userId = null, bool $migrate = true): UpdateLog
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $m = $this->inspect($zipPath);
        $from = $this->currentVersion();
        $code = $this->backupCode(! empty($m['vendor']));
        $db = $this->backupDatabase();

        $this->maintenance(true);
        try {
            $this->extract($zipPath);
            $this->clearCaches();
            if ($migrate) {
                Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Throwable $e) {
            // put the previous code back so the site keeps working
            try {
                $this->extract(self::backupDir().'/'.$code);
                $this->clearCaches();
            } catch (\Throwable) {
            }
            $this->maintenance(false);

            UpdateLog::create(['action' => 'update', 'from_version' => $from, 'to_version' => $m['version'], 'status' => 'failed',
                'message' => mb_substr($e->getMessage(), 0, 1000), 'code_backup' => $code, 'db_backup' => $db, 'user_id' => $userId]);

            throw new RuntimeException('Update failed and the previous version was restored: '.$e->getMessage(), 0, $e);
        }
        $this->maintenance(false);
        $this->pruneBackups();

        return UpdateLog::create(['action' => 'update', 'from_version' => $from, 'to_version' => $m['version'], 'status' => 'success',
            'message' => implode("\n", (array) $m['notes']), 'code_backup' => $code, 'db_backup' => $db, 'user_id' => $userId]);
    }

    /** Put back the code saved before an update (the database is not changed). */
    public function rollback(UpdateLog $log, ?int $userId = null): UpdateLog
    {
        $file = self::backupDir().'/'.basename((string) $log->code_backup);
        if (! $log->code_backup || ! is_file($file)) {
            throw new RuntimeException('The backup for this update is no longer available.');
        }
        @set_time_limit(0);
        $from = $this->currentVersion();

        $this->maintenance(true);
        try {
            $this->extract($file);
            $this->clearCaches();
        } finally {
            $this->maintenance(false);
        }

        return UpdateLog::create(['action' => 'rollback', 'from_version' => $from, 'to_version' => $this->currentVersion(), 'status' => 'success',
            'message' => 'Restored the code from before the update to '.$log->to_version.'.', 'code_backup' => $log->code_backup, 'user_id' => $userId]);
    }

    // ------------------------------------------------------------------

    private function safePath(string $name): bool
    {
        return $name !== '' && ! str_contains($name, '\\') && ! str_starts_with($name, '/') && ! preg_match('#(^|/)\.\.(/|$)#', $name)
            && ! preg_match('#^[a-zA-Z]:#', $name);
    }

    private function isProtected(string $name): bool
    {
        foreach (self::PROTECTED as $p) {
            if ($name === rtrim($p, '/') || str_starts_with($name, $p)) {
                return true;
            }
        }

        return false;
    }

    private function extract(string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open '.basename($zipPath));
        }
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_ends_with($name, '/') || ! $this->safePath($name) || $this->isProtected($name)) {
                    continue;
                }
                $target = $this->root.'/'.$name;
                $dir = dirname($target);
                if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                    throw new RuntimeException('Cannot create folder '.$dir);
                }
                $in = $zip->getStream($name);
                $tmp = $target.'.tds-new';
                $out = fopen($tmp, 'wb');
                if (! $in || ! $out) {
                    throw new RuntimeException('Cannot write '.$name);
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
                if (! rename($tmp, $target)) {
                    @unlink($tmp);
                    throw new RuntimeException('Cannot replace '.$name);
                }
            }
        } finally {
            $zip->close();
        }
    }

    /** Zip of the current code (without storage, .env and — unless the update replaces them — vendor libraries). */
    public function backupCode(bool $withVendor = true): string
    {
        $name = 'code-'.$this->currentVersion().'-'.date('Ymd-His').'.zip';
        $zip = new ZipArchive();
        if ($zip->open(self::backupDir().'/'.$name, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create the code backup.');
        }
        $skip = array_merge(self::PROTECTED, ['node_modules/', 'tests/'], $withVendor ? [] : ['vendor/']);
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($this->root) + 1));
            $skipThis = str_ends_with($rel, '.tds-new') || str_ends_with($rel, '.zip');
            foreach ($skip as $p) {
                if ($rel === rtrim($p, '/') || str_starts_with($rel, $p)) {
                    $skipThis = true;
                    break;
                }
            }
            if (! $skipThis) {
                $zip->addFile($file->getPathname(), $rel);
            }
        }
        $zip->close();

        return $name;
    }

    /** Full database copy (MySQL: SQL file you can import in phpMyAdmin; SQLite: copy of the file). */
    public function backupDatabase(): ?string
    {
        $conn = DB::connection();
        $driver = $conn->getDriverName();
        $stamp = date('Ymd-His');

        if ($driver === 'sqlite') {
            $path = $conn->getDatabaseName();
            if (! is_file($path)) {
                return null; // in-memory database (tests)
            }
            $name = "database-{$stamp}.sqlite";
            copy($path, self::backupDir().'/'.$name);

            return $name;
        }
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return null;
        }

        $name = "database-{$stamp}.sql.gz";
        $gz = gzopen(self::backupDir().'/'.$name, 'wb6');
        $pdo = $conn->getPdo();
        gzwrite($gz, "-- TDS School database backup ".date('c')."\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");
        foreach ($conn->select('SHOW TABLES') as $row) {
            $table = array_values((array) $row)[0];
            $create = array_values((array) $conn->selectOne("SHOW CREATE TABLE `{$table}`"))[1];
            gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n");
            $offset = 0;
            do {
                $rows = $conn->select("SELECT * FROM `{$table}` LIMIT 500 OFFSET {$offset}");
                if ($rows) {
                    $values = [];
                    foreach ($rows as $r) {
                        $values[] = '('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $r)).')';
                    }
                    $cols = '`'.implode('`,`', array_keys((array) $rows[0])).'`';
                    gzwrite($gz, "INSERT INTO `{$table}` ({$cols}) VALUES\n".implode(",\n", $values).";\n");
                }
                $offset += 500;
            } while (count($rows) === 500);
            gzwrite($gz, "\n");
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);

        return $name;
    }

    private function maintenance(bool $down): void
    {
        try {
            Artisan::call($down ? 'down' : 'up', $down ? ['--retry' => 60] : []);
        } catch (\Throwable) {
        }
    }

    private function clearCaches(): void
    {
        foreach (glob($this->root.'/bootstrap/cache/*.php') ?: [] as $f) {
            if (! str_ends_with($f, '.gitignore')) {
                @unlink($f);
            }
        }
        try {
            Artisan::call('view:clear');
        } catch (\Throwable) {
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private function pruneBackups(): void
    {
        foreach (['code-*.zip', 'database-*'] as $pattern) {
            $files = glob(self::backupDir().'/'.$pattern) ?: [];
            usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
            foreach (array_slice($files, self::KEEP_BACKUPS) as $old) {
                @unlink($old);
            }
        }
    }
}
