<?php
class RoomManager
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // 🔹 Get ONE available room (used during scheduling)
    public function getAvailableRoom($roomType)
    {
        $stmt = $this->conn->prepare("
            SELECT roomID, roomName
            FROM rooms
            WHERE roomType = ?
              AND status = 'Available'
            LIMIT 1
        ");

        $stmt->bind_param("s", $roomType);
        $stmt->execute();
        $room = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $room ?: null;
    }

    // 🔹 Get ALL available rooms (used for front-end display)
    public function getAvailableRoomsByType($roomType)
    {
        $stmt = $this->conn->prepare("
            SELECT roomID, roomName
            FROM rooms
            WHERE roomType = ?
              AND status = 'Available'
            ORDER BY roomName
        ");

        $stmt->bind_param("s", $roomType);
        $stmt->execute();
        $rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rooms;
    }

    public function occupyRoom($roomID)
    {
        $stmt = $this->conn->prepare("
            UPDATE rooms SET status = 'Occupied'
            WHERE roomID = ?
        ");
        $stmt->bind_param("i", $roomID);
        $stmt->execute();
        $stmt->close();
    }

    public function releaseRoom($roomID)
    {
        $stmt = $this->conn->prepare("
            UPDATE rooms SET status = 'Available'
            WHERE roomID = ?
        ");
        $stmt->bind_param("i", $roomID);
        $stmt->execute();
        $stmt->close();
    }
}
