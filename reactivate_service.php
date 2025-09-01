<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if (!defined('DEFAULT_REACTIVATION_DAYS')) {
    define('DEFAULT_REACTIVATION_DAYS', 14);
}

function reactivate_offer(PDO $pdo, int $offerId, int $userId, int $extendDays = DEFAULT_REACTIVATION_DAYS): bool
{
    try {
        $pdo->beginTransaction();
        $forUpdate = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') ? ' FOR UPDATE' : '';
        $stmt = $pdo->prepare('SELECT * FROM generaloffers WHERE id = :id' . $forUpdate);
        $stmt->execute([':id' => $offerId]);
        $offer = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$offer || !isExpired($offer)) {
            $pdo->rollBack();
            return false;
        }

        $newDate = (new DateTime('+' . $extendDays . ' day'))->format('Y-m-d');
        $upd = $pdo->prepare('UPDATE generaloffers SET status = :st, valid_until = :vu WHERE id = :id');
        $upd->execute([':st' => 'active', ':vu' => $newDate, ':id' => $offerId]);

        $logLine = sprintf("[%s] user:%d reactivated offer:%d\n", date('c'), $userId, $offerId);
        @file_put_contents(__DIR__ . '/storage/audit.log', $logLine, FILE_APPEND);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}
