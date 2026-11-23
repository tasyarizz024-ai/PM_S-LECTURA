<?php

function schedule_xlsx_headers(bool $includeProdi = true): array
{
    if (!$includeProdi) {
        return [
            'nama_dosen',
            'nama_mk',
            'nama_kelas',
            'semester',
            'tahun_ajaran',
            'hari',
            'jam_mulai',
            'jam_selesai',
            'tipe_sesi',
            'mode',
            'ruang',
        ];
    }

    return [
        'nama_dosen',
        'nama_mk',
        'nama_kelas',
        'prodi',
        'semester',
        'tahun_ajaran',
        'hari',
        'jam_mulai',
        'jam_selesai',
        'tipe_sesi',
        'mode',
        'ruang',
    ];
}

function schedule_xlsx_collect_text(SimpleXMLElement $element, string $namespace): string
{
    if ($element->getName() === 't') {
        return (string)$element;
    }

    $text = '';
    foreach ($element->children($namespace) as $child) {
        $text .= schedule_xlsx_collect_text($child, $namespace);
    }
    return $text;
}

function schedule_xlsx_parse_xml(string $xml, string $errorMessage): SimpleXMLElement
{
    $previousErrorMode = libxml_use_internal_errors(true);
    try {
        $parsed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
        if (!$parsed) {
            throw new RuntimeException($errorMessage);
        }
        return $parsed;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorMode);
    }
}

function schedule_xlsx_read(string $path, ?array $expectedHeaders = null): array
{
    $expectedHeaders = $expectedHeaders ?? schedule_xlsx_headers();
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('File bukan Excel .xlsx yang valid.');
    }

    $totalUncompressed = 0;
    if ($zip->numFiles > 1000) {
        $zip->close();
        throw new RuntimeException('Struktur file Excel tidak valid.');
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $totalUncompressed += (int)($stat['size'] ?? 0);
    }
    if ($totalUncompressed > 20 * 1024 * 1024) {
        $zip->close();
        throw new RuntimeException('Isi file Excel terlalu besar untuk diproses.');
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        $zip->close();
        throw new RuntimeException('Sheet pertama tidak ditemukan di dalam file Excel.');
    }
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    $zip->close();

    $sheet = schedule_xlsx_parse_xml($sheetXml, 'Isi sheet Excel tidak dapat dibaca.');

    $sharedStrings = [];
    if ($sharedXml !== false) {
        $shared = schedule_xlsx_parse_xml($sharedXml, 'Daftar teks Excel tidak dapat dibaca.');
        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        foreach ($shared->children($namespace)->si as $item) {
            $sharedStrings[] = schedule_xlsx_collect_text($item, $namespace);
        }
    }

    $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $sheet->registerXPathNamespace('m', $namespace);
    $xmlRows = $sheet->xpath('/m:worksheet/m:sheetData/m:row');
    if (!$xmlRows || count($xmlRows) > 501) {
        throw new RuntimeException('File harus berisi header dan paling banyak 500 baris data.');
    }

    $rows = [];
    foreach ($xmlRows as $xmlRow) {
        $cells = [];
        foreach ($xmlRow->children($namespace)->c as $cell) {
            $attributes = $cell->attributes();
            $reference = (string)($attributes['r'] ?? '');
            if (!preg_match('/^([A-Z]+)[0-9]+$/', $reference, $match)) {
                continue;
            }
            $column = 0;
            foreach (str_split($match[1]) as $letter) {
                $column = ($column * 26) + ord($letter) - 64;
            }

            $cellType = (string)($attributes['t'] ?? '');
            $cellChildren = $cell->children($namespace);
            if ($cellType === 'inlineStr') {
                $value = schedule_xlsx_collect_text($cellChildren->is, $namespace);
            } else {
                $value = (string)($cellChildren->v ?? '');
                if ($cellType === 's') {
                    $value = $sharedStrings[(int)$value] ?? '';
                }
            }
            $cells[$column] = trim($value);
        }
        if ($cells) {
            $rows[] = $cells;
        }
    }

    if (!$rows) {
        throw new RuntimeException('Sheet Excel masih kosong.');
    }

    $header = array_values($rows[0]);
    $matchedHeaders = null;
    $possibleHeaderSets = [$expectedHeaders];

    // Toleransi: kolom pertama bisa nama_dosen atau email_dosen
    if (isset($expectedHeaders[0]) && in_array($expectedHeaders[0], ['nama_dosen', 'email_dosen'], true)) {
        $altFirst = $expectedHeaders[0] === 'nama_dosen' ? 'email_dosen' : 'nama_dosen';
        $altHeaders = $expectedHeaders;
        $altHeaders[0] = $altFirst;
        $possibleHeaderSets[] = $altHeaders;
    }

    foreach ($possibleHeaderSets as $candidateHeaders) {
        if ($header === $candidateHeaders) {
            $matchedHeaders = $candidateHeaders;
            break;
        }
    }

    if ($matchedHeaders === null) {
        throw new RuntimeException('Header tidak sesuai template. Unduh template Excel dan jangan ubah nama atau urutan kolom.');
    }

    $result = [];
    foreach (array_slice($rows, 1) as $index => $cells) {
        $row = [];
        foreach ($matchedHeaders as $offset => $key) {
            $row[$key] = $cells[$offset + 1] ?? '';
        }
        foreach (['jam_mulai', 'jam_selesai'] as $timeField) {
            if (is_numeric($row[$timeField]) && (float)$row[$timeField] >= 0 && (float)$row[$timeField] < 1) {
                $minutes = (int)round((float)$row[$timeField] * 1440);
                if ($minutes < 1440) {
                    $row[$timeField] = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
                }
            }
        }
        if (count(array_filter($row, static fn($value) => $value !== '')) > 0) {
            $row['_row_number'] = $index + 2;
            $result[] = $row;
        }
    }
    return $result;
}

function schedule_xlsx_template(?array $headers = null, string $sheetName = 'Jadwal'): string
{
    $headers = $headers ?? schedule_xlsx_headers();
    $cells = [];
    foreach ($headers as $index => $header) {
        $number = $index + 1;
        $column = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $column = chr(65 + $remainder) . $column;
            $number = intdiv($number - 1, 26);
        }
        $escaped = htmlspecialchars($header, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $cells[] = '<c r="' . $column . '1" t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
    }

    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . htmlspecialchars($sheetName, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>',
        'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1">'
            . implode('', $cells) . '</row></sheetData></worksheet>',
    ];

    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'slectura-template-' . bin2hex(random_bytes(8)) . '.xlsx';
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Template Excel tidak dapat dibuat.');
    }
    foreach ($files as $name => $contents) {
        if (!$zip->addFromString($name, $contents)) {
            $zip->close();
            unlink($path);
            throw new RuntimeException('Template Excel tidak dapat ditulis.');
        }
    }
    if (!$zip->close()) {
        unlink($path);
        throw new RuntimeException('Template Excel tidak dapat disimpan.');
    }

    $contents = file_get_contents($path);
    unlink($path);
    if ($contents === false) {
        throw new RuntimeException('Template Excel tidak dapat dibaca.');
    }
    return $contents;
}
