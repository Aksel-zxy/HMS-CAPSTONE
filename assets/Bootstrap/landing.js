// Add fade-in and menu toggle logic on DOMContentLoaded
window.addEventListener("DOMContentLoaded", () => {
  // Add fade-in class to body
  document.body.classList.add("fade-in");

  // Menu toggle for mobile nav
  const menuToggle = document.getElementById('mobile-menu');
  const navMenu = document.querySelector('.nav-menu');

  if (menuToggle && navMenu) {
    menuToggle.addEventListener('click', () => {
      navMenu.classList.toggle('active');
      menuToggle.classList.toggle('active');
    });
  }
});

// Fade out and hide preloader when everything (images, scripts, etc.) has loaded
window.addEventListener("load", () => {
  const preloader = document.querySelector(".preloader");
  if (preloader) {
    preloader.style.opacity = "0";  // start fade out

    setTimeout(() => {
      preloader.style.display = "none"; // hide after fade out
    }, 500); // match your CSS transition duration
  }
});
