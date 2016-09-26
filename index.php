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

    $possibleTokens = array();

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
        createArchive($payload['branch']);
    } else {
        logRequest(__LINE__, 'no master push');
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
}
