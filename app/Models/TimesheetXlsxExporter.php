<?php

namespace App\Models;

class TimesheetXlsxExporter
{
    private const HEADERS = [
        'Project Group',
        'phase',
        'Task List/Module',
        'Task/General/Issue',
        'Notes',
        'Daily Log',
        'Log Hours (HH:MM)',
        'Hours(For Calculation)',
        'Date',
        'Billing Type',
        'User',
    ];

    /** Row field each group-by option breaks the per-group sheets down by. */
    private const GROUP_FIELDS = [
        'user' => 'user_name',
        'client' => 'project_group',
        'phase' => 'phase',
        'tasklist' => 'module_name',
    ];

    public static function build(array $rows, array $filters, array $users, array $exportedBy, string $groupBy = 'user'): string
    {
        $sheets = [];
        $sheets[] = [
            'name' => self::safeSheetName(self::mainSheetName($rows, $filters)),
            'rows' => self::mainSheetRows($rows, $filters, $users, $exportedBy),
            'boldRows' => [1, 2, 3, 4, 5, 6, 7, 9],
            'numericColumns' => ['H'],
        ];

        $groupField = self::GROUP_FIELDS[$groupBy] ?? self::GROUP_FIELDS['user'];
        foreach (self::rowsByField($rows, $groupField) as $groupName => $groupRows) {
            $sheets[] = [
                'name' => self::safeSheetName($groupName, array_column($sheets, 'name')),
                'rows' => self::tableRows($groupRows),
                'boldRows' => [1],
                'numericColumns' => ['H'],
            ];
        }

        return self::zip(self::workbookFiles($sheets));
    }

    private static function mainSheetRows(array $rows, array $filters, array $users, array $exportedBy): array
    {
        $projectName = trim((string) ($filters['export_project_name'] ?? '')) ?: (trim((string) ($filters['project_group'] ?? '')) ?: 'All Projects');
        $projectId = trim((string) ($filters['export_project_id'] ?? '')) ?: '-';
        $forWhom = 'All Users';

        if (!empty($filters['user_id'])) {
            foreach ($users as $user) {
                if ((string) $user['id'] === (string) $filters['user_id']) {
                    $forWhom = $user['name'];
                    break;
                }
            }
        }

        return array_merge([
            ['ORGANIZATION NAME : ', 'EduRiser'],
            ['EXPORTED BY : ', $exportedBy['name'] ?? 'System Admin'],
            ['PROJECT NAME : ', $projectName],
            ['PROJECT ID : ', $projectId],
            ['EXPORTED ON : ', date('d F Y h:i:s A')],
            ['TIME PERIOD : ', self::displayPeriod($filters)],
            ['For Whom : ', $forWhom],
            [],
        ], self::tableRows($rows));
    }

    private static function tableRows(array $rows): array
    {
        $sheetRows = [self::HEADERS];
        $total = 0.0;

        foreach ($rows as $row) {
            $hours = (float) ($row['hours'] ?? 0);
            $total += $hours;
            $sheetRows[] = [
                $row['project_group'] ?? '',
                $row['phase'] ?? '',
                $row['module_name'] ?? '',
                $row['report_task_issue'] ?? $row['task_category'] ?? '',
                $row['notes'] ?? '-',
                $row['daily_log'] ?: '-',
                self::hoursToHhMm($hours),
                $hours,
                self::displayDate($row['log_date'] ?? ''),
                $row['billing_type'] ?? '',
                $row['user_name'] ?? '',
            ];
        }

        $sheetRows[] = ['', '', '', '', '', 'Total Hours', self::hoursToHhMm($total), $total, '', '', ''];
        return $sheetRows;
    }

    private static function rowsByField(array $rows, string $field): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row[$field] ?? '')) ?: 'Unassigned';
            $grouped[$name] ??= [];
            $grouped[$name][] = $row;
        }
        ksort($grouped);
        return $grouped;
    }

    private static function workbookFiles(array $sheets): array
    {
        $files = [
            '[Content_Types].xml' => self::contentTypes(count($sheets)),
            '_rels/.rels' => self::rootRels(),
            'xl/workbook.xml' => self::workbookXml($sheets),
            'xl/_rels/workbook.xml.rels' => self::workbookRels(count($sheets)),
            'xl/styles.xml' => self::stylesXml(),
        ];

        foreach ($sheets as $index => $sheet) {
            $files['xl/worksheets/sheet' . ($index + 1) . '.xml'] = self::sheetXml($sheet['rows'], $sheet['boldRows'], $sheet['numericColumns']);
        }

        return $files;
    }

    private static function sheetXml(array $rows, array $boldRows, array $numericColumns): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<dimension ref="A1:K' . max(1, count($rows)) . '"/><sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetFormatPr defaultRowHeight="15"/>';
        $xml .= '<cols>';

        foreach ([23, 24, 28, 34, 52, 24, 20, 26, 16, 16, 22] as $i => $width) {
            $col = $i + 1;
            $xml .= '<col min="' . $col . '" max="' . $col . '" width="' . $width . '" customWidth="1"/>';
        }

        $xml .= '</cols><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $xml .= '<row r="' . $excelRow . '">';

            for ($colIndex = 0; $colIndex < 11; $colIndex++) {
                $value = $row[$colIndex] ?? '';
                if ($value === '' || $value === null) {
                    continue;
                }

                $column = self::columnName($colIndex + 1);
                $cellRef = $column . $excelRow;
                $style = in_array($excelRow, $boldRows, true) ? ' s="1"' : '';

                if (is_numeric($value) && in_array($column, $numericColumns, true)) {
                    $xml .= '<c r="' . $cellRef . '"' . $style . '><v>' . self::num($value) . '</v></c>';
                    continue;
                }

                $xml .= '<c r="' . $cellRef . '" t="inlineStr"' . $style . '><is><t>' . self::xml((string) $value) . '</t></is></c>';
            }

            $xml .= '</row>';
        }

        return $xml . '</sheetData></worksheet>';
    }

    private static function contentTypes(int $sheetCount): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $xml .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>';
        $xml .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return $xml . '</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private static function workbookXml(array $sheets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        foreach ($sheets as $index => $sheet) {
            $sheetId = $index + 1;
            $xml .= '<sheet name="' . self::xml($sheet['name']) . '" sheetId="' . $sheetId . '" r:id="rId' . $sheetId . '"/>';
        }
        return $xml . '</sheets></workbook>';
    }

    private static function workbookRels(int $sheetCount): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $xml .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        return $xml . '<Relationship Id="rId' . ($sheetCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private static function zip(array $files): string
    {
        $local = '';
        $central = '';
        $offset = 0;
        [$time, $date] = self::dosDateTime();

        foreach ($files as $name => $data) {
            $size = strlen($data);
            $crc = crc32($data);
            if ($crc < 0) {
                $crc += 4294967296;
            }

            $header = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $time, $date, $crc, $size, $size, strlen($name), 0) . $name;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $time, $date, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
            $local .= $header . $data;
            $offset += strlen($header) + $size;
        }

        return $local . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), strlen($local), 0);
    }

    private static function dosDateTime(): array
    {
        $now = getdate();
        return [
            ($now['hours'] << 11) | ($now['minutes'] << 5) | (int) floor($now['seconds'] / 2),
            (($now['year'] - 1980) << 9) | ($now['mon'] << 5) | $now['mday'],
        ];
    }

    private static function displayPeriod(array $filters): string
    {
        $from = self::displayDate($filters['from_date'] ?? '');
        $to = self::displayDate($filters['to_date'] ?? '');
        if ($from !== '-' && $to !== '-') {
            return $from . ' To ' . $to;
        }
        if ($from !== '-') {
            return 'From ' . $from;
        }
        if ($to !== '-') {
            return 'Up to ' . $to;
        }
        return 'All Dates';
    }

    private static function displayDate(?string $date): string
    {
        if (!$date) {
            return '-';
        }
        $time = strtotime($date);
        return $time ? date('d-m-Y', $time) : $date;
    }

    private static function hoursToHhMm(float $hours): string
    {
        $minutes = (int) round($hours * 60);
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private static function mainSheetName(array $rows, array $filters): string
    {
        $project = trim((string) ($filters['project_group'] ?? ''));
        return $project !== '' ? $project : ($rows[0]['project_group'] ?? 'TimeSheet');
    }

    private static function safeSheetName(string $name, array $existing = []): string
    {
        $name = trim(preg_replace('/[\[\]\:\*\?\/\\\\]/', '-', $name)) ?: 'Sheet';
        $name = substr($name, 0, 31);
        $base = $name;
        $i = 2;
        while (in_array($name, $existing, true)) {
            $suffix = ' ' . $i;
            $name = substr($base, 0, 31 - strlen($suffix)) . $suffix;
            $i++;
        }
        return $name;
    }

    private static function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }
        return $name;
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function num(float|int|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
