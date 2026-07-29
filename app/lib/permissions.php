<?php
/**
 * Role-based access control (RBAC).
 * Checks admin roles and permissions.
 */

declare(strict_types=1);

/**
 * Get current admin's role (full role object).
 */
function get_current_admin_role(PDO $pdo): ?array {
    if (empty($_SESSION['admin_id'])) {
        return null;
    }

    try {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT r.* FROM admin_roles r
            JOIN admin_users a ON a.admin_role_id = r.id
            WHERE a.id = ?
        SQL);
        $stmt->execute([$_SESSION['admin_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Check if current admin has a specific role.
 */
function has_role(PDO $pdo, string $roleName): bool {
    $role = get_current_admin_role($pdo);
    return $role && $role['name'] === $roleName;
}

/**
 * Check if current admin is full admin.
 */
function is_admin(PDO $pdo): bool {
    return has_role($pdo, 'admin');
}

/**
 * Check if current admin can upload photos.
 */
function can_upload(PDO $pdo): bool {
    $role = get_current_admin_role($pdo);
    if (!$role) return false;
    return in_array($role['name'], ['admin', 'uploader']);
}

/**
 * Check if current admin can manage events.
 */
function can_manage_events(PDO $pdo): bool {
    $role = get_current_admin_role($pdo);
    if (!$role) return false;
    return in_array($role['name'], ['admin', 'uploader']);
}

/**
 * Check if current admin can manage admins.
 */
function can_manage_admins(PDO $pdo): bool {
    return is_admin($pdo);
}

/**
 * Check if current admin can view settings.
 */
function can_view_settings(PDO $pdo): bool {
    return is_admin($pdo);
}

/**
 * Check if current admin can export data.
 */
function can_export(PDO $pdo): bool {
    return is_admin($pdo);
}

/**
 * Check if current admin can view analytics.
 */
function can_view_analytics(PDO $pdo): bool {
    $role = get_current_admin_role($pdo);
    if (!$role) return false;
    return in_array($role['name'], ['admin', 'uploader']);
}

/**
 * Check if current admin can view audit log.
 */
function can_view_audit_log(PDO $pdo): bool {
    return is_admin($pdo);
}

/**
 * Check if current admin can manage print orders.
 */
function can_manage_print_orders(PDO $pdo): bool {
    return is_admin($pdo);
}

/**
 * Require admin role (throw 403 if not).
 */
function require_admin_role(PDO $pdo): void {
    if (!is_admin($pdo)) {
        http_response_code(403);
        echo '403 Forbidden: Admin access required';
        exit;
    }
}

/**
 * Require uploader role or higher (throw 403 if not).
 */
function require_uploader_role(PDO $pdo): void {
    if (!can_upload($pdo)) {
        http_response_code(403);
        echo '403 Forbidden: Uploader access required';
        exit;
    }
}

/**
 * Get all admin roles.
 */
function get_all_roles(PDO $pdo): array {
    try {
        $stmt = $pdo->query('SELECT id, name, permissions, created_at FROM admin_roles ORDER BY id');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get all admins with their roles.
 */
function get_all_admins(PDO $pdo): array {
    try {
        $stmt = $pdo->query(<<<'SQL'
            SELECT a.*, r.display_name as role_name
            FROM admin_users a
            LEFT JOIN admin_roles r ON a.admin_role_id = r.id
            ORDER BY a.created_at DESC
        SQL);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Create a new admin user.
 */
function create_admin(PDO $pdo, string $email, string $password, int $roleId): bool {
    try {
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        $stmt = $pdo->prepare(<<<'SQL'
            INSERT INTO admin_users (email, password_hash, admin_role_id)
            VALUES (?, ?, ?)
        SQL);
        $stmt->execute([$email, $hash, $roleId]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Update admin role.
 */
function update_admin_role(PDO $pdo, int $adminId, int $roleId): bool {
    try {
        $stmt = $pdo->prepare('UPDATE admin_users SET admin_role_id = ? WHERE id = ?');
        $stmt->execute([$roleId, $adminId]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Delete an admin.
 */
function delete_admin(PDO $pdo, int $adminId): bool {
    try {
        // Don't allow deleting the last admin
        $stmt = $pdo->query('SELECT COUNT(*) FROM admin_users');
        if ((int)$stmt->fetchColumn() <= 1) {
            return false;
        }

        $stmt = $pdo->prepare('DELETE FROM admin_users WHERE id = ?');
        $stmt->execute([$adminId]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
