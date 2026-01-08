<?php
/**
 * Audit Log Helper Functions
 */

function logAudit($pdo, $action, $table_name, $record_id = null, $old_values = null, $new_values = null)
{
    try {
        // ✅ SECURITY: Automatically remove password field for users table
        // This ensures password hashes never leak into audit logs,
        // even if logAudit() is called directly instead of using wrapper functions
        if ($table_name === 'users') {
            if (is_array($old_values) && isset($old_values['password'])) {
                unset($old_values['password']);
            }
            if (is_array($new_values) && isset($new_values['password'])) {
                unset($new_values['password']);
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, user_name, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $_SESSION['name'] ?? 'System',
            $action,
            $table_name,
            $record_id,
            $old_values ? json_encode($old_values, JSON_UNESCAPED_UNICODE) : null,
            $new_values ? json_encode($new_values, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

        return true;
    } catch (PDOException $e) {
        error_log("Audit log error: " . $e->getMessage());
        return false;
    }
}

function logClientCreate($pdo, $client_id, $client_data)
{
    logAudit($pdo, 'CREATE', 'clients', $client_id, null, $client_data);
}

function logClientUpdate($pdo, $client_id, $old_data, $new_data)
{
    logAudit($pdo, 'UPDATE', 'clients', $client_id, $old_data, $new_data);
}

function logClientDelete($pdo, $client_id, $client_data)
{
    logAudit($pdo, 'DELETE', 'clients', $client_id, $client_data, null);
}

function logClientApprove($pdo, $client_id, $client_data)
{
    logAudit($pdo, 'APPROVE', 'clients', $client_id, null, $client_data);
}

function logClientReject($pdo, $client_id, $client_data, $reason = null)
{
    $data = $client_data;
    $data['rejection_reason'] = $reason;
    logAudit($pdo, 'REJECT', 'clients', $client_id, null, $data);
}

function logLogin($pdo, $user_id, $success = true)
{
    logAudit($pdo, $success ? 'LOGIN' : 'LOGIN_FAILED', 'users', $user_id, null, ['success' => $success]);
}

function logLogout($pdo, $user_id)
{
    logAudit($pdo, 'LOGOUT', 'users', $user_id, null, null);
}

/**
 * Log data export
 */
function logDataExport($pdo, $export_type, $record_count, $filters = [])
{
    logAudit($pdo, 'DATA_EXPORT', $export_type, null, null, [
        'record_count' => $record_count,
        'filters' => $filters,
        'format' => 'CSV'
    ]);
}

