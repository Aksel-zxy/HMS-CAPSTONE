<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Daily Insurance Claim Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- html2pdf for PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        .report-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0px 3px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .insight-box {
            background: #f8f9fa;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            border-radius: 8px;
        }
    </style>
</head>

<body class="bg-light">

    <div class="container py-4" id="reportArea">

        <h2 class="mb-4 fw-bold">Daily Insurance Claim Report</h2>

        <!-- SINGLE DATE FILTER + EXPORT BUTTONS -->
        <div class="card mb-4">
            <div class="card-body">
                <form class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Select Date</label>
                        <input type="date" id="report_date" class="form-control" value="2025-06-08">
                    </div>

                    <div class="col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-primary w-100" onclick="loadReport()">
                            Load Daily Report
                        </button>
                    </div>

                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="button" class="btn btn-success w-50" onclick="exportPDF()">
                            Export PDF
                        </button>
                        <button type="button" class="btn btn-secondary w-50" onclick="exportCSV()">
                            Export CSV
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row" id="summaryCards"></div>

        <!-- AI Insights -->
        <h4 class="fw-bold mt-4">AI Insights</h4>
        <div id="insightsBox" class="insight-box mb-4">
            Loading insights...
        </div>

    </div>

    <script>
        async function loadReport() {
            const date = document.getElementById("report_date").value;

            const url = `https://localhost:7212/insurance/dayInsuranceClaimReport?date=${date}`;

            const res = await fetch(url);
            const d = await res.json(); // SINGLE OBJECT

            renderSummary(d);
            generateInsights(d);
        }

        function renderSummary(d) {
            document.getElementById("summaryCards").innerHTML = `
        <div class="col-md-3">
            <div class="report-card">
                <h5>Submitted Amount</h5>
                <h3 class="text-primary">₱${d.claim_amount_submitted.toLocaleString()}</h3>
            </div>
        </div>

        <div class="col-md-3">
            <div class="report-card">
                <h5>Denied Amount</h5>
                <h3 class="text-danger">₱${d.claims_amount_denied.toLocaleString()}</h3>
            </div>
        </div>

        <div class="col-md-3">
            <div class="report-card">
                <h5>Total Claims</h5>
                <h3>${d.number_of_claims_submitted}</h3>
            </div>
        </div>

        <div class="col-md-3">
            <div class="report-card">
                <h5>Approval Rate</h5>
                <h3>${((d.claims_approved / d.number_of_claims_submitted) * 100 || 0).toFixed(1)}%</h3>
            </div>
        </div>
    `;
        }

        function generateInsights(d) {
            const approvalRate = ((d.claims_approved / d.number_of_claims_submitted) * 100 || 0).toFixed(1);
            const denyRate = ((d.claims_denied / d.number_of_claims_submitted) * 100 || 0).toFixed(1);

            let insight = `
        • On <b>${d.report_date}</b>, the hospital submitted <b>₱${d.claim_amount_submitted.toLocaleString()}</b> in claims.<br>
        • Denied claims total <b>₱${d.claims_amount_denied.toLocaleString()}</b>.<br>
        • Approval rate: <b>${approvalRate}%</b>, denial rate: <b>${denyRate}%</b>.<br>
    `;

            if (d.claims_denied > 0)
                insight += `⚠️ <b>Claims were denied today.</b> Recommended: review documentation and insurer requirements.`;

            if (d.claims_approved > 0)
                insight += `<br>✅ Some claims were successfully approved today.`;

            document.getElementById("insightsBox").innerHTML = insight;
        }

        loadReport();


        // ----------------------------
        // EXPORT PDF
        // ----------------------------
        function exportPDF() {
            const element = document.getElementById("reportArea");
            const options = {
                margin: 0.5,
                filename: `Daily_Insurance_Report_${document.getElementById("report_date").value}.pdf`,
                image: {
                    type: 'jpeg',
                    quality: 0.98
                },
                html2canvas: {
                    scale: 2
                },
                jsPDF: {
                    unit: 'in',
                    format: 'a4',
                    orientation: 'portrait'
                }
            };

            html2pdf().set(options).from(element).save();
        }

        // ----------------------------
        // EXPORT CSV
        // ----------------------------
        function exportCSV() {
            const date = document.getElementById("report_date").value;

            const submitted = document.querySelector("#summaryCards div:nth-child(1) h3").innerText.replace("₱", "").replace(/,/g, "");
            const denied = document.querySelector("#summaryCards div:nth-child(2) h3").innerText.replace("₱", "").replace(/,/g, "");
            const totalClaims = document.querySelector("#summaryCards div:nth-child(3) h3").innerText;
            const approval = document.querySelector("#summaryCards div:nth-child(4) h3").innerText;

            const csvContent =
                "Date,Submitted Amount,Denied Amount,Total Claims,Approval Rate\n" +
                `${date},${submitted},${denied},${totalClaims},${approval}\n`;

            const blob = new Blob([csvContent], {
                type: "text/csv;charset=utf-8;"
            });

            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = `Daily_Insurance_Report_${date}.csv`;
            link.click();
        }
    </script>

</body>

</html>