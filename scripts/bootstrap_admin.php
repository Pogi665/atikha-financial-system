<?php
require_once __DIR__ . '/cli_common.php';
require_once __DIR__ . '/../includes/admin_bootstrap.php';
try {
    $options=getopt('',['database:']);$pdo=cli_db($options['database']??'');
    cli_require((int)$pdo->query('SELECT COUNT(*) FROM Users')->fetchColumn()===0,'Bootstrap refused: Users already exist.');
    cli_require(function_exists('stream_isatty') && stream_isatty(STDIN),'Interactive console required; redirected passwords are refused.');
    fwrite(STDOUT,'Full name: ');$name=trim(fgets(STDIN));
    fwrite(STDOUT,'Email: ');$email=trim(fgets(STDIN));
    $readSecret=static function(string $prompt): string {
        fwrite(STDERR,$prompt);
        if(PHP_OS_FAMILY==='Windows') {
            $p=proc_open(['powershell.exe','-NoProfile','-ExecutionPolicy','Bypass','-File',__DIR__.'/read_secret.ps1'],[0=>STDIN,1=>['pipe','w'],2=>STDERR],$pipes);
            cli_require(is_resource($p),'Secure console reader unavailable.');
            $secret=stream_get_contents($pipes[1]);fclose($pipes[1]);$code=proc_close($p);fwrite(STDERR,"\n");
            cli_require($code===0,'Secure console reader failed.');return $secret;
        }
        exec('stty -g',$state,$code);cli_require($code===0 && isset($state[0]),'Secure console reader unavailable.');
        exec('stty -echo',$unused,$code);cli_require($code===0,'Cannot disable console echo.');
        try {return rtrim(fgets(STDIN),"\r\n");} finally {exec('stty '.escapeshellarg($state[0]));fwrite(STDERR,"\n");}
    };
    $password=$readSecret('Password (hidden): ');$confirm=$readSecret('Confirm password (hidden): ');
    cli_require(hash_equals($password,$confirm),'Passwords do not match.');
    $id=bootstrap_admin($pdo,$name,$email,$password);unset($password,$confirm);
    echo "Initial Admin created (UserID $id). Credentials were not logged.\n";
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
