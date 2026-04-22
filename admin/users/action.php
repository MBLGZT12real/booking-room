<?php
require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
Auth::requireLogin();

if (!isPost() || !verifyCsrf()) {
    redirect(BASE_URL . '/admin/users/');
}

$db     = db();
$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

if ($action === 'delete') {
    Auth::requirePermission('users.delete');
    if ($id === Auth::id()) {
        flash('error', 'Tidak bisa menghapus akun sendiri.');
        redirect(BASE_URL . '/admin/users/');
    }

    $user = $db->fetch("SELECT name, role_id FROM users WHERE id = ?", [$id]);
    if (!$user) {
        flash('error', 'User tidak ditemukan.');
        redirect(BASE_URL . '/admin/users/');
    }

    // Prevent deleting last superadmin
    $superadminRoleId = $db->fetchColumn("SELECT id FROM roles WHERE slug = 'superadmin' LIMIT 1");
    if ($user['role_id'] == $superadminRoleId) {
        $superadminCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM users WHERE role_id = ? AND is_active = 1", [$superadminRoleId]);
        if ($superadminCount <= 1) {
            flash('error', 'Tidak bisa menghapus superadmin terakhir.');
            redirect(BASE_URL . '/admin/users/');
        }
    }

    $db->delete('users', ['id' => $id]);
    auditLog('delete', 'users', "Hapus user: {$user['name']}");
    flash('success', "User \"{$user['name']}\" berhasil dihapus.");

} elseif ($action === 'toggle') {
    Auth::requirePermission('users.edit');
    if ($id === Auth::id()) {
        flash('error', 'Tidak bisa menonaktifkan akun sendiri.');
        redirect(BASE_URL . '/admin/users/');
    }

    $user = $db->fetch("SELECT name, is_active FROM users WHERE id = ?", [$id]);
    if ($user) {
        $newStatus = $user['is_active'] ? 0 : 1;
        $db->update('users', ['is_active' => $newStatus], ['id' => $id]);
        $statusText = $newStatus ? 'diaktifkan' : 'dinonaktifkan';
        auditLog('update', 'users', "User {$user['name']} $statusText");
        flash('success', "User \"{$user['name']}\" berhasil $statusText.");
    }

} elseif ($action === 'reset_password') {
    Auth::requirePermission('users.edit');

    $user = $db->fetch("SELECT name, email FROM users WHERE id = ?", [$id]);
    if (!$user) {
        flash('error', 'User tidak ditemukan.');
        redirect(BASE_URL . '/admin/users/');
    }

    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 6) {
        flash('error', 'Password minimal 6 karakter.');
    } elseif ($newPassword !== $confirmPassword) {
        flash('error', 'Konfirmasi password tidak cocok.');
    } else {
        $hashed = Auth::hashPassword($newPassword);
        $db->update('users', ['password' => $hashed], ['id' => $id]);
        auditLog('update', 'users', "Reset password user: {$user['name']}");
        flash('success', "Password user \"{$user['name']}\" berhasil direset.");
    }
}

redirect(BASE_URL . '/admin/users/');
