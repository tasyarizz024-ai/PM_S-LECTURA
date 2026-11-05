<?php

require_once __DIR__ . '/../includes/schedule_xlsx.php';

$path = tempnam(sys_get_temp_dir(), 'slectura-xlsx-');
if ($path === false) {
    fwrite(STDERR, "[FAIL] Could not create temporary XLSX test file.\n");
    exit(1);
}

try {
    if (file_put_contents($path, schedule_xlsx_template()) === false) {
        throw new RuntimeException('Could not write generated XLSX template.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Could not reopen generated XLSX template.');
    }
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $sharedStrings = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="1" uniqueCount="1">'
        . '<si><t>dosen1@example.test</t></si></sst>';
    $row = '<row r="2"><c r="A2" t="s"><v>0</v></c>'
        . '<c r="B2" t="inlineStr"><is><t>TEST001</t></is></c>'
        . '<c r="C2" t="inlineStr"><is><t>Uji</t></is></c>'
        . '<c r="D2" t="inlineStr"><is><t>Kelas Uji</t></is></c>'
        . '<c r="E2" t="inlineStr"><is><t>Manajemen Informatika</t></is></c>'
        . '<c r="F2"><v>1</v></c><c r="G2" t="inlineStr"><is><t>2026/2027</t></is></c>'
        . '<c r="H2"><v>1</v></c><c r="I2" t="inlineStr"><is><t>Senin</t></is></c>'
        . '<c r="J2"><v>0.3541666667</v></c><c r="K2"><v>0.3958333333</v></c>'
        . '<c r="L2" t="inlineStr"><is><t>teori</t></is></c><c r="M2"><v>2</v></c>'
        . '<c r="N2" t="inlineStr"><is><t>daring</t></is></c></row>';
    if ($sheet === false || !$zip->addFromString('xl/sharedStrings.xml', $sharedStrings)
        || !$zip->addFromString('xl/worksheets/sheet1.xml', str_replace('</sheetData>', $row . '</sheetData>', $sheet))) {
        throw new RuntimeException('Could not add XLSX test data.');
    }
    $zip->close();

    $rows = schedule_xlsx_read($path);
    if (count($rows) !== 1 || $rows[0]['email_dosen'] !== 'dosen1@example.test'
        || $rows[0]['jam_mulai'] !== '08:30' || $rows[0]['jam_selesai'] !== '09:30') {
        throw new RuntimeException('Shared strings or Excel time values were not parsed correctly.');
    }
    echo "[PASS] XLSX template, shared strings, and time cells round-trip correctly.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "[FAIL] XLSX template round-trip failed: " . $exception->getMessage() . "\n");
    exit(1);
} finally {
    if (file_exists($path)) {
        unlink($path);
    }
}
