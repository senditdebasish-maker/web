<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
$options=getopt('',['directory:','dump-binary:']);$tmp=null;$auth=null;$exitCode=0;
try {
    $dir=realpath((string)($options['directory']??''));$root=realpath(dirname(__DIR__));
    if(!$dir||!is_dir($dir)||!is_writable($dir))throw new RuntimeException('Supply --directory pointing to an existing writable PRIVATE directory outside all web roots.');
    $norm=static fn(string $p):string=>strtolower(str_replace('\\','/',rtrim($p,'/\\'))).'/';
    if(str_starts_with($norm($dir),$norm($root)))throw new RuntimeException('Backups cannot be stored inside the application project. Use a private directory outside every web root.');
    for($ancestor=dirname($root);$ancestor!==dirname($ancestor);$ancestor=dirname($ancestor)) {
        if(in_array(strtolower(basename($ancestor)),['htdocs','www','wwwroot','public_html'],true) && str_starts_with($norm($dir),$norm($ancestor)))throw new RuntimeException('Destination is under a recognized web root. Choose a private directory outside the web server.');
    }
    umask(0077);$driver=explode(':',(string)$config['dsn'],2)[0];$tmp=tempnam($dir,'.northstar-part-');if(!$tmp)throw new RuntimeException('Cannot allocate private backup file.');chmod($tmp,0600);
    if($driver==='sqlite')db()->exec('VACUUM INTO '.db()->quote($tmp));
    elseif($driver==='mysql') {
        $parts=[];foreach(explode(';',substr($config['dsn'],6)) as $part){$pair=explode('=',$part,2);if(count($pair)===2)$parts[$pair[0]]=$pair[1];}
        if(!isset($parts['unix_socket']) && !in_array(strtolower($parts['host']??'127.0.0.1'),['127.0.0.1','localhost','::1'],true))throw new RuntimeException('Remote database backups require your provider or TLS-verified native tools. This helper only supports local/socket MySQL connections.');
        $database=$parts['dbname']??'';if(!preg_match('/^[A-Za-z0-9_]+$/D',$database))throw new RuntimeException('Use native database tools for this nonstandard database name.');
        $quote=static fn(string $s):string=>'"'.str_replace(["\\","\"","\n","\r"],["\\\\","\\\"","\\n","\\r"],$s).'"';
        $auth=tempnam($dir,'.northstar-client-');if(!$auth)throw new RuntimeException('Cannot create private client options.');chmod($auth,0600);
        $client="[client]\nuser=".$quote((string)($config['user']??''))."\npassword=".$quote((string)($config['password']??''))."\n";
        if(isset($parts['unix_socket']))$client.='socket='.$quote($parts['unix_socket'])."\n";
        elseif(($parts['host']??'')==='localhost' && PHP_OS_FAMILY!=='Windows' && ini_get('pdo_mysql.default_socket'))$client.='socket='.$quote((string)ini_get('pdo_mysql.default_socket'))."\n";
        else {$client.='host='.$quote($parts['host']??'127.0.0.1')."\nport=".$quote($parts['port']??'3306')."\n";}
        if(file_put_contents($auth,$client)===false)throw new RuntimeException('Cannot write private client options.');
        $binary=(string)($options['dump-binary']??'mysqldump');
        // Argument array: no shell interpolation and no password in argv/environment/output.
        $cmd=[$binary,'--defaults-file='.$auth,'--single-transaction','--quick','--skip-lock-tables','--hex-blob','--no-tablespaces','--default-character-set=utf8mb4','--result-file='.$tmp,$database];
        $pipes=[];$p=proc_open($cmd,[0=>['pipe','r'],1=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($p))throw new RuntimeException('Could not start mysqldump. Supply --dump-binary for your trusted MySQL/MariaDB dump executable.');
        fclose($pipes[0]);while(!feof($pipes[2]))fread($pipes[2],8192);fclose($pipes[2]);
        if(proc_close($p)!==0)throw new RuntimeException('Database dump failed. Check the private client configuration, executable, permissions and connectivity. No finished backup was published.');
    }else throw new RuntimeException('Unsupported database driver.');
    clearstatcache(true,$tmp);if(filesize($tmp)===0)throw new RuntimeException('Refusing an empty backup.');
    $name='northstar-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).($driver==='sqlite'?'.sqlite':'.sql');$target=$dir.DIRECTORY_SEPARATOR.$name;
    $manifest=['format'=>'northstar-backup-v1','file'=>$name,'driver'=>$driver,'created_at'=>date(DATE_ATOM),'bytes'=>filesize($tmp),'sha256'=>hash_file('sha256',$tmp),'encrypted'=>false];
    if(!rename($tmp,$target))throw new RuntimeException('Cannot finalize backup.');$tmp=null;
    if(file_put_contents($target.'.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n")===false)throw new RuntimeException('Backup file was created, but its manifest could not be written. Inspect the private backup directory.');
    echo "Created private backup: $name\nSHA-256: ".$manifest['sha256']."\nNot encrypted. Encrypt and copy off-server; verify an isolated restore. Never restore directly over production.\n";
}catch(Throwable $e){fwrite(STDERR,'Backup failed: '.$e->getMessage()."\n");$exitCode=1;}
finally{if($tmp && is_file($tmp))unlink($tmp);if($auth && is_file($auth))unlink($auth);}

exit($exitCode);
