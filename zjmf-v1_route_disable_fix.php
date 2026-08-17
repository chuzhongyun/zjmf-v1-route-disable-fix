<?php
/**
 * zjmf-v1_route_disable_fix
 * 作者：CHUZHONG (https://github.com/chuzhongyun)
 *
 * 修复思路（2026-08 调整）：
 * 0 元购的根因是结算/升级逻辑无条件信任客户端传入的折扣参数
 * （resource_percent_value），而非 /v1 接口本身。
 * 因此默认改为「参数级拦截」：任何外部请求携带该折扣参数即 400，
 * /v1 及其它接口的正常调用（对接方）完全不受影响。
 *
 * 如需同时整体关闭 /v1 开放接口，将下方 $disableV1 改为 true。
 */

if (!defined('CMF_ROOT')) {
    return;
}

(function () {
    $reject = static function ($statusCode, $msg) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(
            ['status' => $statusCode, 'msg' => $msg],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    };

    // ---------- 1. 参数级拦截（默认开启）：不影响正常对接 ----------
    $percentParam = 'resource_percent_value';
    $hitSources = [];

    if (isset($_GET[$percentParam])) {
        $hitSources[] = 'query';
    }
    if (isset($_POST[$percentParam])) {
        $hitSources[] = 'form';
    }

    // JSON body（Content-Type: application/json 时 $_POST 为空，需直接读 body）
    if (empty($hitSources)) {
        $rawBody = file_get_contents('php://input');
        if (is_string($rawBody) && $rawBody !== '') {
            $body = json_decode($rawBody, true);
            if (is_array($body) && array_key_exists($percentParam, $body)) {
                $hitSources[] = 'json';
            }
        }
    }

    if (!empty($hitSources)) {
        $reject(400, 'Invalid discount parameter');
    }

    // ---------- 2. 路径级拦截（可选，默认关闭） ----------
    $disableV1 = false;
    if (!$disableV1) {
        return;
    }

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
            $reject(404, 'Not Found');
        }
    }
})();