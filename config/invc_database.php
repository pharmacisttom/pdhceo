<?php
declare(strict_types=1);

function invc_current_fiscal_year(): int
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    $year = (int)$now->format('Y');
    $month = (int)$now->format('n');
    return ($month >= 10 ? $year + 1 : $year) + 543;
}

function invc_get_pdo(): PDO
{
    static $invcPdo = null;
    if ($invcPdo instanceof PDO) {
        return $invcPdo;
    }

    $serverIps = ['192.168.111.240'];
    $machineIp = gethostbyname(gethostname());
    $currentHost = $_SERVER['HTTP_HOST'] ?? '';
    $currentServerAddr = $_SERVER['SERVER_ADDR'] ?? ($_SERVER['LOCAL_ADDR'] ?? '');
    $isServer = in_array($machineIp, $serverIps, true)
        || in_array($currentServerAddr, $serverIps, true)
        || strpos($currentHost, '192.168.111.240') === 0;

    $envHost = getenv('PDH_INVC_DB_HOST');
    $hosts = $envHost !== false
        ? [(string)$envHost]
        : ($isServer ? ['localhost', '127.0.0.1'] : ['192.168.111.240']);
    $db = getenv('PDH_INVC_DB_NAME') ?: 'himtoinvc';
    if (getenv('PDH_INVC_DB_USER') !== false) {
        $user = (string)getenv('PDH_INVC_DB_USER');
        $pass = (string)getenv('PDH_INVC_DB_PASS');
    } elseif ($isServer) {
        $user = 'webtomdb';
        $pass = '@TOM$DataBase10832';
    } else {
        $user = 'tomwebdbnavicat';
        $pass = '@TOM$NavicatDB10832';
    }

    $lastError = null;
    foreach ($hosts as $host) {
        try {
            $invcPdo = new PDO(
                "mysql:host={$host};dbname={$db};charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            break;
        } catch (PDOException $e) {
            $lastError = $e;
            $invcPdo = null;
        }
    }

    if (!$invcPdo instanceof PDO) {
        throw $lastError ?? new RuntimeException('Cannot connect to INVC cache database');
    }
    $invcPdo->exec("SET time_zone = '+07:00'");

    return $invcPdo;
}
