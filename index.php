<?php
require __DIR__ . '/functions.php';
$config = include_once __DIR__ . '/config.php';

if ('POST' === $_SERVER['REQUEST_METHOD']) {
    if (!isset($_POST['payload'])) {
        header('HTTP/1.1 400 Bad Request');
        logRequest('no payload in request', __FILE__, __LINE__);
        exit;
    }

    $headers = apache_request_headers();

    if (!isset($headers['Signature'])) {
        header('HTTP/1.1 401 Unauthorized');
        logRequest('missing Signature header', __FILE__, __LINE__);
        exit;
    }

    //load config from travis
    $travisConfigJson = file_get_contents('https://api.travis-ci.com/config');
    $travisConfig = json_decode($travisConfigJson, true);

    $verification = openssl_verify(
        trim($_POST['payload']),
        base64_decode($headers['Signature']),
        $travisConfig['config']['notifications']['webhook']['public_key']
    );
    if (1 !== $verification) {
        header('HTTP/1.1 401 Unauthorized');
        logRequest(
            $verification === 0 ? 'provided Signature is invalid' : 'error while Signature validation',
            __FILE__,
            __LINE__
        );
        exit;
    }

    $payload = json_decode($_POST['payload'], true);

    if (!isset($payload['repository']['owner_name']) || !isset($payload['repository']['name'])) {
        header('HTTP/1.1 400 Bad Request');
        logRequest('missing owner or/and repository name', __FILE__, __LINE__);
        exit;
    }

    if (!isset($headers['Travis-Repo-Slug']) || $headers['Travis-Repo-Slug'] !== 'IlchCMS/Ilch-2.0') {
        logRequest('invalid repo slug', __FILE__, __LINE__);
        exit;
    }

    if ($payload['type'] === 'push'
        && (in_array($payload['branch'], $config['allowedBranches'])
            || $payload['branch'] === $payload['tag']
        )
    ) {
        createArchive($payload['branch'], $payload['commit'], $payload['tag']);
    } else {
        logRequest(
            'no push of a allowed branch (' . implode(', ', $config['allowedBranches']) . ')',
            __FILE__,
            __LINE__
        );
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
}
