<?php
require_once 'config.php';

class Database {
    private $conn;
    private $stmt;
    private $error;
    private $bindParams = [];
    private $bindTypes = '';

    public function __construct() {
        // Kết nối database dựa trên config
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($this->conn->connect_error) {
            die("Kết nối thất bại: " . $this->conn->connect_error);
        }

        $this->conn->set_charset("utf8mb4");
    }

    // Chuẩn bị câu truy vấn, dùng dấu hỏi ? thay cho tham số
    public function query($query) {
        $this->stmt = $this->conn->prepare($query);
        if (!$this->stmt) {
            $this->error = $this->conn->error;
            return false;
        }
        // Reset các tham số bind
        $this->bindParams = [];
        $this->bindTypes = '';
        return true;
    }

    // Bind tham số theo kiểu tự động
    public function bind($value, $type = null) {
        if ($type === null) {
            if (is_int($value)) {
                $type = 'i';
            } elseif (is_float($value) || is_double($value)) {
                $type = 'd';
            } elseif (is_string($value)) {
                $type = 's';
            } else {
                $type = 'b';
            }
        }
        $this->bindTypes .= $type;
        $this->bindParams[] = $value;
    }

    // Thực thi truy vấn
    public function execute() {
        if (!$this->stmt) {
            return false;
        }

        if (!empty($this->bindParams)) {
            // Tạo tham số cho bind_param với tham chiếu
            $params = [];
            $params[] = &$this->bindTypes;
            for ($i = 0; $i < count($this->bindParams); $i++) {
                $params[] = &$this->bindParams[$i];
            }
            call_user_func_array([$this->stmt, 'bind_param'], $params);
        }

        $success = $this->stmt->execute();

        if (!$success) {
            $this->error = $this->stmt->error;
        }

        return $success;
    }

    // Lấy tất cả bản ghi dạng mảng
    public function fetchAll() {
        $this->execute();
        $result = $this->stmt->get_result();
        if ($result === false) {
            return [];
        }
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Lấy một bản ghi
    public function fetchOne() {
        $this->execute();
        $result = $this->stmt->get_result();
        if ($result === false) {
            return null;
        }
        return $result->fetch_assoc();
    }

    // Số dòng ảnh hưởng hoặc trả về
    public function rowCount() {
        if ($this->stmt) {
            return $this->stmt->affected_rows;
        }
        return 0;
    }

    // Lấy ID bản ghi mới insert
    public function lastInsertId() {
        return $this->conn->insert_id;
    }

    // Transaction
    public function beginTransaction() {
        return $this->conn->begin_transaction();
    }

    public function commit() {
        return $this->conn->commit();
    }

    public function rollback() {
        return $this->conn->rollback();
    }

    // Lấy lỗi
    public function getError() {
        return $this->error;
    }

    // Đóng kết nối khi hủy object
    public function __destruct() {
        if ($this->stmt) {
            $this->stmt->close();
        }
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
