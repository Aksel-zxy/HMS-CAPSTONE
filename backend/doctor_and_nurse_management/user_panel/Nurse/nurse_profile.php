<?php
if (!isset($conn)) {
    include '../../../../SQL/config.php';
}

if (isset($_SESSION['employee_id'])) {
    $emp_id_modal = $_SESSION['employee_id'];

    $modalEmpQuery = $conn->prepare("SELECT * FROM hr_employees WHERE employee_id = ?");
    $modalEmpQuery->bind_param("i", $emp_id_modal);
    $modalEmpQuery->execute();
    $m_emp = $modalEmpQuery->get_result()->fetch_assoc();

    $modalProfQuery = $conn->prepare("SELECT * FROM clinical_profiles WHERE employee_id = ?");
    $modalProfQuery->bind_param("i", $emp_id_modal);
    $modalProfQuery->execute();
    $m_prof = $modalProfQuery->get_result()->fetch_assoc();
}
?>

<div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">

            <div class="modal-header border-0 p-0 position-relative">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3 text-white" data-bs-dismiss="modal" style="z-index: 10; filter: invert(1);"></button>
            </div>

            <div class="modal-body p-0">
                <?php if (isset($m_emp) && $m_emp): ?>
                    <div class="card border-0">
                        <div style="background: linear-gradient(45deg, #009688, #20c997); padding: 30px; text-align: center; color: white;">
                            
                            <div style="width: 100px; height: 100px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; color: #009688; font-size: 40px; font-weight: bold; border: 4px solid rgba(255,255,255,0.3);">
                                <?php
                                $initials = substr($m_emp['first_name'], 0, 1) . substr($m_emp['last_name'], 0, 1);
                                echo strtoupper($initials);
                                ?>
                            </div>

                            <h3 class="mb-0"><?= htmlspecialchars($m_emp['first_name'] . " " . $m_emp['last_name']) ?></h3>
                            <p class="mb-0 text-white-50">Nurse | <?= htmlspecialchars($m_emp['department']) ?></p>
                            
                            <span class="badge bg-light text-success mt-2 rounded-pill px-3">
                                <?= htmlspecialchars($m_emp['status'] ?? 'Active') ?>
                            </span>
                        </div>

                        <div class="card-body p-4">
                            <div class="row">
                                <div class="col-md-6 border-end">
                                    <h6 class="text-success fw-bold text-uppercase small mb-3">Professional Details</h6>
                                    
                                    <div class="mb-3">
                                        <label class="small text-muted fw-bold">Department / Ward</label>
                                        <div><?= htmlspecialchars($m_emp['department']) ?></div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="small text-muted fw-bold">Role</label>
                                        <div><?= htmlspecialchars($m_emp['role']) ?></div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="small text-muted fw-bold">Email Address</label>
                                        <div><?= htmlspecialchars($m_emp['email'] ?? 'Not set') ?></div>
                                    </div>
                                </div>

                                <div class="col-md-6 ps-md-4">
                                    <h6 class="text-success fw-bold text-uppercase small mb-3">Shift Settings</h6>
                                    
                                    <div class="mb-3">
                                        <label class="small text-muted fw-bold">Work Status</label>
                                        <div><?= htmlspecialchars($m_prof['clinical_status'] ?? 'Active') ?></div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="small text-muted fw-bold">Preferred Shift</label>
                                        <div><?= htmlspecialchars($m_prof['preferred_shift'] ?? 'Rotating') ?></div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="small text-muted fw-bold">Weekly Hours</label>
                                        <div><?= htmlspecialchars($m_prof['max_hours_per_week'] ?? '40') ?> Hours</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer bg-light">
                            <small class="text-muted me-auto">Nurse ID: #<?= htmlspecialchars($m_emp['employee_id']) ?></small>
                            <small class="text-muted me-auto">License: #<?= htmlspecialchars($m_emp['license_number']) ?></small>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-4 text-center">
                        <div class="alert alert-warning">No nurse profile data found.</div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>