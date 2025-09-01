<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../share_service.php';

final class ShareOfferTest extends TestCase
{
    public function testShareOffer(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE generaloffers (id INTEGER PRIMARY KEY, status TEXT)');
        $pdo->exec("INSERT INTO generaloffers (id, status) VALUES (1, 'pending')");
        $this->assertTrue(share_offer($pdo, 1));
        $row = $pdo->query('SELECT status FROM generaloffers WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('shared', $row['status']);
    }

    public function testShareOfferInvalidId(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE generaloffers (id INTEGER PRIMARY KEY, status TEXT)');
        $this->assertFalse(share_offer($pdo, 999));
    }
}
