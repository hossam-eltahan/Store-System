<?php
/**
 * Shared database backup routines
 * Used by: manual backup page, automatic periodic backup hook, factory reset page
 */

if (!function_exists('backupFilePath')) {
    /**
     * Backups are stored by filename (see `backups` table); resolve the path.
     */
    function backupFilePath($filename) {
        return BACKUP_DIR . basename($filename);
    }
}

if (!function_exists('performDbBackup')) {
    /**
     * Dump the whole database to a .sql file + record it in `backups`.
     * Returns ['filename','path','size'] on success, false on failure.
     */
    function performDbBackup($prefix = 'backup_', $logLabel = 'نسخ احتياطي') {
        global $pdo;
        try {
            if (!is_dir(BACKUP_DIR)) {
                mkdir(BACKUP_DIR, 0777, true);
            }
            $secondaryDir = defined('BACKUP_DIR_SECONDARY') ? BACKUP_DIR_SECONDARY : null;
            if ($secondaryDir && !is_dir($secondaryDir)) {
                @mkdir($secondaryDir, 0777, true);
            }

            $filename = $prefix . date('Y-m-d_H-i-s') . '.sql';
            $filepath = BACKUP_DIR . $filename;

            $sql = "-- Backup created: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- Database: " . DB_NAME . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql .= $createTable['Create Table'] . ";\n\n";

                $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);

                if (count($rows) > 0) {
                    $columns = array_keys($rows[0]);
                    $columnList = '`' . implode('`, `', $columns) . '`';

                    foreach ($rows as $row) {
                        $values = array_map(function ($v) use ($pdo) {
                            if ($v === null) return 'NULL';
                            return $pdo->quote($v);
                        }, $row);
                        $sql .= "INSERT INTO `$table` ($columnList) VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $sql .= "\n";
                }
            }

            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

            $saved = file_put_contents($filepath, $sql);
            if ($secondaryDir) {
                @file_put_contents($secondaryDir . $filename, $sql);
            }

            if (!$saved) {
                return false;
            }

            $filesize = filesize($filepath);
            $createdBy = (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) ? (int)$_SESSION['user_id'] : null;
            insert(
                "INSERT INTO backups (filename, file_size, created_by) VALUES (?, ?, ?)",
                [$filename, $filesize, $createdBy]
            );
            updateSetting('last_backup_date', date('Y-m-d'));
            logActivity($logLabel, "تم إنشاء نسخة احتياطية: $filename");

            return ['filename' => $filename, 'path' => $filepath, 'size' => $filesize];
        } catch (Throwable $e) {
            error_log("Backup failed: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('pruneAutoBackups')) {
    /**
     * Keep only the newest $keep automatic backups (files + records).
     * Manual backups are never touched.
     */
    function pruneAutoBackups($keep = 7) {
        $keep = max(1, (int)$keep);
        $rows = getRows("SELECT id, filename FROM backups WHERE filename LIKE 'auto_backup_%' ORDER BY created_at DESC, id DESC");
        if (!$rows) {
            return 0;
        }
        $extra = array_slice($rows, $keep);
        foreach ($extra as $r) {
            $p = backupFilePath($r['filename']);
            if (file_exists($p)) {
                @unlink($p);
            }
            execute("DELETE FROM backups WHERE id = ?", [$r['id']]);
        }
        return count($extra);
    }
}

if (!function_exists('runAutoBackupIfDue')) {
    /**
     * Opportunistic scheduler: call on normal page loads.
     * Runs at most once per configured interval. Silent on failure.
     */
    function runAutoBackupIfDue() {
        try {
            if (getSetting('auto_backup_enabled', '0') !== '1') {
                return false;
            }
            $hours = max(1, (int)getSetting('auto_backup_interval_hours', '24'));
            $last = (int)getSetting('last_auto_backup_ts', '0');
            if (time() - $last < $hours * 3600) {
                return false;
            }
            $res = performDbBackup('auto_backup_', 'نسخ احتياطي تلقائي');
            if ($res) {
                updateSetting('last_auto_backup_ts', (string)time());
                pruneAutoBackups(getSetting('auto_backup_keep', '7'));
                return true;
            }
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('splitSqlStatements')) {
    /**
     * Split a .sql dump into executable statements (quote-aware).
     * Strips full-line `--` and `#` comments. No DELIMITER support needed
     * (our dumps contain no triggers/procedures).
     */
    function splitSqlStatements($sql) {
        $len = strlen($sql);
        $stmts = [];
        $buf = '';
        $quote = null; // ' or " or `
        $lineStart = true;
        $inLineComment = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next2 = substr($sql, $i, 2);
            $next3 = substr($sql, $i, 3);

            if ($inLineComment) {
                if ($ch === "\n") {
                    $inLineComment = false;
                    $lineStart = true;
                }
                continue;
            }

            if ($quote === null && $lineStart && ($next2 === '--' || $ch === '#')) {
                // MySQL '--' comment requires whitespace/control after; our headers qualify
                if ($ch === '#' || $next3 === "-- " || $next3 === "--\t" || $next3 === "--\n" || $next3 === "--\r" || strlen($next3) < 3) {
                    $inLineComment = true;
                    continue;
                }
            }

            if ($ch === "\n") {
                $lineStart = true;
            } elseif ($ch !== ' ' && $ch !== "\t" && $ch !== "\r") {
                if ($buf === '' || $lineStart) {
                    // keep tracking
                }
                $lineStart = false;
            }

            if ($quote !== null) {
                $buf .= $ch;
                if ($ch === '\\') {
                    // escaped char: copy next byte verbatim
                    if ($i + 1 < $len) {
                        $buf .= $sql[$i + 1];
                        $i++;
                    }
                } elseif ($ch === $quote) {
                    // '' inside a ' string is an escaped quote, not the terminator
                    if ($quote === "'" && $i + 1 < $len && $sql[$i + 1] === "'") {
                        $buf .= "'";
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $buf .= $ch;
                continue;
            }

            if ($ch === ';') {
                $trimmed = trim($buf);
                if ($trimmed !== '') {
                    $stmts[] = $trimmed;
                }
                $buf = '';
                continue;
            }

            $buf .= $ch;
        }

        $trimmed = trim($buf);
        if ($trimmed !== '') {
            $stmts[] = $trimmed;
        }

        return $stmts;
    }
}
