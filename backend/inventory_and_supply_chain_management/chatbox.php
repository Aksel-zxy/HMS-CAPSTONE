<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Repair Request Assistant</title>
  <link rel="stylesheet" href="assets/CSS/chatbot.css">

</head>
<body>

<!-- Floating Button -->
<div id="chat-button">🛠️</div>

<!-- Chat Widget -->
<div id="chat-widget">
  <div id="chat-header">
    Repair Assistant
    <span id="close-chat" style="cursor:pointer;">✖</span>
  </div>
  <div id="chatbox"></div>
  <div id="chat-input">
    <input type="text" id="msg" placeholder="Type your response..." />
    <button id="send-btn">Send</button>
  </div>
</div>

<script>
let step = 0;
let mode = "";
let requestData = { equipment: "", issue: "", location: "", priority: "" };

document.addEventListener("DOMContentLoaded", function () {
  const chatWidget = document.getElementById("chat-widget");

  // Ensure chat is hidden on page load
  chatWidget.style.display = "none";

  document.getElementById("chat-button").onclick = function() {
    chatWidget.style.display = "flex";
    if (step === 0) startConversation();
  };

  document.getElementById("close-chat").onclick = function() {
    chatWidget.style.display = "none";
  };

  document.getElementById("send-btn").onclick = handleInput;
  document.getElementById("msg").addEventListener("keypress", function(e) {
    if (e.key === "Enter") handleInput();
  });
});

function startConversation() {
  let chatbox = document.getElementById("chatbox");
  chatbox.innerHTML = `
    <div class='bot'>
      Hi! What would you like to do?<br>
      <button onclick="selectMode('report')">🛠️ Report Repair</button>
      <button onclick="selectMode('status')">📋 Check Status</button>
    </div>`;
  step = 1;
}

function selectMode(selected) {
  mode = selected;
  let chatbox = document.getElementById("chatbox");

  if (mode === "report") {
    chatbox.innerHTML += `
      <div class='bot'>
        Please select the equipment:<br>
        <button onclick="chooseEquipment('Computer')">💻 Computer</button>
        <button onclick="chooseEquipment('Printer')">🖨️ Printer</button>
        <button onclick="chooseEquipment('Machine')">⚙️ Machine</button>
      </div>`;
    step = 2;
  } else if (mode === "status") {
    chatbox.innerHTML += "<div class='bot'>Please enter your Ticket Number:</div>";
    step = 99;
  }
  chatbox.scrollTop = chatbox.scrollHeight;
}

function chooseEquipment(type) {
  requestData.equipment = type;
  let chatbox = document.getElementById("chatbox");
  chatbox.innerHTML += "<div class='bot'>You selected: " + type + "</div>";

  if (type === "Printer") {
    chatbox.innerHTML += `
      <div class='bot'>
        🔧 Basic Troubleshooting for Printer:<br>
        1. Check if printer has paper.<br>
        2. Restart the printer.<br>
        3. Check cable connection.<br>
        Do you still want to proceed with a repair request?<br>
        <button onclick="confirmProceed()">Yes, continue</button>
        <button onclick="cancelRequest()">No, it’s fixed</button>
      </div>`;
    step = 2.5;
  } else {
    chatbox.innerHTML += "<div class='bot'>What problem are you experiencing with the " + type + "?</div>";
    step = 3;
  }
  chatbox.scrollTop = chatbox.scrollHeight;
}

function confirmProceed() {
  let chatbox = document.getElementById("chatbox");
  chatbox.innerHTML += "<div class='bot'>Okay, what problem are you experiencing with the Printer?</div>";
  step = 3;
}

function cancelRequest() {
  let chatbox = document.getElementById("chatbox");
  chatbox.innerHTML += "<div class='bot'>✅ Great! Issue resolved without repair request.</div>";
  step = 0;
}

function showTyping(chatbox) {
  let typing = document.createElement("div");
  typing.className = "bot typing";
  typing.innerHTML = "Bot is typing...";
  chatbox.appendChild(typing);
  chatbox.scrollTop = chatbox.scrollHeight;
  return typing;
}

function autoDetectPriority(issue) {
  issue = issue.toLowerCase();
  if (issue.includes("won't turn on") || issue.includes("not working") || issue.includes("cannot") || issue.includes("broken") || issue.includes("crash")) {
    return "High";
  } else if (issue.includes("slow") || issue.includes("sometimes") || issue.includes("error")) {
    return "Medium";
  } else {
    return "Low";
  }
}

function handleInput() {
  let msg = document.getElementById("msg").value.trim();
  if (!msg) return;
  let chatbox = document.getElementById("chatbox");
  chatbox.innerHTML += "<div class='user'>You: " + msg + "</div>";
  document.getElementById("msg").value = "";
  let typing = showTyping(chatbox);

  setTimeout(() => {
    typing.remove();

    if (step === 99) {
      fetch("check_status.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "ticket_no=" + encodeURIComponent(msg)
      })
      .then(res => res.json())
      .then(data => {
        if (data.found) {
          chatbox.innerHTML += `
            <div class='bot receipt'>
              📋 Ticket No: <strong>${data.ticket_no}</strong><br>
              Status: <strong>${data.status}</strong><br>
              Created At: ${data.created_at}
            </div>`;
        } else {
          chatbox.innerHTML += "<div class='bot'>❌ Ticket not found.</div>";
        }
        chatbox.scrollTop = chatbox.scrollHeight;
      });
      step = 0;
      return;
    }

    if (mode === "report") {
      if (step === 3) {
        requestData.issue = msg;
        requestData.priority = autoDetectPriority(msg);
        chatbox.innerHTML += "<div class='bot'>Where is this equipment located? (Department/Room)</div>";
        step = 4;
      } else if (step === 4) {
        requestData.location = msg;
        chatbox.innerHTML += "<div class='bot'>Thank you! Logging your request...</div>";

        fetch("chatbot.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: "equipment=" + encodeURIComponent(requestData.equipment) +
                "&issue=" + encodeURIComponent(requestData.issue) +
                "&location=" + encodeURIComponent(requestData.location) +
                "&priority=" + encodeURIComponent(requestData.priority)
        })
        .then(res => res.json())
        .then(data => {
          chatbox.innerHTML += `
            <div class='bot receipt'>
              ✅ Request Logged!<br>
              Ticket No: <strong>${data.ticket_no}</strong><br>
              Date: ${data.created_at}<br>
              Status: <strong>${data.status}</strong><br>
              Priority: <strong>${data.priority}</strong>
            </div>`;
          chatbox.scrollTop = chatbox.scrollHeight;
        });

        step = 0;
        requestData = { equipment: "", issue: "", location: "", priority: "" };
      }
    }
    chatbox.scrollTop = chatbox.scrollHeight;
  }, 1000);
}
</script>
</body>
</html>
