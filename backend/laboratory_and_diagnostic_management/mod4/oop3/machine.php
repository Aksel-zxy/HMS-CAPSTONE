<?php
require_once __DIR__ . '../../../../../SQL/config.php';

class MachineEquipment {
    private $conn;

    public function __construct(mysqli $dbConn) {
        $this->conn = $dbConn;
    }

    public function getLabAndDiagnostic(): array {
        $sql = "
            SELECT DISTINCT
                machine_id,
                machine_type,
                machine_name,
                status
            FROM machine_equipments
            WHERE machine_type IN ('Laboratory', 'Diagnostic')
            
            ORDER BY machine_id ASC
        ";

        $result = $this->conn->query($sql);

        if (!$result) {
            throw new Exception("Query failed: " . $this->conn->error);
        }

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        return $data;
    }
}

$machine = new MachineEquipment($conn);
$labDiagnosticEquipments = [];
$fetchError = '';

try {
    $labDiagnosticEquipments = $machine->getLabAndDiagnostic();
} catch (Exception $e) {
    $fetchError = $e->getMessage();
}
?>
