<?php
$config = include_once __DIR__ . '/config.php';

function logRequest($errorLine = null, $message = null)
{
    global $config;
    if (!isset($config['logDir']) || !is_dir($config['logDir']) || !is_writable($config['logDir'])) {
        return;
    }
    $logFile = $config['logDir'] . '/' . date('Ymd-His') . '.log';
    $fh = fopen($logFile, 'w');
    if (!empty($errorLine)) {
        fwrite($fh, sprintf("Error in file %s line %d\n", __FILE__, $errorLine));
    }
    if (!empty($message)) {
        fwrite($fh, sprintf("ErrorMessage: %s\n", $message));
    }
    fwrite($fh, sprintf("Headers: %s\nPayload: %s\n", print_r(apache_request_headers(), true), $_POST['payload']));
    fclose($fh);
}

if (!isset($config['token']) || !isset($config['fullToken'])) {
    header('HTTP/1.1 500 Application Error');
    logRequest(__LINE__, 'Token is missing in config');
    exit;
}

if ('POST' === $_SERVER['REQUEST_METHOD']) {
    if (!isset($_POST['payload'])) {
        header('HTTP/1.1 400 Bad Request');
        logRequest(__LINE__, 'no payload in request');
        exit;
    }

    $payload = json_decode($_POST['payload'], true);

    if (!isset($payload['repository']['owner_name']) || !isset($payload['repository']['name'])) {
        header('HTTP/1.1 400 Bad Request');
        logRequest(__LINE__, 'missing owner or/and repository name');
        exit;
    }

    $headers = apache_request_headers();

    if (!isset($headers['Authorization'])) {
        header('HTTP/1.1 401 Unauthorized');
        logRequest(__LINE__, 'missing authorization header');
        exit;
    }

    $possibleTokens = [];

    if (!empty($config['fullToken'])) {
        $possibleTokens[] = $config['fullToken'];
    }

    $repoSlug = $payload['repository']['owner_name'] . '/' . $payload['repository']['name'];
    $possibleTokens[] = hash('sha256', $repoSlug . $config['token']);

    if (!in_array($headers['Authorization'], $possibleTokens)) {
        header('HTTP/1.1 401 Unauthorized');
        logRequest(__LINE__, 'provided authorization is invalid');
        exit;
    }

    if (!isset($headers['Travis-Repo-Slug']) || $headers['Travis-Repo-Slug'] !== 'IlchCMS/Ilch-2.0') {
        logRequest(__LINE__, 'invalid repo slug');
        exit;
    }

    if ($payload['branch'] === 'master' && $payload['type'] === 'push') {
        $fp = fopen('master_tmp.zip', 'w+');
        $ch = curl_init('https://codeload.github.com/IlchCMS/Ilch-2.0/zip/master');
        curl_setopt($ch, CURLOPT_TIMEOUT, 50);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        shell_exec('unzip master_tmp.zip');
        unlink('master_tmp.zip');
        shell_exec('cd Ilch-2.0-master; rm -R tests; zip -r ../master_tmp.zip .; cd ..');
        rename('master_tmp.zip', 'master.zip');
        shell_exec('rm -R Ilch-2.0-master;');
    } else {
        logRequest(__LINE__, 'no master push');
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
}
