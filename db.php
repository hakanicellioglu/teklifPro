<?php

declare(strict_types=1);

interface DbDriverInterface
{
    public function prepare(string $sql);
    public function exec(string $sql);
    public function query(string $sql);
    public function lastInsertId();
}

class DbAdapter
{
    private $driver;

    public function __construct(DbDriverInterface $driver)
    {
        $this->driver = $driver;
    }

    public static function create(string $host, string $db, string $user, string $pass): self
    {
        if (class_exists('PDO')) {
            $driver = new PdoDriver($host, $db, $user, $pass);
        } else {
            $driver = new MysqliDriver($host, $db, $user, $pass);
        }
        return new self($driver);
    }

    public function prepare(string $sql)
    {
        return $this->driver->prepare($sql);
    }

    public function exec(string $sql)
    {
        return $this->driver->exec($sql);
    }

    public function query(string $sql)
    {
        return $this->driver->query($sql);
    }

    public function lastInsertId()
    {
        return $this->driver->lastInsertId();
    }
}

class PdoDriver implements DbDriverInterface
{
    private $pdo;

    public function __construct(string $host, string $db, string $user, string $pass)
    {
        $this->pdo = new PDO(
            'mysql:host=' . $host . ';dbname=' . $db . ';charset=utf8mb4',
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $this->pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_turkish_ci');
    }

    public function prepare(string $sql)
    {
        return new PdoStatementWrapper($this->pdo->prepare($sql));
    }

    public function exec(string $sql)
    {
        return $this->pdo->exec($sql);
    }

    public function query(string $sql)
    {
        return $this->pdo->query($sql);
    }

    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }
}

class PdoStatementWrapper
{
    private $stmt;

    public function __construct(PDOStatement $stmt)
    {
        $this->stmt = $stmt;
    }

    public function execute(array $params = [])
    {
        return $this->stmt->execute($params);
    }

    public function fetch()
    {
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function fetchAll()
    {
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchColumn()
    {
        return $this->stmt->fetchColumn();
    }
}

class MysqliDriver implements DbDriverInterface
{
    private $mysqli;

    public function __construct(string $host, string $db, string $user, string $pass)
    {
        $this->mysqli = mysqli_init();
        $this->mysqli->real_connect($host, $user, $pass, $db);
        if ($this->mysqli->connect_error) {
            throw new Exception('Connect Error (' . $this->mysqli->connect_errno . ') ' . $this->mysqli->connect_error);
        }
        $this->mysqli->set_charset('utf8mb4');
        $this->mysqli->query("SET NAMES utf8mb4 COLLATE utf8mb4_turkish_ci");
    }

    public function prepare(string $sql)
    {
        $paramMap = [];
        $sql = preg_replace_callback('/:([a-zA-Z_][a-zA-Z0-9_]*)/', function ($matches) use (&$paramMap) {
            $paramMap[] = $matches[1];
            return '?';
        }, $sql);
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception($this->mysqli->error);
        }
        return new MysqliStatementWrapper($stmt, $paramMap);
    }

    public function exec(string $sql)
    {
        if ($this->mysqli->query($sql) === false) {
            throw new Exception($this->mysqli->error);
        }
    }

    public function query(string $sql)
    {
        $result = $this->mysqli->query($sql);
        if ($result === false) {
            throw new Exception($this->mysqli->error);
        }
        return $result;
    }

    public function lastInsertId()
    {
        return $this->mysqli->insert_id;
    }
}

class MysqliStatementWrapper
{
    private $stmt;
    private $paramMap;
    private $result;

    public function __construct(mysqli_stmt $stmt, array $paramMap)
    {
        $this->stmt = $stmt;
        $this->paramMap = $paramMap;
    }

    public function execute(array $params = [])
    {
        if ($this->paramMap) {
            $types = '';
            $values = [];
            foreach ($this->paramMap as $name) {
                $key = ':' . $name;
                $value = isset($params[$key]) ? $params[$key] : (isset($params[$name]) ? $params[$name] : null);
                $values[] = $value;
                if (is_int($value)) {
                    $types .= 'i';
                } elseif (is_float($value)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            if ($values) {
                $this->stmt->bind_param($types, ...$values);
            }
        }
        $ok = $this->stmt->execute();
        if ($ok) {
            $this->result = $this->stmt->get_result();
        }
        return $ok;
    }

    public function fetch()
    {
        if ($this->result) {
            return $this->result->fetch_assoc();
        }
        return null;
    }

    public function fetchAll()
    {
        if ($this->result) {
            return $this->result->fetch_all(MYSQLI_ASSOC);
        }
        return [];
    }

    public function fetchColumn()
    {
        if ($this->result) {
            $row = $this->result->fetch_row();
            return $row ? $row[0] : null;
        }
        return null;
    }
}

