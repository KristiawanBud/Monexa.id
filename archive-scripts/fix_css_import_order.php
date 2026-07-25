<?php

$file = '/var/www/monexa/resources/css/app.css';

if (! file_exists($file)) {
    echo "SKIP: file tidak ditemukan\n";
    exit;
}

$content = file_get_contents($file);

// Cari baris @import fonts
preg_match('/@import url\([^)]+fonts\.googleapis[^)]+\);/', $content, $matches);

if (empty($matches)) {
    echo "⚠️  Baris @import fonts tidak ketemu — cek manual\n";
    exit;
}

$importLine = $matches[0];

// Hapus @import dari posisi lamanya
$content = str_replace($importLine, '', $content);

// Hapus tailwind directives dari posisi lamanya (biar bisa disusun ulang)
$content = str_replace("@tailwind base;\n@tailwind components;\n@tailwind utilities;\n\n", '', $content);

// Susun ulang: @import PALING ATAS, baru @tailwind, baru sisanya
$newContent = $importLine."\n\n@tailwind base;\n@tailwind components;\n@tailwind utilities;\n\n".ltrim($content);

copy($file, $file.'.bak_'.date('Ymd_His'));
file_put_contents($file, $newContent);

echo "OK: urutan @import & @tailwind sudah dibenerin\n";
