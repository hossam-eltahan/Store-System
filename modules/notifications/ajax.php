<?php
/**
 * Notifications AJAX Handler
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
require_once '../../includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'check_timers':
            $finishedSessions = [];

            // Ensure the tracking table exists (defensive)
            $pdo->exec("CREATE TABLE IF NOT EXISTS `session_timer_notifications` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `entity_type` ENUM('gaming', 'billiards') NOT NULL,
                `session_id` INT NOT NULL,
                `notified_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_session` (`entity_type`, `session_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 1. Check Gaming Sessions
            $gaming = getRows("
                SELECT gs.id, gd.display_name, gs.preset_minutes, gs.started_at, gs.total_paused_seconds, gs.status, gs.paused_at
                FROM gaming_sessions gs
                JOIN gaming_devices gd ON gs.device_id = gd.id
                WHERE gs.status IN ('active', 'paused')
                AND gs.time_mode = 'countdown' 
                AND NOT EXISTS (
                    SELECT 1 FROM session_timer_notifications stn
                    WHERE stn.entity_type = 'gaming' AND stn.session_id = gs.id
                )
            ");

            foreach ($gaming as $sess) {
                $paused = (int)$sess['total_paused_seconds'];
                if (($sess['status'] ?? '') === 'paused' && !empty($sess['paused_at'])) {
                    $paused += max(0, time() - strtotime($sess['paused_at']));
                }
                $elapsed = time() - strtotime($sess['started_at']);
                $netSec = $elapsed - $paused;
                $presetSec = $sess['preset_minutes'] * 60;

                if ($netSec >= $presetSec) {
                    $finishedSessions[] = [
                        'type' => 'gaming',
                        'id' => $sess['id'],
                        'name' => $sess['display_name'],
                        'message' => "انتهى وقت جهاز " . $sess['display_name']
                    ];
                    // Mark as notified
                    $pdo->exec("INSERT IGNORE INTO session_timer_notifications (entity_type, session_id) VALUES ('gaming', {$sess['id']})");
                }
            }

            // 2. Check Billiard Sessions
            $billiards = getRows("
                SELECT bs.id, bt.display_name, bs.preset_minutes, bs.started_at, bs.total_paused_seconds, bs.status, bs.paused_at
                FROM billiard_sessions bs
                JOIN billiard_tables bt ON bs.table_id = bt.id
                WHERE bs.status IN ('active', 'paused')
                AND bs.time_mode = 'countdown' 
                AND NOT EXISTS (
                    SELECT 1 FROM session_timer_notifications stn
                    WHERE stn.entity_type = 'billiards' AND stn.session_id = bs.id
                )
            ");

            foreach ($billiards as $sess) {
                $paused = (int)$sess['total_paused_seconds'];
                if (($sess['status'] ?? '') === 'paused' && !empty($sess['paused_at'])) {
                    $paused += max(0, time() - strtotime($sess['paused_at']));
                }
                $elapsed = time() - strtotime($sess['started_at']);
                $netSec = $elapsed - $paused;
                $presetSec = $sess['preset_minutes'] * 60;

                if ($netSec >= $presetSec) {
                    $finishedSessions[] = [
                        'type' => 'billiards',
                        'id' => $sess['id'],
                        'name' => $sess['display_name'],
                        'message' => "انتهى وقت طاولة " . $sess['display_name']
                    ];
                    // Mark as notified
                    $pdo->exec("INSERT IGNORE INTO session_timer_notifications (entity_type, session_id) VALUES ('billiards', {$sess['id']})");
                }
            }

            $notifList = function_exists('getNotifications') ? getNotifications() : [];
            echo json_encode([
                'success' => true,
                'finished' => $finishedSessions,
                'new_alerts' => $finishedSessions,
                'notification_count' => function_exists('getNotificationCount') ? getNotificationCount() : count($notifList),
                'notifications' => array_values($notifList)
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'mark_notified':
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $type = $_POST['type'] ?? '';
            $pdo->exec("CREATE TABLE IF NOT EXISTS `session_timer_notifications` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `entity_type` ENUM('gaming', 'billiards') NOT NULL,
                `session_id` INT NOT NULL,
                `notified_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_session` (`entity_type`, `session_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            if ($type === 'gaming' || $type === 'billiards') {
                $pdo->exec("INSERT IGNORE INTO session_timer_notifications (entity_type, session_id) VALUES ('" . $type . "', " . $sessionId . ")");
            }
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
