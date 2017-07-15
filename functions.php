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

/**
 * @param string $branch
 * @param string $commit
 * @param string|null $tag
 */
function createArchive($branch, $commit, $tag = null)
{
    global $config;

    if (preg_match('~^v?\d+(?:\.\d+){0,2}$~',$tag) === 1) {
        $archiveName = $tag;
    } else {
        $archiveName = array_search($branch, $config['allowedBranches']);
        if (!is_string($archiveName)) {
            $archiveName = $branch;
        }
    }

    $archive = $commit . '_tmp.zip';
    if (!file_exists($archive)) {
        $fp = fopen($archive, 'w+');
        $ch = curl_init('https://codeload.github.com/IlchCMS/Ilch-2.0/zip/' . $commit);
        curl_setopt($ch, CURLOPT_TIMEOUT, 50);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
    }

    $composer = $config['phpBin'] . ' ' . $config['composer'];
    if (!empty($config['composerHome'])) {
        $composer = 'COMPOSER_HOME=' . escapeshellarg($config['composerHome']) . ' ' . $composer;
    }

    $commands = array(
        'unzip ' . $archive,
        'rm ' . $archive,
        'cd Ilch-2.0-' . $commit,
        $composer . ' install --no-dev --optimize-autoloader --no-interaction',
        'if [ -f build/optimize_vendor.php ]; then ' . $config['phpBin'] . ' build/optimize_vendor.php; fi',
        'zip -r ../' . $archiveName . '_NEW_.zip .',
        'mv ../' . $archiveName . '_NEW_.zip ../' . $archiveName . '.zip',
        'cd ..',
        'rm -r Ilch-2.0-' . $commit
    );

    $cmdLine = implode(' && ', $commands);

    if (isset($config['cmd'])) {
        $cmdLine = sprintf($config['cmd'], $cmdLine);
    }
    shell_exec($cmdLine);
}
