<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

function tXml($s) { return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
function tStr($col, $row, $text, $s = 0) {
    $st = $s ? " s=\"{$s}\"" : '';
    return "<c r=\"{$col}{$row}\"{$st} t=\"inlineStr\"><is><t>" . tXml($text) . "</t></is></c>";
}
function tNum($col, $row, $n) { return "<c r=\"{$col}{$row}\"><v>{$n}</v></c>"; }

$tmp = tempnam(sys_get_temp_dir(), 'cafe_inv_');
$zip = new ZipArchive();
$zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

$zip->addFromString('[Content_Types].xml',
'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

$zip->addFromString('_rels/.rels',
'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

$zip->addFromString('xl/_rels/workbook.xml.rels',
'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

$zip->addFromString('xl/workbook.xml',
'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="14400" windowHeight="8000"/></bookViews>
<sheets><sheet name="' . tXml('المخزون') . '" sheetId="1" r:id="rId1"/></sheets>
</workbook>');

// Style 0=normal | Style 1=bold white on blue header
$zip->addFromString('xl/styles.xml',
'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="2">
  <font><sz val="11"/><name val="Calibri"/></font>
  <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
</fonts>
<fills count="3">
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="gray125"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF1D4ED8"/></patternFill></fill>
</fills>
<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="2">
  <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
  <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
</cellXfs>
</styleSheet>');

$examples = [
    ['Pepsi',        'كانز',  400, 48,  4,   ''],
    ['حبوب قهوة',   'كيلو',  20,  2,   50,  ''],
    ['حليب',        'لتر',   18,  5,   8,   ''],
    ['سكر',         'كيلو',  15,  3,   5,   ''],
    ['شاي',         'علبة',  10,  2,   15,  ''],
    ['شوكولاتة',    'كيلو',  5,   1,   40,  ''],
    ['كريمة مخفوقة','علبة',  8,   2,   12,  ''],
    ['مياه معدنية', 'كرتون', 20,  5,   30,  '24 زجاجة في الكرتون'],
];

$sd = '<row r="1">'
    . tStr('A',1,'اسم المادة *',1) . tStr('B',1,'الوحدة *',1)
    . tStr('C',1,'الكمية الحالية',1) . tStr('D',1,'الحد الأدنى',1)
    . tStr('E',1,'سعر الوحدة',1) . tStr('F',1,'ملاحظات',1)
    . '</row>';

foreach ($examples as $i => $ex) {
    $r = $i + 2;
    $sd .= "<row r=\"{$r}\">"
         . tStr('A',$r,$ex[0]) . tStr('B',$r,$ex[1])
         . tNum('C',$r,$ex[2]) . tNum('D',$r,$ex[3])
         . tNum('E',$r,$ex[4])
         . ($ex[5] !== '' ? tStr('F',$r,$ex[5]) : '')
         . '</row>';
}

$zip->addFromString('xl/worksheets/sheet1.xml',
'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetViews><sheetView workbookViewId="0" rightToLeft="1"/></sheetViews>
<cols>
  <col min="1" max="1" width="24" customWidth="1"/>
  <col min="2" max="2" width="14" customWidth="1"/>
  <col min="3" max="3" width="16" customWidth="1"/>
  <col min="4" max="4" width="14" customWidth="1"/>
  <col min="5" max="5" width="14" customWidth="1"/>
  <col min="6" max="6" width="28" customWidth="1"/>
</cols>
<sheetData>' . $sd . '</sheetData>
</worksheet>');

$zip->close();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode('قالب_المخزون.xlsx'));
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: no-cache, no-store');
readfile($tmp);
unlink($tmp);
exit;
