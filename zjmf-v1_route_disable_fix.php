<?php
/**
 * zjmf-v1_route_disable_fix
 * 作者：CHUZHONG (https://github.com/chuzhongyun)
 */

if (!defined('CMF_ROOT')) {
    return;
}

(function () {
    $normalizePath = static function ($value) {
        if (!is_string($value) || $value === '') {
            return '';
        }

        for ($round = 0; $round < 3; $round++) {
            $decoded = rawurldecode($value);
            if ($decoded === $value) {
                break;
            }
            $value = $decoded;
        }

        $value = str_replace('\\', '/', $value);
        $value = preg_replace('#/+#', '/', $value);

        return is_string($value) ? rtrim($value, '/') : '';
    };

    $isV1Route = static function ($value) use ($normalizePath) {
        $path = $normalizePath($value);
        if ($path === '') {
            return false;
        }

        $path = preg_replace('#^/index\.php/#i', '/', $path);

        return preg_match('#^/v1(?:/|$)#i', $path) === 1;
    };

    $routeCandidates = [];
    $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $requestPath = parse_url($requestUri, PHP_URL_PATH);
    if (is_string($requestPath)) {
        $routeCandidates[] = $requestPath;
    }

    foreach (['PATH_INFO', 'ORIG_PATH_INFO', 'REDIRECT_PATH_INFO', 'REDIRECT_URL'] as $serverKey) {
        if (isset($_SERVER[$serverKey]) && is_string($_SERVER[$serverKey])) {
            $routeCandidates[] = $_SERVER[$serverKey];
        }
    }

    if (isset($_GET['s']) && is_string($_GET['s'])) {
        $routeCandidates[] = '/' . $_GET['s'];
    }

    foreach ($routeCandidates as $routeCandidate) {
        if ($isV1Route($routeCandidate)) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode(
                ['status' => 404, 'msg' => 'Not Found'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            exit;
        }
    }
})();