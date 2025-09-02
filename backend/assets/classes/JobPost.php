<?php
require '../SQL/config.php';

class JobPost
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAllPosts()
    {
        $sql = "SELECT * FROM hr_job ORDER BY date_post DESC;";
        $result = $this->conn->query($sql);

        $posts = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $posts[] = $row;
            }
        }
        return $posts;
    }
}
?>
