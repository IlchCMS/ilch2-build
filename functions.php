<?php

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

function createArchive($branch)
{
    global $config;

    $archive = $branch . '_tmp.zip';
    $fp = fopen($archive, 'w+');
    $ch = curl_init('https://codeload.github.com/IlchCMS/Ilch-2.0/zip/' . $branch);
    curl_setopt($ch, CURLOPT_TIMEOUT, 50);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    $composerInstall = $config['phpBin'] . ' ' . $config['composer'] . ' install --no-dev --optimize-autoloader';

    shell_exec('unzip ' . $archive);
    unlink($archive);
    shell_exec('cd Ilch-2.0-' . $branch . '; ' . $composerInstall . '; zip -r ../' . $archive . ' .; cd ..');
    rename($archive, $branch . '.zip');
    shell_exec('rm -R Ilch-2.0-' . $branch);
}
