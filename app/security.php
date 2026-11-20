<?php
declare(strict_types=1);

function passwordAlgorithm(): string
{
    return in_array('argon2id', password_algos(), true) ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
}

function hashAdminPassword(string $password): string
{
    return password_hash($password, passwordAlgorithm());
}

function validNewPassword(string $password): bool
{
    return strlen($password) <= 72
        && !str_contains($password, "\0")
        && preg_match('/\A.{15,}\z/su', $password) === 1
        && trim($password) !== '';
}

function startAdminSession(): void
{
    $path = dataDirectory() . '/sessions';
    ensurePrivateDirectory($path);
    session_name(isHttps() ? '__Host-PlugySession' : 'PlugyLocalSession');
    if (!session_start([
        'save_handler' => 'files',
        'save_path' => $path,
        'use_strict_mode' => 1,
        'use_only_cookies' => 1,
        'use_cookies' => 1,
        'use_trans_sid' => 0,
        'cookie_lifetime' => 0,
        'cookie_path' => '/',
        'cookie_domain' => '',
        'cookie_secure' => isHttps(),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'cache_limiter' => '',
        'gc_probability' => 1,
        'gc_divisor' => 100,
        'gc_maxlifetime' => 28800,
    ])) {
        throw new RuntimeException('Unable to open admin session.');
    }
}

function csrfToken(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function beginAdminSession(array $account, ?string $message = null): void
{
    // The old session contains no authenticated state when the ID changes.
    $_SESSION = [];
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Unable to rotate admin session.');
    }
    $_SESSION = [
        'version' => (int) $account['session_version'],
        'started' => time(),
        'seen' => time(),
    ];
    if ($message !== null) {
        $_SESSION['notice'] = $message;
    }
}

function adminAccount(PDO $db): array
{
    return $db->query('SELECT password_hash, demo, session_version FROM admin WHERE id = 1')->fetch();
}

function isAuthenticated(array $account): bool
{
    if (!isset($_SESSION['version'])) {
        return false;
    }
    $now = time();
    if ($_SESSION['version'] !== (int) $account['session_version']
        || $_SESSION['started'] <= $now - 8 * 60 * 60
        || $_SESSION['seen'] <= $now - 30 * 60
        || ((int) $account['demo'] === 1 && !isLocalDevelopment())) {
        $_SESSION = [];
        return false;
    }
    $_SESSION['seen'] = $now;
    return true;
}

function endAdminSession(): void
{
    $_SESSION = [];
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $cookie['path'],
        'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'],
        'samesite' => $cookie['samesite'],
    ]);
    session_destroy();
}

function loginScopes(): array
{
    $address = $_SERVER['REMOTE_ADDR'] ?? '';
    $addressBytes = inet_pton($address);
    return ['account' => 20, 'ip:' . hash('sha256', $addressBytes === false ? '' : $addressBytes) => 5];
}

function reserveLoginAttempt(PDO $db, array $scopes): int
{
    return writeTransaction($db, static function () use ($db, $scopes): int {
        $now = time();
        $db->prepare('DELETE FROM login_limits WHERE expires <= ?')->execute([$now]);
        $read = $db->prepare('SELECT attempts, expires FROM login_limits WHERE scope = ?');
        $retryAfter = 0;
        foreach ($scopes as $scope => $maximum) {
            $read->execute([$scope]);
            $bucket = $read->fetch();
            if ($bucket && (int) $bucket['attempts'] >= $maximum) {
                $retryAfter = max($retryAfter, (int) $bucket['expires'] - $now);
            }
        }
        if ($retryAfter > 0) {
            return $retryAfter;
        }
        $increment = $db->prepare('INSERT INTO login_limits (scope, attempts, expires) VALUES (?, 1, ?)
            ON CONFLICT (scope) DO UPDATE SET attempts = attempts + 1');
        foreach (array_keys($scopes) as $scope) {
            $increment->execute([$scope, $now + 900]);
        }
        return 0;
    });
}

function authenticate(PDO $db, array $account, string $username, string $password): bool
{
    $scopes = loginScopes();
    $retryAfter = reserveLoginAttempt($db, $scopes);
    if ($retryAfter > 0) {
        header('Retry-After: ' . $retryAfter);
        http_response_code(429);
        return false;
    }

    if (!verifyAdminPassword($password, $account['password_hash']) || !hash_equals('admin', $username)) {
        http_response_code(401);
        return false;
    }

    $db->prepare('DELETE FROM login_limits WHERE scope IN (?, ?)')->execute(array_keys($scopes));
    if (password_needs_rehash($account['password_hash'], passwordAlgorithm())) {
        $db->prepare('UPDATE admin SET password_hash = ? WHERE id = 1 AND session_version = ? AND password_hash = ?')
            ->execute([hashAdminPassword($password), $account['session_version'], $account['password_hash']]);
    }
    beginAdminSession($account);
    return true;
}

function verifyAdminPassword(string $password, string $hash): bool
{
    return $password !== '' && strlen($password) <= 72 && !str_contains($password, "\0")
        && password_verify($password, $hash);
}

function changeAdminPassword(PDO $db, array $account): void
{
    $current = post('current_password');
    $new = post('new_password');
    if (!verifyAdminPassword($current, $account['password_hash'])) {
        throw new ValidationException('Укажите действующий пароль.');
    }
    if (!validNewPassword($new) || hash_equals($current, $new)) {
        throw new ValidationException('Введите новый пароль от 15 символов и до 72 байт. Для длинного пароля используйте латиницу.');
    }
    if (!hash_equals($new, post('repeat_password'))) {
        throw new ValidationException('Новый пароль и его повтор не совпадают.');
    }

    $update = $db->prepare('UPDATE admin SET password_hash = ?, demo = 0, session_version = session_version + 1
        WHERE id = 1 AND session_version = ?');
    $update->execute([hashAdminPassword($new), $account['session_version']]);
    if ($update->rowCount() !== 1) {
        throw new ValidationException('Пароль уже изменился. Войдите снова и повторите действие.');
    }
    $account['session_version']++;
    beginAdminSession($account, 'Пароль обновлён. Остальные сессии завершены.');
}
