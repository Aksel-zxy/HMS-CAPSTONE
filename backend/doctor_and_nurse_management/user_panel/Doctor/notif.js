// Global variables
let licenses = [];
let currentNotificationCount = 0;

function loadNotifications() {
  console.log("Fetching notifications..."); // DEBUG
  fetch("fetch_notif.php")
    .then((response) => {
        if (!response.ok) { throw new Error("HTTP Status " + response.status); }
        return response.json();
    })
    .then((data) => {
      console.log("Data received from PHP:", data); // DEBUG: Check your browser console
      licenses = data;
      checkNotifications();
    })
    .catch((error) => console.error("Error fetching notifications:", error));
}

function checkNotifications() {
  const listElement = document.getElementById("notification-list");
  const badgeElement = document.getElementById("notification-count");
  
  const today = new Date();
  today.setHours(0, 0, 0, 0); 
  
  const warningThreshold = 30;

  currentNotificationCount = 0;
  listElement.innerHTML = "";

  licenses.sort((a, b) => new Date(a.expiryDate) - new Date(b.expiryDate));

  licenses.forEach((license) => {
    const expiry = new Date(license.expiryDate);
    expiry.setHours(0, 0, 0, 0);

    if (isNaN(expiry.getTime())) {
        console.error("Invalid Date found:", license.expiryDate);
        return; 
        
    }


    const diffTime = expiry - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    let statusClass = "";
    let dateMsg = "";

    
    if (diffDays < 0) {
      statusClass = "status-expired";
      dateMsg = `Expired ${Math.abs(diffDays)} days ago (${license.expiryDate})`;
      currentNotificationCount++;
      addNotifyItem(license.name, dateMsg, statusClass);
    } 
    else if (diffDays <= warningThreshold) {
      statusClass = "status-soon";
      if (diffDays === 0) {
          dateMsg = "Expires TODAY!";
      } else {
          dateMsg = `Expires in ${diffDays} days (${license.expiryDate})`;
      }
      currentNotificationCount++;
      addNotifyItem(license.name, dateMsg, statusClass);
    }
  });


  const seenCount = parseInt(localStorage.getItem("seen_alert_count")) || 0;

  if (currentNotificationCount > seenCount) {
    badgeElement.style.display = "block";
    badgeElement.innerText = currentNotificationCount;
  } else {
    badgeElement.style.display = "none";
  }

  if (currentNotificationCount === 0) {
    listElement.innerHTML = '<li style="padding:15px; text-align:center; color:#999;">No new alerts</li>';
  }
}

function addNotifyItem(title, dateMsg, cssClass) {
  const list = document.getElementById("notification-list");
  const li = document.createElement("li");
  li.className = cssClass;
  li.innerHTML = `
        <span class="notify-title">${title}</span>
        <span class="notify-date">${dateMsg}</span>
    `;
  list.appendChild(li);
}

function toggleNotifications() {
  const drop = document.getElementById("notification-dropdown");
  const badge = document.getElementById("notification-count");

  drop.classList.toggle("hidden");

  if (!drop.classList.contains("hidden")) {
    badge.style.display = "none";
    localStorage.setItem("seen_alert_count", currentNotificationCount);
  }
}

document.addEventListener("DOMContentLoaded", function () {
  loadNotifications();
});