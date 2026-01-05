<?php
class RoomManager
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getAvailableRoom($roomType)
    {
        $stmt = $this->conn->prepare("
            SELECT roomID
            FROM rooms
            WHERE roomType = ?
              AND status = 'Available'
            LIMIT 1
        ");

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("s", $roomType);
        $stmt->execute();
        $result = $stmt->get_result();
        $room = $result->fetch_assoc();
        $stmt->close();

        return $room ?: null;
    }

    public function occupyRoom($roomID)
    {
        $stmt = $this->conn->prepare("
            UPDATE rooms
            SET status = 'Occupied'
            WHERE roomID = ?
        ");
        $stmt->bind_param("i", $roomID);
        $stmt->execute();
        $stmt->close();
    }

    public function releaseRoom($roomID)
    {
        $stmt = $this->conn->prepare("
            UPDATE rooms
            SET status = 'Available'
            WHERE roomID = ?
        ");
        $stmt->bind_param("i", $roomID);
        $stmt->execute();
        $stmt->close();
    }
}
