<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/** Reads the first sheet of an .xlsx file, or a .csv file, into rows of strings (no external library). */
class SpreadsheetReader
{
    /** @return array<int, array<int, string>> */
    public static function read(string $path, string $extension): array
    {
        $rows = strtolower($extension) === 'csv' ? self::csv($path) : self::xlsx($path);

        // drop fully empty rows, trim cells
        return array_values(array_filter(array_map(fn ($r) => array_map(fn ($c) => trim((string) $c), $r), $rows),
            fn ($r) => implode('', $r) !== ''));
    }

    private static function csv(string $path): array
    {
        $fh = fopen($path, 'r');
        $first = fgets($fh) ?: '';
        rewind($fh);
        $sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $rows = [];
        while (($r = fgetcsv($fh, 0, $sep, '"', '\\')) !== false) {
            if ($rows === [] && isset($r[0])) {
                $r[0] = preg_replace('/^\xEF\xBB\xBF/', '', $r[0]); // Excel UTF-8 BOM
            }
            $rows[] = $r;
        }
        fclose($fh);

        return $rows;
    }

    private static function xlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('This file could not be opened. Save it as .xlsx or .csv and try again.');
        }
        try {
            $shared = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $sx = simplexml_load_string($xml);
                foreach ($sx->si as $si) {
                    $shared[] = isset($si->t) ? (string) $si->t : implode('', array_map(fn ($r) => (string) $r->t, iterator_to_array($si->r, false)));
                }
            }
            $sheet = self::firstSheetPath($zip);
            $xml = $zip->getFromName($sheet);
            if ($xml === false) {
                throw new RuntimeException('The first sheet of the file is empty or missing.');
            }
            $sx = simplexml_load_string($xml);
            $rows = [];
            foreach ($sx->sheetData->row as $row) {
                $cells = [];
                foreach ($row->c as $c) {
                    $idx = self::colIndex(preg_replace('/\d+/', '', (string) $c['r']));
                    $t = (string) $c['t'];
                    $v = match ($t) {
                        's' => $shared[(int) $c->v] ?? '',
                        'inlineStr' => isset($c->is->t) ? (string) $c->is->t : implode('', array_map(fn ($r) => (string) $r->t, iterator_to_array($c->is->r, false))),
                        'b' => ((string) $c->v) === '1' ? 'TRUE' : 'FALSE',
                        default => (string) $c->v,
                    };
                    $cells[$idx] = $v;
                }
                if ($cells) {
                    $max = max(array_keys($cells));
                    $rows[] = array_map(fn ($i) => $cells[$i] ?? '', range(0, $max));
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb && $rels) {
            $w = simplexml_load_string($wb);
            $first = $w->sheets->sheet[0] ?? null;
            if ($first) {
                $rid = (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                foreach (simplexml_load_string($rels)->Relationship as $r) {
                    if ((string) $r['Id'] === $rid) {
                        $target = ltrim((string) $r['Target'], '/');

                        return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                    }
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return max(0, $n - 1);
    }

    /** Excel date serial (e.g. 45383) or d-m-Y / d/m/Y / Y-m-d text → Y-m-d, or null. */
    public static function date(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (is_numeric($v) && (float) $v > 1000 && (float) $v < 80000) {
            return \Carbon\Carbon::create(1899, 12, 30)->addDays((int) floor((float) $v))->toDateString();
        }
        foreach (['d-m-Y', 'd/m/Y', 'd.m.Y', 'Y-m-d', 'Y/m/d', 'd-m-y', 'd/m/y', 'd M Y', 'd-M-Y', 'j M Y'] as $f) {
            try {
                $d = \Carbon\Carbon::createFromFormat('!'.$f, $v);
                if ($d && $d->year > 1900 && $d->year < 2100 && ! \Carbon\Carbon::getLastErrors()) {
                    return $d->toDateString();
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }
}
