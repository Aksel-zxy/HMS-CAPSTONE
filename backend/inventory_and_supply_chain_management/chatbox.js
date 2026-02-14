let step = 0;
let mode = "";
let requestData = { equipment: "", issue: "", location: "", priority: "" };
let currentUserId = null;
let currentUserName = ""; // Store logged-in user's full name

document.addEventListener("DOMContentLoaded", function () {
  const chatWidget = document.getElementById("chat-widget");
  const chatButton = document.getElementById("chat-button");
  const closeChat = document.getElementById("close-chat");

  chatWidget.style.display = "none";

  chatButton.onclick = function() {
    chatWidget.style.display = "flex";
    if(step === 0) startConversation();
  };

  closeChat.onclick = function() {
    chatWidget.style.display = "none";
  };

  document.getElementById("send-btn").onclick = handleInput;
  document.getElementById("msg").addEventListener("keypress", function(e) {
    if(e.key === "Enter") handleInput();
  });

  // Fetch logged-in user info asynchronously
  fetchLoggedUser();
});

// =====================
// Fetch logged-in user
// =====================
function fetchLoggedUser() {
  fetch("get_logged_user.php", { credentials: "same-origin" })
    .then(res => res.json())
    .then(data => {
      if(data.success) {
        currentUserId = data.user_id;
        currentUserName = data.name;
      }
    })
    .catch(err => console.error("Error fetching user:", err));
}

// =====================
// Helper to append messages
// =====================
function appendMessage(html, sender = "bot") {
  const chatbox = document.getElementById("chatbox");
  chatbox.insertAdjacentHTML('beforeend', `<div class='${sender}'>${html}</div>`);
  chatbox.scrollTop = chatbox.scrollHeight;
}

// =====================
// Start conversation
// =====================
function startConversation() {
  let welcomeMsg = currentUserName ? `Welcome, <strong>${currentUserName}</strong>!<br><br>` : "";
  appendMessage(`${welcomeMsg}Hi! What would you like to do?<br><br>
    <button onclick="selectMode('report')">🛠️ Report Repair</button>
    <button onclick="selectMode('status')">📋 Check Status</button>
    <button onclick="selectMode('mytickets')">📁 My Tickets</button>
  `);
  step = 1;
}

// =====================
// Mode selection
// =====================
function selectMode(selected) {
  mode = selected;

  if(mode === "report") {
    appendMessage(`
      Please select equipment:<br><br>
      <button onclick="chooseEquipment('Computer')">💻 Computer</button>
      <button onclick="chooseEquipment('Printer')">🖨️ Printer</button>
      <button onclick="chooseEquipment('Machine')">⚙️ Machine</button>
    `);
    step = 2;

  } else if(mode === "status") {
    appendMessage("Please enter your Ticket Number:");
    step = 99;

  } else if(mode === "mytickets") {
    // If currentUserId not ready yet, fetch dynamically
    if(currentUserId) {
      fetchMyTickets();
    } else {
      fetch("get_logged_user.php", { credentials: "same-origin" })
        .then(res => res.json())
        .then(data => {
          if(data.success) {
            currentUserId = data.user_id;
            currentUserName = data.name;
            fetchMyTickets();
          } else {
            appendMessage("❌ You must be logged in to view your tickets.");
          }
        })
        .catch(err => appendMessage("❌ Failed to verify login."));
    }
    step = 200;
  }
}

// =====================
// Equipment selection
// =====================
function chooseEquipment(type) {
  requestData.equipment = type;
  appendMessage(`You selected: <strong>${type}</strong>`);
  appendMessage("Please describe the problem:");
  step = 3;
}

// =====================
// Computer AI
// =====================
function getComputerAIResponse(issue) {
  issue = issue.toLowerCase();
  if(issue.includes("slow")) return `
    🐢 Computer Running Slow:<br>
    1. Restart computer<br>
    2. Close unused apps<br>
    3. Check Task Manager<br>
    4. Run Disk Cleanup<br>`;
  if(issue.includes("internet")) return `
    🌐 Internet Problem:<br>
    1. Check WiFi/LAN cable<br>
    2. Restart router<br>
    3. Try another website<br>`;
  if(issue.includes("won't turn on") || issue.includes("no power")) return `
    🔌 No Power Issue:<br>
    1. Check power cable<br>
    2. Try different outlet<br>
    3. Check PSU switch<br>`;
  return `
    🔎 Basic Troubleshooting:<br>
    1. Restart device<br>
    2. Check cables<br>
    3. Update system<br>`;
}

// =====================
// Auto priority detection
// =====================
function autoDetectPriority(issue) {
  issue = issue.toLowerCase();
  if(issue.includes("won't turn on") || issue.includes("broken") || issue.includes("crash")) return "High";
  if(issue.includes("slow") || issue.includes("error")) return "Medium";
  return "Low";
}

// =====================
// Handle user input
// =====================
function handleInput() {
  const msgInput = document.getElementById("msg");
  let msg = msgInput.value.trim();
  if(!msg) return;

  appendMessage("You: " + msg, "user");
  msgInput.value = "";

  // STATUS CHECK
  if(step === 99) {
    fetch("check_status.php", {
      method: "POST",
      headers: {"Content-Type": "application/x-www-form-urlencoded"},
      body: "ticket_no=" + encodeURIComponent(msg)
    })
    .then(res => res.json())
    .then(data => {
      if(data.found) {
        appendMessage(`📋 Ticket: <strong>${data.ticket_no}</strong><br>Status: ${data.status}`);
      } else {
        appendMessage("❌ Ticket not found");
      }
      step = 0;
    })
    .catch(err => console.error(err));
    return;
  }

  // REPORT MODE
  if(mode === "report") {
    if(step === 3) {
      requestData.issue = msg;
      requestData.priority = autoDetectPriority(msg);

      if(requestData.equipment === "Computer") {
        const aiResponse = getComputerAIResponse(msg);
        appendMessage(`
          ${aiResponse}<br>
          Did this fix your issue?<br><br>
          <button onclick="issueResolved()">✅ Yes</button>
          <button onclick="proceedRepair()">❌ No, Create Ticket</button>
        `);
        step = 3.5;
        return;
      }

      appendMessage("Where is the location? (Department/Room)");
      step = 4;
      return;
    }

    if(step === 4) {
      requestData.location = msg;
      appendMessage("Logging your request...");

      fetch("chatbot.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body:
          "equipment=" + encodeURIComponent(requestData.equipment) +
          "&issue=" + encodeURIComponent(requestData.issue) +
          "&location=" + encodeURIComponent(requestData.location) +
          "&priority=" + encodeURIComponent(requestData.priority),
        credentials: "same-origin"
      })
      .then(res => res.json())
      .then(data => {
        if(data.success) {
          appendMessage(`✅ Ticket Created!<br>Ticket No: <strong>${data.ticket_no}</strong><br>Priority: ${data.priority}`);
        } else {
          appendMessage("❌ Failed to create ticket.");
        }
        step = 0;
        requestData = { equipment: "", issue: "", location: "", priority: "" };
      })
      .catch(err => console.error(err));

      return;
    }
  }

  // MY TICKETS
  if(step === 200) {
    fetchMyTickets();
    step = 0;
  }
}

// =====================
// Button actions
// =====================
function issueResolved() {
  appendMessage("✅ Great! No ticket was created.");
  step = 0;
}

function proceedRepair() {
  appendMessage("Please enter location (Department/Room):");
  step = 4;
}

// =====================
// Fetch My Tickets
// =====================
function fetchMyTickets() {
  fetch("my_tickets.php", {
    method: "POST",
    headers: {"Content-Type": "application/x-www-form-urlencoded"},
    body: "",
    credentials: "same-origin"
  })
  .then(res => res.json())
  .then(data => {
    if(data.success) {
      appendMessage(data.html);
    } else {
      appendMessage("No tickets found.");
    }
  })
  .catch(err => appendMessage("❌ Failed to fetch tickets."));
}
