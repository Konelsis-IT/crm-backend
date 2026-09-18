<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

echo "upload_tmp_dir (ini)  : " . (ini_get('upload_tmp_dir') ?: '(bos)') . PHP_EOL;
echo "sys_get_temp_dir()    : " . sys_get_temp_dir() . PHP_EOL;
echo "open_basedir          : " . (ini_get('open_basedir') ?: '(kisitlama yok)') . PHP_EOL;
echo "upload_max_filesize   : " . ini_get('upload_max_filesize') . PHP_EOL;
echo "post_max_size         : " . ini_get('post_max_size') . PHP_EOL;
echo "memory_limit          : " . ini_get('memory_limit') . PHP_EOL;
echo "php sapi              : " . PHP_SAPI . PHP_EOL;

$dir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
echo "hedef klasor          : $dir" . PHP_EOL;
echo "  var mi              : " . (is_dir($dir) ? 'evet' : 'HAYIR') . PHP_EOL;
echo "  yazilabilir mi      : " . (is_writable($dir) ? 'evet' : 'HAYIR') . PHP_EOL;

$test = @tempnam($dir, 'ks_test_');
echo "  gecici dosya denemesi: " . ($test ? 'BASARILI' : 'BASARISIZ') . PHP_EOL;

if ($test) {
    @unlink($test);
}

$proj = dirname(__DIR__) . '/storage/app/upload-tmp';
echo "storage/app/upload-tmp: " . (is_dir($proj) ? (is_writable($proj) ? 'var ve yazilabilir' : 'var ama YAZILAMIYOR') : 'yok') . PHP_EOL;

$private = dirname(__DIR__) . '/storage/app/private';
echo "storage/app/private   : " . (is_dir($private) ? (is_writable($private) ? 'var ve yazilabilir' : 'var ama YAZILAMIYOR') : 'yok') . PHP_EOL;

echo "calisan kullanici     : " . (function_exists('posix_getpwuid') && function_exists('posix_geteuid')
    ? (posix_getpwuid(posix_geteuid())['name'] ?? '(bilinmiyor)')
    : '(posix uzantisi yok)') . PHP_EOL;
