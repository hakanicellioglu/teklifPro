<?php
declare(strict_types=1);

function share_offer(PDO $pdo, int $offerId): bool
{
    try {
        $stmt = $pdo->prepare('UPDATE generaloffers SET status = :st WHERE id = :id');
        $stmt->execute([':st' => 'shared', ':id' => $offerId]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}
