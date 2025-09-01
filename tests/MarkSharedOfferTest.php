<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../mark_shared_service.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

final class MarkSharedOfferTest extends TestCase
{
    private function createPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE generaloffers (id INTEGER PRIMARY KEY, status TEXT, share_count INT DEFAULT 0, shared_at DATETIME NULL, shared_by INT NULL)');
        return $pdo;
    }

    public function testDraftMovesToInProgress(): void
    {
        $pdo = $this->createPdo();
        $pdo->exec("INSERT INTO generaloffers (id, status, share_count) VALUES (1, 'draft', 0)");
        $res = mark_offer_shared($pdo, 1, 5);
        $this->assertEquals('in_progress', $res['status']);
        $this->assertEquals(1, $res['share_count']);
        $row = $pdo->query('SELECT status, share_count, shared_by FROM generaloffers WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('in_progress', $row['status']);
        $this->assertEquals(1, $row['share_count']);
        $this->assertEquals(5, $row['shared_by']);
    }

    public function testApprovedStatusUnaffected(): void
    {
        $pdo = $this->createPdo();
        $pdo->exec("INSERT INTO generaloffers (id, status, share_count) VALUES (1, 'approved', 2)");
        $res = mark_offer_shared($pdo, 1, 7);
        $this->assertEquals('approved', $res['status']);
        $this->assertEquals(3, $res['share_count']);
        $row = $pdo->query('SELECT status, share_count FROM generaloffers WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('approved', $row['status']);
        $this->assertEquals(3, $row['share_count']);
    }

    public function testCsrfFailureReturns400(): void
    {
        $pdo = $this->createPdo();
        $pdo->exec("INSERT INTO generaloffers (id, status, share_count) VALUES (1, 'draft', 0)");
        $_SESSION['csrf_token'] = 'token';
        $_SESSION['user_id'] = 1;
        $_POST = ['csrf_token' => 'wrong', 'id' => 1];
        $_SERVER['REQUEST_URI'] = '/offers/1/mark-shared';
        ob_start();
        include __DIR__ . '/../offers/mark_shared.php';
        ob_end_clean();
        $this->assertSame(400, $GLOBALS['response_code']);
    }
}
