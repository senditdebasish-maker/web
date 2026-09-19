<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);
if (!is_file($root.'/vendor/autoload.php')) { fwrite(STDERR,"Install dependencies with composer install --no-dev first.\n"); exit(1); }
if (!class_exists(ZipArchive::class)) { fwrite(STDERR,"Enable the PHP zip extension to build a release.\n"); exit(1); }
require $root.'/vendor/autoload.php';
if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class) || !class_exists(\Dompdf\Dompdf::class)) { fwrite(STDERR,"Mail/PDF dependencies are incomplete.\n"); exit(1); }
$dir=$root.'/releases';
if (!is_dir($dir)) mkdir($dir,0700,true);
$path=$dir.'/northstar-setup.zip';
$zip=new ZipArchive();
if ($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) { fwrite(STDERR,"Cannot create release ZIP.\n"); exit(1); }
$files=['.htaccess','README.md','START-HERE.html','config.example.php','composer.json','storage/.gitkeep','storage/.htaccess'];
if (is_file($root.'/composer.lock')) $files[]='composer.lock';
foreach (['app','bin','public','docs','vendor'] as $dir) {
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir,FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $file) {
        if (!$file->isFile() || $file->isLink()) continue;
        $relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
        if (preg_match('~/(?:\.git|\.github|node_modules|tests?|examples?|\.cache)(?:/|$)~i',$relative)) continue;
        $files[]=$relative;
    }
}
foreach($files as $file) {
    if (!is_file($root.'/'.$file)) continue;
    if (!$zip->addFile($root.'/'.$file,'institute-crm/'.$file)) { $zip->close(); throw new RuntimeException('Could not package a required file.'); }
}
$zip->setArchiveComment('Northstar Institute CRM - browser setup package. No configuration, credentials, databases or mail captures included.');
if (!$zip->close()) throw new RuntimeException('Could not finish ZIP.');
echo "Release created: $path\nExtract the institute-crm folder into XAMPP htdocs, then open public/setup.php.\n";
