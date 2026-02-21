<?php
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Insurance Report</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }

        .card {
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .insight-box {
            background: #fff;
            border-left: 4px solid #0d6efd;
            padding: 20px;
            border-radius: 10px;
            margin-top: 25px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .badge-approved {
            background-color: #198754;
        }

        .badge-denied {
            background-color: #dc3545;
        }
    </style>
</head>

<body>
    <div class="d-flex">

        <?php include 'sidebar.php'; ?>

        <div class="container py-5">

            <h2 class="text-center mb-4" id="reportTitle">Monthly Insurance Report</h2>

            <!-- Summary -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-center p-3">
                        <h6>Total Claims</h6>
                        <h3 id="totalClaims">-</h3>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card text-center p-3">
                        <h6>Approved</h6>
                        <h3 id="totalApproved">-</h3>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card text-center p-3">
                        <h6>Denied</h6>
                        <h3 id="totalDenied">-</h3>
                    </div>
                </div>
            </div>

            <!-- Progress -->
            <div class="progress mb-4" style="height:25px">
                <div id="approvedBar" class="progress-bar bg-success"></div>
                <div id="deniedBar" class="progress-bar bg-danger"></div>
            </div>

            <!-- INSIGHTS -->
            <div id="insightSection" class="insight-box">
                <h5 class="fw-bold mb-2">📌 Insights</h5>
                <div id="insightContent">Loading insights...</div>
            </div>

            <!-- Table -->
            <div class="table-responsive mt-4">
                <table class="table table-hover table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Provider</th>
                            <th>Contact Person</th>
                            <th>Contact Number</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Resolved</th>
                        </tr>
                    </thead>
                    <tbody id="claimsTableBody"></tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <button class="btn btn-outline-secondary" id="prevBtn">Previous</button>
                <span id="pageInfo"></span>
                <button class="btn btn-outline-secondary" id="nextBtn">Next</button>
            </div>

        </div>
    </div>

    <script>
        // ---------------------------------------------------------------------
        // PROVIDER MAPPING
        // ---------------------------------------------------------------------
        const providerMap = {
            1: {
                name: "PhilHealth",
                contact_person: "Customer Service",
                contact_number: "0284417442"
            },
            2: {
                name: "Maxicare Healthcare Corporation",
                contact_person: "Account Manager",
                contact_number: "0285821980"
            },
            3: {
                name: "Medicard Philippines",
                contact_person: "Client Services",
                contact_number: "0288469999"
            },
            4: {
                name: "Intellicare (Asalus Corporation)",
                contact_person: "Customer Support",
                contact_number: "0287894000"
            },
            5: {
                name: "Pacific Cross Philippines",
                contact_person: "Policy Services",
                contact_number: "0288766111"
            },
            6: {
                name: "Cocolife Healthcare",
                contact_person: "Member Services",
                contact_number: "0281889000"
            },
            7: {
                name: "AXA Philippines",
                contact_person: "Customer Care",
                contact_number: "0285811111"
            },
            8: {
                name: "Sun Life Philippines",
                contact_person: "Client Relations",
                contact_number: "0284918888"
            },
            9: {
                name: "Manulife Philippines",
                contact_person: "Policy Support",
                contact_number: "0288847000"
            },
            10: {
                name: "Prudential Guarantee & Assurance (PGA)",
                contact_person: "Claims Department",
                contact_number: "0288192222"
            },
            11: {
                name: "Etiqa Philippines",
                contact_person: "Customer Relations",
                contact_number: "0288895800"
            },
            12: {
                name: "Generali Philippines",
                contact_person: "Member Assistance",
                contact_number: "0288708888"
            },
            13: {
                name: "Insular Health Care (InLife)",
                contact_person: "Healthcare Services",
                contact_number: "0288447000"
            },
            14: {
                name: "ValuCare Health Systems",
                contact_person: "Provider Relations",
                contact_number: "0282342000"
            }
        };

        // URL PARAMS
        const params = new URLSearchParams(window.location.search);
        const month = params.get("month");
        const year = params.get("year");

        const monthNames = ["January", "February", "March", "April", "May", "June", "July",
            "August", "September", "October", "November", "December"
        ];

        if (month && year) {
            reportTitle.innerText = `${monthNames[month - 1]} ${year} Insurance Report`;
        }

        let currentPage = 1;
        const pageSize = 5;

        prevBtn.onclick = () => {
            if (currentPage > 1) {
                currentPage--;
                fetchReport();
            }
        };
        nextBtn.onclick = () => {
            currentPage++;
            fetchReport();
        };

        async function fetchReport() {
            const url = `https://bsis-03.keikaizen.xyz/insurance/getMonthInsuranceReport?month=${month}&year=${year}&page=${currentPage}&size=${pageSize}`;
            const res = await fetch(url);
            const data = await res.json();

            totalClaims.innerText = data.totalClaims;
            totalApproved.innerText = data.totalApprovedClaims;
            totalDenied.innerText = data.totalDeniedClaims;

            const approvedPct = (data.totalApprovedClaims / data.totalClaims * 100).toFixed(1);
            const deniedPct = (data.totalDeniedClaims / data.totalClaims * 100).toFixed(1);

            approvedBar.style.width = approvedPct + "%";
            deniedBar.style.width = deniedPct + "%";

            approvedBar.innerText = `Approved ${approvedPct}%`;
            deniedBar.innerText = `Denied ${deniedPct}%`;

            pageInfo.innerText = `Page ${currentPage}`;

            renderTable(data.claimsList);
            generateInsights(data);
        }

        function renderTable(claims) {
            claimsTableBody.innerHTML = "";

            claims.forEach(c => {
                const p = providerMap[c.insurance_provider_id] || {
                    name: "Unknown",
                    contact_person: "-",
                    contact_number: "-"
                };
                const statusClass = c.status === "approved" ? "badge-approved" : "badge-denied";

                claimsTableBody.innerHTML += `
                    <tr>
                        <td>${p.name}</td>
                        <td>${p.contact_person}</td>
                        <td>${p.contact_number}</td>
                        <td>₱${Number(c.claim_amount).toLocaleString()}</td>
                        <td><span class="badge ${statusClass}">${c.status}</span></td>
                        <td>${c.submmited_date}</td>
                        <td>${c.resolved_date}</td>
                    </tr>`;
            });
        }

        // ---------------------------------------------------------------------
        // INSIGHT GENERATOR
        // ---------------------------------------------------------------------
        function generateInsights(data) {
            if (!data || !data.claimsList) return;

            const list = data.claimsList;
            let insights = [];

            const approvedPct = (data.totalApprovedClaims / data.totalClaims * 100).toFixed(1);
            const deniedPct = (data.totalDeniedClaims / data.totalClaims * 100).toFixed(1);

            insights.push(`✔ <b>${approvedPct}%</b> of all claims were approved this month.`);
            insights.push(`✖ <b>${deniedPct}%</b> of claims were denied.`);

            // Top provider by volume
            let providerCount = {};
            let largestClaim = 0;
            let largestProvider = "";

            list.forEach(c => {
                const provider = providerMap[c.insurance_provider_id]?.name || "Unknown";

                providerCount[provider] = (providerCount[provider] || 0) + 1;

                if (c.claim_amount > largestClaim) {
                    largestClaim = c.claim_amount;
                    largestProvider = provider;
                }
            });

            const topProvider = Object.entries(providerCount).sort((a, b) => b[1] - a[1])[0];

            if (topProvider) {
                insights.push(`🏆 Most claims this month came from <b>${topProvider[0]}</b>.`);
            }

            insights.push(`💰 Highest payout request was <b>₱${largestClaim.toLocaleString()}</b> from ${largestProvider}.`);

            document.getElementById("insightContent").innerHTML =
                insights.map(i => `<p>${i}</p>`).join("");
        }

        fetchReport();
    </script>

</body>

</html>