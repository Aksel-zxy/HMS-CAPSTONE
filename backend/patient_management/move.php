<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Move Patient</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
    body {
        background: #f1f4f9;
        height: 100vh;
        overflow: hidden;
    }

    /* Card animation */
    .slide-in {
        animation: slideIn 0.8s ease forwards;
    }

    @keyframes slideIn {
        from {
            transform: translateY(-30px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    /* Patient icon */
    .patient {
        position: absolute;
        bottom: 30px;
        left: 30px;
        font-size: 60px;
        transition: transform 2s ease;
    }

    .move {
        transform: translateX(700px);
    }
    </style>
</head>

<body class="d-flex align-items-center justify-content-center">


    <!-- Transfer Patient Modal -->
    <div class="modal fade" id="moveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg slide-in">

                <div class="modal-header">
                    <h5 class="modal-title">Transfer Patient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <form onsubmit="movePatient(); return false;">

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" placeholder="Patient ID" required>
                            <label>Patient ID</label>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" placeholder="Patient Name" required>
                            <label>Patient Name</label>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col">
                                <div class="form-floating">
                                    <input type="text" class="form-control" placeholder="From Room" required>
                                    <label>From Room</label>
                                </div>
                            </div>
                            <div class="col">
                                <div class="form-floating">
                                    <input type="text" class="form-control" placeholder="To Room" required>
                                    <label>To Room</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-floating mb-3">
                            <select class="form-select">
                                <option>Transfer</option>
                                <option>Discharge</option>
                                <option>Emergency</option>
                            </select>
                            <label>Reason</label>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                Move Patient
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>

    <style>
    .slide-in {
        animation: slideIn 0.6s ease;
    }

    @keyframes slideIn {
        from {
            transform: translateY(-20px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    </style>

    <script>
    function movePatient() {
        alert("Patient transferred successfully!");

        const modalEl = document.getElementById('moveModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
    }
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    function movePatient() {
        document.getElementById("patient").classList.add("move");

        setTimeout(() => {
            alert("Patient movement recorded successfully!");
        }, 500);
    }
    </script>

</body>

</html>