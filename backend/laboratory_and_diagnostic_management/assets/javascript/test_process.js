// Sidebar toggle
document.querySelector(".toggler-btn")?.addEventListener("click", function () {
  document.querySelector("#sidebar").classList.toggle("collapsed");
});

document.querySelectorAll(".edit-btn").forEach((button) => {
  button.addEventListener("click", function () {
    document.getElementById("modalScheduleId").value = this.dataset.id;
    document.getElementById("modalScheduleDate").value = this.dataset.date;
    document.getElementById("modalScheduleTime").value = this.dataset.time;
    document.getElementById("modalStatus").value = this.dataset.status;
  });
});

function askCancelReason(scheduleID) {
  const reason = prompt("Please provide a reason for cancellation:");
  if (reason !== null && reason.trim() !== "") {
    document.getElementById("cancelReasonInput_" + scheduleID).value = reason;
    document
      .querySelector("input[name='scheduleID'][value='" + scheduleID + "']")
      .form.submit();
  } else {
    alert("❌ Cancellation aborted. Reason is required.");
  }
}
let tests = [];
let currentIndex = 0;

function showResult(index) {
  let scheduleID = tests[index]?.scheduleID;
  let testType = tests[index]?.serviceName;

  if (!scheduleID || !testType) {
    document.getElementById("resultContent").innerHTML =
      "<p class='text-danger'>Unknown test type or missing data.</p>";
    return;
  }

  // Update modal title
  document.getElementById("modalTitle").innerText = testType + " Result";

  // Show loading
  document.getElementById("resultContent").innerHTML =
    "<p class='text-center text-muted'>Loading result...</p>";

  // Fetch result
  fetch(
    "get_result.php?scheduleID=" +
      scheduleID +
      "&testType=" +
      encodeURIComponent(testType)
  )
    .then((response) => response.text())
    .then((data) => {
      document.getElementById("resultContent").innerHTML = data;
    })
    .catch((err) => {
      document.getElementById("resultContent").innerHTML =
        "<p class='text-danger'>Error loading result.</p>";
    });

  // Handle prev/next visibility
  const prevBtn = document.getElementById("prevResult");
  const nextBtn = document.getElementById("nextResult");

  if (tests.length <= 1) {
    prevBtn.style.display = "none";
    nextBtn.style.display = "none";
  } else {
    prevBtn.style.display = index === 0 ? "none" : "inline-block";
    nextBtn.style.display =
      index === tests.length - 1 ? "none" : "inline-block";
  }
}

// Prev / Next handlers
document.getElementById("prevResult").addEventListener("click", function () {
  if (currentIndex > 0) {
    currentIndex--;
    showResult(currentIndex);
  }
});

document.getElementById("nextResult").addEventListener("click", function () {
  if (currentIndex < tests.length - 1) {
    currentIndex++;
    showResult(currentIndex);
  }
});

// Click handler for each button
document.querySelectorAll(".view-result-btn").forEach((button) => {
  button.addEventListener("click", function () {
    // Parse JSON from button's data-results
    tests = JSON.parse(this.dataset.results || "[]");

    currentIndex = 0;
    showResult(currentIndex);
  });
});
document.addEventListener("DOMContentLoaded", function () {
  const aiButtons = document.querySelectorAll(".ai-impression-btn");
  const aiText = document.getElementById("aiImpressionText");

  aiButtons.forEach((btn) => {
    btn.addEventListener("click", function () {
      aiText.textContent = this.getAttribute("data-impression");
    });
  });
});
document.addEventListener("DOMContentLoaded", function () {
  const remarksModal = document.getElementById("remarksModal");
  remarksModal.addEventListener("show.bs.modal", function (event) {
    const button = event.relatedTarget;

    // Get data from button
    const patientID = button.getAttribute("data-patientid");
    const scheduleIDs = button.getAttribute("data-scheduleids");
    const testList = button.getAttribute("data-testlist");

    // Fill modal hidden inputs
    document.getElementById("remarksPatientID").value = patientID;
    document.getElementById("remarksScheduleIDs").value = scheduleIDs;
    document.getElementById("remarksTestList").value = testList;
  });
});
document.addEventListener("DOMContentLoaded", function () {
  const searchInput = document.getElementById("searchInput");
  const tableRows = document.querySelectorAll("tbody tr");

  searchInput.addEventListener("keyup", function () {
    let filter = searchInput.value.toLowerCase();

    tableRows.forEach((row) => {
      let text = row.textContent.toLowerCase();
      if (text.includes(filter)) {
        row.style.display = "";
      } else {
        row.style.display = "none";
      }
    });
  });
});
// Global fix for accessibility warning: prevent focus inside hidden modals
document.addEventListener("DOMContentLoaded", function () {
  let lastTriggerButton = null;

  // Track which element opened a modal
  document.querySelectorAll("[data-bs-toggle='modal']").forEach((btn) => {
    btn.addEventListener("click", function () {
      lastTriggerButton = this;
    });
  });

  // Apply fix to all modals
  document.querySelectorAll(".modal").forEach((modal) => {
    // 1️ Before the modal hides, blur any focused element inside it
    modal.addEventListener("hide.bs.modal", function () {
      if (document.activeElement && modal.contains(document.activeElement)) {
        document.activeElement.blur();
      }
    });

    // 2️ After fully hidden, restore focus to opener or body
    modal.addEventListener("hidden.bs.modal", function () {
      if (lastTriggerButton && document.body.contains(lastTriggerButton)) {
        lastTriggerButton.focus();
      } else {
        document.body.focus();
      }
    });
  });
});
