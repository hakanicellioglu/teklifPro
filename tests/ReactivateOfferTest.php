<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../reactivate_service.php';

final class ReactivateOfferTest extends TestCase
{
    public function testIsExpiredByDate(): void
    {
        $offer = ['status' => 'pending', 'valid_until' => date('Y-m-d', strtotime('-1 day'))];
        $this->assertTrue(isExpired($offer));
    }

    public function testReactivateOffer(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE generaloffers (id INTEGER PRIMARY KEY, status TEXT, valid_until DATE)');
        $pdo->exec("INSERT INTO generaloffers (id, status, valid_until) VALUES (1, 'expired', DATE('now','-1 day'))");
        $this->assertTrue(reactivate_offer($pdo, 1, 42, 14));
        $row = $pdo->query('SELECT status, valid_until FROM generaloffers WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('active', $row['status']);
        $expected = (new DateTime('+14 day'))->format('Y-m-d');
        $this->assertEquals($expected, $row['valid_until']);
    }
}
