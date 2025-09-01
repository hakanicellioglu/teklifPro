<?php
declare(strict_types=1);

function mark_offer_shared(PDO $pdo, int $offerId, int $userId): array|false
{
    try {
        $pdo->beginTransaction();
        $forUpdate = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') ? ' FOR UPDATE' : '';
        $stmt = $pdo->prepare('SELECT status, share_count FROM generaloffers WHERE id = :id' . $forUpdate);
        $stmt->execute([':id' => $offerId]);
        $offer = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$offer) {
            $pdo->rollBack();
            return false;
        }
        $status = ($offer['status'] === 'draft') ? 'in_progress' : $offer['status'];
        $upd = $pdo->prepare('UPDATE generaloffers SET status = :st, share_count = share_count + 1, shared_at = CURRENT_TIMESTAMP, shared_by = :uid WHERE id = :id');
        $upd->execute([':st' => $status, ':uid' => $userId, ':id' => $offerId]);
        $pdo->commit();
        return ['status' => $status, 'share_count' => (int)$offer['share_count'] + 1];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}
