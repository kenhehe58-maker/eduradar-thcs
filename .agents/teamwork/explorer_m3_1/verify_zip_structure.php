<?php
$file = __DIR__ . '/sample_students.xlsx';
$zip = new ZipArchive();
if ($zip->open($file) !== true) {
    die("Failed to open $file\n");
}
echo "Archive entries for $file:\n";
for ($i = 0; $i < $zip->numFiles; $i++) {
    $stat = $zip->statIndex($i);
    printf(" - %-30s (size: %4d, comp: %4d)\n", $stat['name'], $stat['size'], $stat['comp_size']);
}
$zip->close();
