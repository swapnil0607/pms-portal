<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\User;
use DateTimeImmutable;
use Throwable;

class NotificationService
{
    /**
     * Send an In-App notification and optionally trigger an MSG91 Email alert.
     */
    public static function notifyUser(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $link = null,
        array $emailVariables = [],
        ?string $templateKey = null
    ): void {
        self::ensureTablesExist();

        if ($userId <= 0) {
            return;
        }

        // 1. Create In-App Notification
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                'INSERT INTO notifications (user_id, type, title, message, link_url, is_read, created_at)
                 VALUES (?, ?, ?, ?, ?, 0, NOW())'
            );
            $stmt->execute([$userId, $type, $title, $message, $link]);
        } catch (Throwable $e) {
            error_log('Failed to create in-app notification: ' . $e->getMessage());
        }

        // 2. Dispatch MSG91 Email if template is specified
        if ($templateKey !== null) {
            try {
                $user = User::find($userId);
                if ($user && !empty($user['email']) && ($user['status'] ?? 'active') === 'active') {
                    Msg91Service::sendTemplateEmail(
                        $templateKey,
                        $user['email'],
                        $user['name'] ?? 'Team Member',
                        $emailVariables
                    );
                }
            } catch (Throwable $e) {
                error_log('Failed to dispatch MSG91 notification email: ' . $e->getMessage());
            }
        }
    }

    /**
     * Triggered when a task is assigned or newly added to assignees.
     */
    public static function onTaskAssigned(int $taskId, array $newAssigneeIds, ?int $assignedByUserId = null): void
    {
        if ($taskId <= 0 || empty($newAssigneeIds)) {
            return;
        }

        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                'SELECT t.*, p.name AS project_name 
                 FROM tasks t 
                 LEFT JOIN projects p ON p.id = t.project_id 
                 WHERE t.id = ?'
            );
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();
            if (!$task) {
                return;
            }

            $assignerName = 'Team Lead';
            if ($assignedByUserId && $assignedByUserId > 0) {
                $assigner = User::find($assignedByUserId);
                if ($assigner) {
                    $assignerName = $assigner['name'];
                }
            }

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'eduriserck.com';
            $taskLink = $scheme . '://' . $host . url('/tasks/show?id=' . $taskId);
            $inAppLink = url('/tasks/show?id=' . $taskId);

            $taskCode = $task['task_code'] ?: ('TSK-' . $taskId);
            $taskTitle = $task['title'];
            $projectName = $task['project_name'] ?: 'General Project';
            $priority = ucfirst((string) ($task['priority'] ?: 'Normal'));
            $dueDate = !empty($task['due_date']) ? date('d M Y', strtotime($task['due_date'])) : 'No due date';

            $title = "Task Assigned: [{$taskCode}] {$taskTitle}";
            $message = "Assigned to you by {$assignerName} in {$projectName}. Priority: {$priority}, Due: {$dueDate}.";

            $emailVars = [
                'task_code' => $taskCode,
                'task_title' => $taskTitle,
                'project_name' => $projectName,
                'assigned_by' => $assignerName,
                'priority' => $priority,
                'due_date' => $dueDate,
                'task_link' => $taskLink,
            ];

            foreach ($newAssigneeIds as $assigneeId) {
                $uid = (int) $assigneeId;
                if ($uid <= 0 || ($assignedByUserId && $uid === $assignedByUserId)) {
                    continue; // Skip self-assignment notifications
                }

                self::notifyUser(
                    $uid,
                    'task_assigned',
                    $title,
                    $message,
                    $inAppLink,
                    $emailVars,
                    'task_assigned'
                );
            }
        } catch (Throwable $e) {
            error_log('Error in onTaskAssigned: ' . $e->getMessage());
        }
    }

    /**
     * Triggered when a task status changes (e.g. In Review, Completed).
     */
    public static function onTaskStatusChanged(int $taskId, string $newStatus, ?int $updatedByUserId = null): void
    {
        if ($taskId <= 0 || !in_array($newStatus, ['in_review', 'completed'], true)) {
            return;
        }

        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                'SELECT t.*, p.name AS project_name, p.owner_id AS project_owner_id 
                 FROM tasks t 
                 LEFT JOIN projects p ON p.id = t.project_id 
                 WHERE t.id = ?'
            );
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();
            if (!$task) {
                return;
            }

            $updaterName = 'Team Member';
            if ($updatedByUserId && $updatedByUserId > 0) {
                $updater = User::find($updatedByUserId);
                if ($updater) {
                    $updaterName = $updater['name'];
                }
            }

            $statusLabel = $newStatus === 'completed' ? 'Completed' : 'Ready for Review';
            $taskCode = $task['task_code'] ?: ('TSK-' . $taskId);
            $taskTitle = $task['title'];
            $projectName = $task['project_name'] ?: 'Project';
            $inAppLink = url('/tasks/show?id=' . $taskId);

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'eduriserck.com';
            $taskLink = $scheme . '://' . $host . $inAppLink;

            $title = "Task {$statusLabel}: [{$taskCode}] {$taskTitle}";
            $message = "{$updaterName} updated this task to {$statusLabel} in {$projectName}.";

            $emailVars = [
                'notice_title' => $title,
                'notice_message' => $message,
                'action_link' => $taskLink,
            ];

            // Option B: Notify ONLY assigned team members (co-assignees), skipping updater
            $assigneeIds = \App\Models\Task::assigneeIds($taskId);
            if (empty($assigneeIds) && !empty($task['assigned_to'])) {
                $assigneeIds = [(int) $task['assigned_to']];
            }

            $notifyUserIds = array_unique(array_filter($assigneeIds));

            foreach ($notifyUserIds as $uid) {
                if ($uid > 0 && $uid !== $updatedByUserId) {
                    self::notifyUser(
                        $uid,
                        'task_status',
                        $title,
                        $message,
                        $inAppLink,
                        [],
                        null // In-app notification only
                    );
                }
            }
        } catch (Throwable $e) {
            error_log('Error in onTaskStatusChanged: ' . $e->getMessage());
        }
    }

    /**
     * Checks client batches and sends upcoming/overdue renewal alerts.
     * Sent to: Project Owners, Managers, and Admins.
     */
    public static function checkRenewalAlerts(): int
    {
        self::ensureTablesExist();

        try {
            $db = Database::connection();
            $today = date('Y-m-d');

            // Find active client batches with a renewal_date
            $stmt = $db->query(
                'SELECT cb.*, c.name AS client_name, p.name AS project_name, p.owner_id AS project_owner_id
                 FROM client_batches cb
                 LEFT JOIN clients c ON c.id = cb.client_id
                 LEFT JOIN projects p ON p.id = cb.project_id
                 WHERE cb.status NOT IN ("archived", "cancelled")
                   AND cb.archived_at IS NULL
                   AND cb.renewal_date IS NOT NULL 
                   AND cb.renewal_date != "0000-00-00"'
            );
            $batches = $stmt->fetchAll();

            $alertsSent = 0;

            // Fetch all Admins and Managers
            $adminStmt = $db->query(
                "SELECT id, name, email FROM users WHERE status = 'active' AND role IN ('admin', 'manager')"
            );
            $adminsAndManagers = $adminStmt->fetchAll();

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'eduriserck.com';
            $renewalsBaseLink = $scheme . '://' . $host . url('/client-renewals');
            $inAppRenewalsLink = url('/client-renewals');

            foreach ($batches as $batch) {
                $renewalDate = $batch['renewal_date'];
                $diffDays = (int) round((strtotime($renewalDate) - strtotime($today)) / 86400);

                $milestone = null;
                $daysText = '';

                if ($diffDays === 45) {
                    $milestone = '45d';
                    $daysText = '45 days remaining';
                } elseif ($diffDays === 30) {
                    $milestone = '30d';
                    $daysText = '30 days remaining';
                } elseif ($diffDays === 15) {
                    $milestone = '15d';
                    $daysText = '15 days remaining';
                } elseif ($diffDays === 7) {
                    $milestone = '7d';
                    $daysText = '7 days remaining';
                } elseif ($diffDays < 0) {
                    // Catches all overdue batches
                    $milestone = 'overdue';
                    $daysText = 'Contract Overdue (' . abs($diffDays) . ' days ago)';
                }

                if (!$milestone) {
                    continue;
                }

                // Check if already sent today for this batch & milestone (prevents duplicate spamming)
                $triggerKey = "renewal_batch_{$batch['id']}_{$milestone}_{$today}";
                $logCheck = $db->prepare('SELECT id FROM notification_logs WHERE trigger_key = ? LIMIT 1');
                $logCheck->execute([$triggerKey]);
                if ($logCheck->fetch()) {
                    continue; // Already processed today
                }

                // Log trigger key
                $db->prepare('INSERT INTO notification_logs (trigger_key) VALUES (?)')->execute([$triggerKey]);

                $clientName = $batch['client_name'] ?: 'Client';
                $batchName = $batch['batch_name'] ?: 'Batch';
                $formattedRenewalDate = date('d M Y', strtotime($renewalDate));

                if ($diffDays < 0) {
                    $title = "Renewal Overdue Notice: {$clientName} ({$daysText})";
                    $message = "Batch '{$batchName}' for {$clientName} expired on {$formattedRenewalDate} ({$daysText}). Immediate renewal action required.";
                } else {
                    $title = "Renewal Notice: {$clientName} ({$daysText})";
                    $message = "Batch '{$batchName}' for {$clientName} is set to expire on {$formattedRenewalDate}.";
                }

                $emailVars = [
                    'client_name' => $clientName,
                    'batch_name' => $batchName,
                    'renewal_date' => $formattedRenewalDate,
                    'days_remaining' => $daysText,
                    'renewal_link' => $renewalsBaseLink,
                    'date_year' => date('Y'),
                ];

                // Build recipient list: Admins + Managers + Project Owners
                $recipientUserIds = [];

                // 1. All Admins and Managers
                foreach ($adminsAndManagers as $adm) {
                    $recipientUserIds[(int) $adm['id']] = true;
                }

                // 2. Direct Project Owner for this batch
                if (!empty($batch['project_owner_id'])) {
                    $ownerId = (int) $batch['project_owner_id'];
                    if ($ownerId > 0) {
                        $recipientUserIds[$ownerId] = true;
                    }
                }

                // 3. Fallback: If no direct project_owner_id on batch, include Project Owners under this client
                if (empty($batch['project_owner_id']) && !empty($batch['client_id'])) {
                    $cpoStmt = $db->prepare('SELECT owner_id FROM projects WHERE client_id = ? AND archived_at IS NULL');
                    $cpoStmt->execute([(int) $batch['client_id']]);
                    foreach ($cpoStmt->fetchAll() as $cpo) {
                        if (!empty($cpo['owner_id'])) {
                            $recipientUserIds[(int) $cpo['owner_id']] = true;
                        }
                    }
                }

                // Notify all resolved unique recipients via In-App and MSG91 Email
                foreach (array_keys($recipientUserIds) as $recipientId) {
                    self::notifyUser(
                        (int) $recipientId,
                        'renewal_alert',
                        $title,
                        $message,
                        $inAppRenewalsLink,
                        $emailVars,
                        'renewal_alert'
                    );
                    $alertsSent++;
                }
            }

            return $alertsSent;
        } catch (Throwable $e) {
            error_log('Error in checkRenewalAlerts: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Returns unread notification count for a user.
     */
    public static function getUnreadCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        self::ensureTablesExist();

        try {
            $db = Database::connection();
            $stmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
            $stmt->execute([$userId]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Returns recent notifications for a user.
     */
    public static function getRecent(int $userId, int $limit = 20): array
    {
        if ($userId <= 0) {
            return [];
        }
        self::ensureTablesExist();

        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                'SELECT * FROM notifications 
                 WHERE user_id = ? 
                 ORDER BY id DESC 
                 LIMIT ' . (int) $limit
            );
            $stmt->execute([$userId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Marks a single notification as read.
     */
    public static function markAsRead(int $notificationId, int $userId): void
    {
        self::ensureTablesExist();
        try {
            $db = Database::connection();
            $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
            $stmt->execute([$notificationId, $userId]);
        } catch (Throwable $e) {
            error_log('Error in markAsRead: ' . $e->getMessage());
        }
    }

    /**
     * Marks all notifications as read for a user.
     */
    public static function markAllAsRead(int $userId): void
    {
        self::ensureTablesExist();
        try {
            $db = Database::connection();
            $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
            $stmt->execute([$userId]);
        } catch (Throwable $e) {
            error_log('Error in markAllAsRead: ' . $e->getMessage());
        }
    }

    /**
     * Ensures database tables exist.
     */
    public static function ensureTablesExist(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        try {
            $db = Database::connection();
            $db->exec(
                'CREATE TABLE IF NOT EXISTS notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    type VARCHAR(50) NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    link_url VARCHAR(255) DEFAULT NULL,
                    is_read TINYINT(1) NOT NULL DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_read (user_id, is_read),
                    INDEX idx_user_created (user_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
            );

            $db->exec(
                'CREATE TABLE IF NOT EXISTS notification_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    trigger_key VARCHAR(100) NOT NULL UNIQUE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
            );

            $checked = true;
        } catch (Throwable $e) {
            error_log('Failed to ensure notifications tables: ' . $e->getMessage());
        }
    }
}
