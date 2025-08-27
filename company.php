<?php
declare(strict_types=1);

/**
 * Fetch company information by ID.
 *
 * @param PDO   $pdo       Database connection
 * @param int   $companyId Company identifier
 * @return array           Company data or empty array if not found
 */
function getCompanyById(PDO $pdo, int $companyId): array
{
    if ($companyId <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare('SELECT id, name, email, phone, address, logo FROM company WHERE id = :id');
        $stmt->execute(['id' => $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    } catch (Exception $e) {
        return [];
    }
}
