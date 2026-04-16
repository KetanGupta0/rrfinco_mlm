<?php
/**
 * Admin common helpers.
 */

if (!function_exists('recordAuditLog')) {
    function recordAuditLog($adminId, $action, $entityType, $entityId = null, $oldValue = null, $newValue = null) {
        global $pdo;

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, old_value, new_value, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $adminId,
                $action,
                $entityType,
                $entityId,
                $oldValue === null ? null : json_encode($oldValue, JSON_UNESCAPED_UNICODE),
                $newValue === null ? null : json_encode($newValue, JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
        } catch (Throwable $e) {
            logError('Admin audit log failed', $e->getMessage());
        }
    }
}

function adminGetSortableColumn($requested, array $allowed, $default) {
    return array_key_exists($requested, $allowed) ? $requested : $default;
}

function adminGetSortDirection($requested, $default = 'desc') {
    $requested = strtolower((string) $requested);
    return in_array($requested, ['asc', 'desc'], true) ? $requested : $default;
}

function adminQueryString(array $overrides = [], array $remove = []) {
    $params = $_GET;

    foreach ($remove as $key) {
        unset($params[$key]);
    }

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    $query = http_build_query($params);
    return $query !== '' ? '?' . $query : '';
}

function adminLike($value) {
    return '%' . $value . '%';
}

function adminStatusClass($status) {
    $map = [
        'active' => 'success',
        'completed' => 'success',
        'verified' => 'success',
        'closed' => 'success',
        'matured' => 'primary',
        'admin' => 'primary',
        'open' => 'info',
        'pending' => 'warning',
        'in_progress' => 'warning',
        'inactive' => 'muted',
        'cancelled' => 'muted',
        'suspended' => 'danger',
        'rejected' => 'danger',
        'failed' => 'danger',
    ];

    return $map[$status] ?? 'muted';
}

function adminPriorityClass($priority) {
    $map = [
        'critical' => 'danger',
        'high' => 'warning',
        'medium' => 'info',
        'low' => 'primary',
    ];

    return $map[$priority] ?? 'muted';
}

function adminText($value, $fallback = 'NA') {
    $value = trim((string) ($value ?? ''));
    return $value !== '' ? htmlspecialchars($value) : $fallback;
}

function adminDateTime($value, $fallback = 'NA') {
    if (empty($value)) {
        return $fallback;
    }

    $timestamp = strtotime((string) $value);
    return $timestamp ? date('d M Y, h:i A', $timestamp) : $fallback;
}

function adminDate($value, $fallback = 'NA') {
    if (empty($value)) {
        return $fallback;
    }

    $timestamp = strtotime((string) $value);
    return $timestamp ? date('d M Y', $timestamp) : $fallback;
}

function adminPercent($value, $decimals = 2) {
    return number_format((float) $value, $decimals) . '%';
}
