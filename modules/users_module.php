<?php
// ============================================================
// modules/users_module.php — User Model
//
// Contains ALL database logic for system user accounts.
// No HTML, no session checks — pure data functions.
// Called by: public/users.php
// ============================================================

/**
 * Fetch all users with their linked doctor name (LEFT JOIN).
 */
function getUsers($pdo) {
    return $pdo->query("
        SELECT u.*, d.first_name AS doc_first, d.last_name AS doc_last
        FROM users u
        LEFT JOIN doctors d ON d.user_id = u.id
        ORDER BY u.role ASC, u.full_name ASC
    ")->fetchAll();
}

/**
 * Fetch a single user by primary key.
 */
function getUserById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Fetch doctors that have no linked user account yet (for the create user form).
 */
function getUnlinkedDoctors($pdo) {
    return $pdo->query("
        SELECT id, first_name, last_name, specialization
        FROM doctors WHERE user_id IS NULL ORDER BY last_name
    ")->fetchAll();
}

/**
 * Create a new user account. Returns array of error strings (empty = success).
 * If role is 'doctor', links the chosen doctor record to this user via user_id.
 */
function createUser($pdo, $full_name, $username, $password, $password2, $role, $doctor_id = null) {
    $errors = [];
    if (!$full_name)                              $errors[] = 'Full name is required.';
    if (!$username)                               $errors[] = 'Username is required.';
    if (strlen($password) < 6)                   $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $password2)                 $errors[] = 'Passwords do not match.';
    if (!in_array($role, ['admin','receptionist','doctor'])) $errors[] = 'Invalid role selected.';
    if ($role === 'doctor' && !$doctor_id)        $errors[] = 'Please select which doctor this account belongs to.';

    if (!$errors) {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) $errors[] = 'Username already exists. Choose a different one.';
    }

    if (!$errors) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (full_name, username, password, role) VALUES (?,?,?,?)");
        $stmt->execute([$full_name, $username, $hashed, $role]);
        $newUserId = $pdo->lastInsertId();
        if ($role === 'doctor' && $doctor_id) {
            $pdo->prepare("UPDATE doctors SET user_id=? WHERE id=?")->execute([$newUserId, $doctor_id]);
        }
    }
    return $errors;
}

/**
 * Update an existing user. Password is optional — blank = keep current.
 * Returns array of error strings (empty = success).
 */
function updateUser($pdo, $id, $full_name, $username, $password, $password2, $role, $current_user_id = null) {
    $errors = [];
    if (!$full_name) $errors[] = 'Full name is required.';
    if (!$username)  $errors[] = 'Username is required.';
    if (!in_array($role, ['admin','receptionist','doctor'])) $errors[] = 'Invalid role.';
    if ($password && strlen($password) < 6)   $errors[] = 'Password must be at least 6 characters.';
    if ($password && $password !== $password2) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $check = $pdo->prepare("SELECT id FROM users WHERE username=? AND id != ?");
        $check->execute([$username, $id]);
        if ($check->fetch()) $errors[] = 'Username already taken by another account.';
    }

    if (!$errors) {
        $current = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $current->execute([$id]);
        $current = $current->fetch();
        if (!$current) {
            $errors[] = 'User not found.';
        } else {
            $oldRole = $current['role'];
            $isDemotingAdmin = ($oldRole === 'admin' && $role !== 'admin');

            // Prevent self lockout by changing your own account from admin to non-admin.
            if ($isDemotingAdmin && $current_user_id !== null && (int)$id === (int)$current_user_id) {
                $errors[] = 'You cannot change your own role from Admin.';
            }

            // Prevent demoting the last remaining admin account.
            if ($isDemotingAdmin) {
                $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
                if ($adminCount <= 1) {
                    $errors[] = 'Cannot change role of the last administrator account.';
                }
            }
        }
    }

    if (!$errors) {
        if ($password) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET full_name=?, username=?, password=?, role=? WHERE id=?")->execute([$full_name, $username, $hashed, $role, $id]);
        } else {
            $pdo->prepare("UPDATE users SET full_name=?, username=?, role=? WHERE id=?")->execute([$full_name, $username, $role, $id]);
        }
    }
    return $errors;
}

/**
 * Delete a user account. Multiple safety checks:
 *   - Cannot delete yourself
 *   - Cannot delete the last admin
 *   - Doctor records are unlinked (not deleted) when user is removed
 * Returns error string or null on success.
 */
function deleteUser($pdo, $id, $current_user_id) {
    if ($id === (int)$current_user_id) return 'You cannot delete your own account.';

    $user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user->execute([$id]);
    $user = $user->fetch();
    if (!$user) return 'User not found.';

    if ($user['role'] === 'admin') {
        $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
        if ($adminCount <= 1) return 'Cannot delete the last administrator account. Create another admin first.';
    }

    // If this is a doctor account, block deletion when ANY appointment history exists.
    if ($user['role'] === 'doctor') {
        $docStmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
        $docStmt->execute([$id]);
        $doctor = $docStmt->fetch();
        if ($doctor) {
            $history = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ?");
            $history->execute([(int)$doctor['id']]);
            if ($history->fetchColumn() > 0) {
                return 'Cannot delete this doctor account. The linked doctor has appointment history.';
            }
        }
    }

    // Unlink doctor record but keep the doctor in the system
    $pdo->prepare("UPDATE doctors SET user_id=NULL WHERE user_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    return null;
}
