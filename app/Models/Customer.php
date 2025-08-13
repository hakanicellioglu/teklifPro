<?php
namespace App\Models;

use PDO;

class Customer
{
    protected PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function all(): array
    {
        $stmt = $this->db->query('SELECT * FROM customers');
        return $stmt->fetchAll();
    }
}
