<?php

declare(strict_types=1);

/**
 * Reads an already-unzipped .xlsx (via `unzip file.xlsx -d dir`) using only
 * SimpleXML, since this machine's PHP doesn't have the zip extension for
 * ZipArchive. Handles multi-sheet workbooks (Zoho's per-project task export)
 * by concatenating every sheet's data rows together. Assumes inline strings
 * (t="inlineStr"), which is what Zoho's exports use (empty sharedStrings.xml).
 *
 * @return array{headers: array<string,string>, rows: list<array<string,string>>}
 *   headers/rows are keyed by header NAME (from $headerRow), not column letter.
 */
function read_xlsx_sheets(string $extractedDir, int $headerRow): array
{
    $sheetFiles = glob($extractedDir . '/xl/worksheets/sheet*.xml');
    if (!$sheetFiles) {
        throw new RuntimeException("No sheet XML found under $extractedDir");
    }
    natsort($sheetFiles);

    $headerNames = null; // column letter => header name, from the first sheet
    $rows = [];

    foreach ($sheetFiles as $file) {
        $xml = simplexml_load_file($file);
        if ($xml === false) {
            continue;
        }

        $localHeaders = null;
        foreach ($xml->sheetData->row as $row) {
            $r = (int) $row['r'];
            $cells = [];
            foreach ($row->c as $c) {
                preg_match('/^([A-Z]+)/', (string) $c['r'], $m);
                $col = $m[1];
                $cells[$col] = isset($c->is->t) ? (string) $c->is->t : (isset($c->v) ? (string) $c->v : '');
            }

            if ($r === $headerRow) {
                $localHeaders = $cells;
                if ($headerNames === null) {
                    $headerNames = $cells;
                }
            } elseif ($r > $headerRow && $localHeaders !== null) {
                $named = [];
                foreach ($localHeaders as $col => $name) {
                    if ($name !== '') {
                        $named[$name] = $cells[$col] ?? '';
                    }
                }
                $rows[] = $named;
            }
        }
    }

    return ['headers' => $headerNames ?? [], 'rows' => $rows];
}

/** Zoho dates are "DD-MM-YYYY" or "DD-MM-YYYY HH:MM"; returns Y-m-d or null. */
function zoho_date(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '' || $value === '-') {
        return null;
    }
    $datePart = explode(' ', $value)[0];
    $parts = explode('-', $datePart);
    if (count($parts) !== 3) {
        return null;
    }
    [$d, $m, $y] = $parts;
    if (!checkdate((int) $m, (int) $d, (int) $y)) {
        return null;
    }
    return sprintf('%04d-%02d-%02d', (int) $y, (int) $m, (int) $d);
}

/** Zoho "HH:MM" duration (e.g. "08:45", "1:00") to decimal hours; null if blank/"-" . */
function zoho_hhmm_to_hours(?string $value): ?float
{
    $value = trim((string) $value);
    if ($value === '' || $value === '-') {
        return null;
    }
    if (preg_match('/^(\d+):(\d{2})$/', $value, $m)) {
        return round((int) $m[1] + ((int) $m[2] / 60), 2);
    }
    if (is_numeric($value)) {
        return round((float) $value, 2);
    }
    return null;
}
