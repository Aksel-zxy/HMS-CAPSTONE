// Sidebar toggle
document.querySelector(".toggler-btn")?.addEventListener("click", function () {
  document.querySelector("#sidebar").classList.toggle("collapsed");
});

// Handle edit buttons (if any)
document.querySelectorAll(".edit-btn").forEach((button) => {
  button.addEventListener("click", function () {
    const id = document.getElementById("modalScheduleId");
    const date = document.getElementById("modalScheduleDate");
    const time = document.getElementById("modalScheduleTime");
    const status = document.getElementById("modalStatus");

    if (id && date && time && status) {
      id.value = this.dataset.id;
      date.value = this.dataset.date;
      time.value = this.dataset.time;
      status.value = this.dataset.status;
    }
  });
});

// Ask for cancellation reason
function askCancelReason(scheduleID) {
  const reason = prompt("Please provide a reason for cancellation:");
  if (reason !== null && reason.trim() !== "") {
    const input = document.getElementById("cancelReasonInput_" + scheduleID);
    const formInput = document.querySelector(
      "input[name='scheduleID'][value='" + scheduleID + "']"
    );
    if (input && formInput?.form) {
      input.value = reason;
      formInput.form.submit();
    }
  } else {
    alert("❌ Cancellation aborted. Reason is required.");
  }
}

let tests = [];
let currentIndex = 0;

function showResult(index) {
  let scheduleID = tests[index]?.scheduleID;
  let testType = tests[index]?.serviceName;

  const resultContent = document.getElementById("resultContent");
  const modalTitle = document.getElementById("modalTitle");
  const prevBtn = document.getElementById("prevResult");
  const nextBtn = document.getElementById("nextResult");

  if (!resultContent || !modalTitle) return;

  if (!scheduleID || !testType) {
    resultContent.innerHTML =
      "<p class='text-danger'>Unknown test type or missing data.</p>";
    return;
  }

  modalTitle.innerText = testType + " Result";
  resultContent.innerHTML =
    "<p class='text-center text-muted'>Loading result...</p>";

  fetch(
    "get_result.php?scheduleID=" +
      scheduleID +
      "&testType=" +
      encodeURIComponent(testType)
  )
    .then((response) => response.text())
    .then((data) => {
      resultContent.innerHTML = data;
    })
    .catch(() => {
      resultContent.innerHTML =
        "<p class='text-danger'>Error loading result.</p>";
    });

  if (prevBtn && nextBtn) {
    if (tests.length <= 1) {
      prevBtn.style.display = "none";
      nextBtn.style.display = "none";
    } else {
      prevBtn.style.display = index === 0 ? "none" : "inline-block";
      nextBtn.style.display =
        index === tests.length - 1 ? "none" : "inline-block";
    }
  }
}

// Prev / Next handlers
document.getElementById("prevResult")?.addEventListener("click", function () {
  if (currentIndex > 0) {
    currentIndex--;
    showResult(currentIndex);
  }
});

document.getElementById("nextResult")?.addEventListener("click", function () {
  if (currentIndex < tests.length - 1) {
    currentIndex++;
    showResult(currentIndex);
  }
});

// Click handler for view result buttons
document.querySelectorAll(".view-result-btn").forEach((button) => {
  button.addEventListener("click", function () {
    tests = JSON.parse(this.dataset.results || "[]");
    currentIndex = 0;
    showResult(currentIndex);
  });
});

// Wait for DOM
document.addEventListener("DOMContentLoaded", function () {
  // --- AI Impression Modal (safe for missing elements) ---
  const aiButtons = document.querySelectorAll(".ai-impression-btn");
  const aiText =
    document.getElementById("aiImpressionText") ||
    document.getElementById("impressionText"); // support both IDs

  if (aiButtons.length && aiText) {
    aiButtons.forEach((btn) => {
      btn.addEventListener("click", function () {
        aiText.textContent =
          this.getAttribute("data-impression") || "No remarks available.";
      });
    });
  }

  // --- Remarks Modal (safe for missing elements) ---
  const remarksModal = document.getElementById("remarksModal");
if (remarksModal) {
  remarksModal.addEventListener("show.bs.modal", function (event) {
    const button = event.relatedTarget;

    // Get data from button
    const patientID = button.getAttribute("data-patientid");
    const scheduleIDs = button.getAttribute("data-scheduleids");
    const testList = button.getAttribute("data-testlist");

    // Fill modal hidden inputs
    const pid = document.getElementById("remarksPatientID");
    const sid = document.getElementById("remarksScheduleIDs");
    const tlist = document.getElementById("remarksTestList");

    if (pid) pid.value = patientID;
    if (sid) sid.value = scheduleIDs;
    if (tlist) tlist.value = testList;
  });
}

  // --- Search Filtering (safe for missing inputs) ---
  const searchInput = document.getElementById("searchInput");
  if (searchInput) {
    const tableRows = document.querySelectorAll("tbody tr");
    searchInput.addEventListener("keyup", function () {
      let filter = searchInput.value.toLowerCase();
      tableRows.forEach((row) => {
        let text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? "" : "none";
      });
    });
  }

  // --- Accessibility Fix for Modals ---
  let lastTriggerButton = null;

  document.querySelectorAll("[data-bs-toggle='modal']").forEach((btn) => {
    btn.addEventListener("click", function () {
      lastTriggerButton = this;
    });
  });

  document.querySelectorAll(".modal").forEach((modal) => {
    modal.addEventListener("hide.bs.modal", function () {
      if (document.activeElement && modal.contains(document.activeElement)) {
        document.activeElement.blur();
      }
    });

    modal.addEventListener("hidden.bs.modal", function () {
      if (lastTriggerButton && document.body.contains(lastTriggerButton)) {
        lastTriggerButton.focus();
      } else {
        document.body.focus();
      }
    });
  });
});
