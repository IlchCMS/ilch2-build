<?php
$travisToken = include_once __DIR__ . '/token.php';

if('POST' === $_SERVER['REQUEST_METHOD']) {
        if (!isset($_POST['payload'])) {
                header('HTTP/1.1 400 Bad Request');
                exit;
        }

        $payload = json_decode($_POST['payload'], true);

        if (!isset($payload['repository']['owner_name']) || !isset($payload['repository']['name'])) {
                header('HTTP/1.1 400 Bad Request');
                exit;
        }

        $headers = apache_request_headers();

        if (!isset($headers['Authorization'])) {
                header('HTTP/1.1 401 Unauthorized');
                exit;
        }

        $repoSlug = $payload['repository']['owner_name'] . '/' . $payload['repository']['name'];
        $authorization = hash('sha256', $repoSlug.$travisToken);

        if ($headers['Authorization'] !== $authorization) {
                header('HTTP/1.1 401 Unauthorized');
                exit;
        }

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
        header('HTTP/1.1 405 Method Not Allowed');
}