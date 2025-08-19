document.addEventListener("DOMContentLoaded", function () {
  const calendarEl = document.getElementById("scheduleCalendar");

  // Hover box for Month view
  const hoverBox = document.createElement("div");
  Object.assign(hoverBox.style, {
    position: "absolute",
    background: "#fff",
    border: "1px solid #ccc",
    padding: "10px",
    borderRadius: "6px",
    boxShadow: "0 2px 6px rgba(0,0,0,.2)",
    display: "none",
    zIndex: "1000",
  });
  document.body.appendChild(hoverBox);

  // Modal (hidden by default)
  const modal = document.createElement("div");
  modal.innerHTML = `
        <div id="calendarModal" style="
            display:none; position:fixed; top:0; left:0; width:100%; height:100%;
            background: rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:center;
        ">
            <div style="background:#fff; padding:20px; border-radius:8px; max-width:500px; width:90%; max-height:80%; overflow:auto;">
                <span id="closeModal" style="float:right; cursor:pointer; font-weight:bold;">&times;</span>
                <div id="modalContent"></div>
            </div>
        </div>
    `;
  document.body.appendChild(modal);

  const modalEl = document.getElementById("calendarModal");
  const modalContent = document.getElementById("modalContent");
  const closeModal = document.getElementById("closeModal");
  closeModal.onclick = () => (modalEl.style.display = "none");
  window.onclick = (e) => {
    if (e.target === modalEl) modalEl.style.display = "none";
  };

  // Date helpers
  function toYMD(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
  }

  function placeHover(e) {
    hoverBox.style.left = e.clientX + 12 + window.scrollX + "px";
    hoverBox.style.top = e.clientY + 12 + window.scrollY + "px";
  }

  function formatTime(timeStr) {
    if (!timeStr) return "";

    if (/am|pm/i.test(timeStr)) {
      return timeStr.replace(/\s+/g, " ").toUpperCase();
    }

    const [h, m] = timeStr.split(":");
    let hour = parseInt(h, 10);
    const minute = (m || "00").padStart(2, "0");
    const ampm = hour >= 12 ? "PM" : "AM";
    hour = hour % 12 || 12;
    return `${hour.toString().padStart(2, "0")}:${minute} ${ampm}`;
  }

  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: "dayGridMonth",
    timeZone: "local",
    events: "oop/docref.php?action=schedules",
    eventColor: "#007bff",
    eventTextColor: "#fff",

    headerToolbar: {
      left: "prev,next today",
      center: "title",
      right: "dayGridMonth,timeGridWeek,timeGridDay",
    },

    allDaySlot: false,

    slotLabelFormat: {
      hour: "numeric",
      minute: "2-digit",
      hour12: true,
    },

    // 🚀 key settings for stacking
    eventOverlap: false,
    slotEventOverlap: false,
    eventMaxStack: 10,  // allow multiple stacked vertically

    eventContent: function (arg) {
      let patient = arg.event.extendedProps.patient || "";
      let service = arg.event.extendedProps.service || "";

      return {
        html: `
          <div style="font-size:12px; line-height:1.2;">
            <strong>${patient}</strong><br>
            <span>${service}</span>
          </div>
        `,
      };
    },

    // ✅ Ensure vertical stacking in Week/Day
    eventDidMount: function (info) {
      if (
        info.view.type === "timeGridWeek" ||
        info.view.type === "timeGridDay"
      ) {
        info.el.style.position = "relative";
        info.el.style.width = "100%";   // full width instead of side-by-side
        info.el.style.marginTop = "2px"; // gap between stacked patients
      }
    },

    // Month hover tooltip
    dayCellDidMount: function (info) {
      const dateStr = toYMD(info.date);

      info.el.addEventListener("mouseenter", function (e) {
        if (calendar.view.type !== "dayGridMonth") return;

        fetch(
          `oop/docref.php?action=dayDetails&date=${encodeURIComponent(dateStr)}`
        )
          .then((r) => r.json())
          .then((data) => {
            let html = `<strong>${dateStr}</strong><br>`;
            if (Array.isArray(data) && data.length) {
              data.forEach((d) => {
                html += `${d.patient} — ${d.service} — ${formatTime(
                  d.time
                )}<br>`;
              });
            } else {
              html += "No appointments";
            }
            hoverBox.innerHTML = html;
            hoverBox.style.display = "block";
            placeHover(e);
          });
      });

      info.el.addEventListener("mousemove", placeHover);
      info.el.addEventListener("mouseleave", () => {
        hoverBox.style.display = "none";
      });
    },

    // Show modal on click in Week/Day
    dateClick: function (info) {
      if (
        calendar.view.type === "timeGridWeek" ||
        calendar.view.type === "timeGridDay"
      ) {
        const dateStr = toYMD(info.date);

        fetch(
          `oop/docref.php?action=dayDetails&date=${encodeURIComponent(dateStr)}`
        )
          .then((r) => r.json())
          .then((data) => {
            let html = `<h3>${dateStr}</h3>`;
            if (Array.isArray(data) && data.length) {
              html += `<table style="width:100%; border-collapse:collapse;">
                        <thead><tr><th>Patient</th><th>Service</th><th>Time</th></tr></thead><tbody>`;
              data.forEach((d) => {
                html += `<tr>
                          <td>${d.patient}</td>
                          <td>${d.service}</td>
                          <td>${formatTime(d.time)}</td>
                        </tr>`;
              });
              html += `</tbody></table>`;
            } else {
              html += "<p>No appointments</p>";
            }

            modalContent.innerHTML = html;
            modalEl.style.display = "flex";
          });
      }
    },
  });

  
  calendar.render();
});
