<?php
require __DIR__ . '/functions.php';
$config = include_once __DIR__ . '/config.php';

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

    $headers = apache_request_headers();

    if (!isset($headers['Signature'])) {
        header('HTTP/1.1 401 Unauthorized');
        logRequest(__LINE__, 'missing Signature header');
        exit;
    }

    //load config from travis
    $travisConfigJson = file_get_contents('https://api.travis-ci.org/config');
    $travisConfig = json_decode($travisConfigJson, true);

    $verification = openssl_verify(
        trim($_POST['payload']),
        base64_decode($headers['Signature']),
        $travisConfig['config']['notifications']['webhook']['public_key']
    );
    if (1 !== $verification) {
        header('HTTP/1.1 401 Unauthorized');
        logRequest(__LINE__, $verification === 0 ? 'provided Signature is invalid' : 'error while Signature validation');
        exit;
    }

    $payload = json_decode($_POST['payload'], true);

    if (!isset($payload['repository']['owner_name']) || !isset($payload['repository']['name'])) {
        header('HTTP/1.1 400 Bad Request');
        logRequest(__LINE__, 'missing owner or/and repository name');
        exit;
    }

    if (!isset($headers['Travis-Repo-Slug']) || $headers['Travis-Repo-Slug'] !== 'IlchCMS/Ilch-2.0') {
        logRequest(__LINE__, 'invalid repo slug');
        exit;
    }

    if ($payload['branch'] === 'master' && $payload['type'] === 'push') {
        createArchive($payload['branch']);
    } else {
        logRequest(__LINE__, 'no master push');
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
}
