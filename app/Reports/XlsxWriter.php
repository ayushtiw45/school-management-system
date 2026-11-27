<?php

namespace App\Reports;

use ZipArchive;

/** Minimal dependency-free .xlsx writer (one sheet, bold header, number formats). */
class XlsxWriter
{
    public static function fromReport(ReportData $r, string $school): string
    {
        $rows = [];
        $rows[] = [[$school, 1]];
        $rows[] = [[$r->title, 1]];
        $rows[] = [[$r->subtitle, 0]];
        foreach ($r->summary as $label => $value) {
            $rows[] = [[$label, 1], [$value, 0]];
        }
        $rows[] = [];
        $rows[] = array_map(fn ($c) => [$c['label'], 1], array_values($r->columns));

        foreach ($r->rows as $row) {
            $rows[] = self::line($r, $row, false);
        }
        if ($r->totals) {
            $rows[] = self::line($r, $r->totals, true);
        }

        return self::build($rows, count($r->columns));
    }

    private static function line(ReportData $r, array $row, bool $bold): array
    {
        $out = [];
        foreach (array_keys($r->columns) as $col) {
            $v = $row[$col] ?? '';
            $type = $r->type($col);
            if ($v !== '' && $v !== null && in_array($type, ['money', 'int'], true)) {
                $out[] = [(float) $v, $type === 'money' ? ($bold ? 4 : 2) : ($bold ? 5 : 3)];
            } else {
                $out[] = [$r->format($col, $v), $bold ? 1 : 0];
            }
        }

        return $out;
    }

    /** @param array<int, array<int, array{0: mixed, 1: int}>> $rows  [value, styleId] */
    private static function build(array $rows, int $cols): string
    {
        $xml = '';
        foreach ($rows as $i => $cells) {
            $xml .= '<row r="'.($i + 1).'">';
            foreach ($cells as $j => [$v, $s]) {
                $ref = self::col($j).($i + 1);
                if (is_float($v) || is_int($v)) {
                    $xml .= '<c r="'.$ref.'" s="'.$s.'"><v>'.$v.'</v></c>';
                } else {
                    $xml .= '<c r="'.$ref.'" s="'.$s.'" t="inlineStr"><is><t xml:space="preserve">'
                        .htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        $colsXml = '<cols>'.implode('', array_map(fn ($k) => '<col min="'.$k.'" max="'.$k.'" width="'.($k === 1 ? 24 : 18).'" customWidth="1"/>', range(1, max(1, $cols)))).'</cols>';

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            // styles: 0 normal, 1 bold, 2 money, 3 integer, 4 bold money, 5 bold integer
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="6"><xf/><xf fontId="1" applyFont="1"/><xf numFmtId="164" applyNumberFormat="1"/><xf numFmtId="3" applyNumberFormat="1"/><xf numFmtId="164" fontId="1" applyNumberFormat="1" applyFont="1"/><xf numFmtId="3" fontId="1" applyNumberFormat="1" applyFont="1"/></cellXfs></styleSheet>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.$colsXml.'<sheetData>'.$xml.'</sheetData></worksheet>',
        ];

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $data = file_get_contents($tmp);
        @unlink($tmp);

        return $data;
    }

    private static function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }
}
