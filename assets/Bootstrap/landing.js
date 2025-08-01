window.addEventListener("DOMContentLoaded", () => {
  document.body.classList.add("fade-in");
});
document.addEventListener("DOMContentLoaded", function () {
  const preloader = document.querySelector(".preloader");
  if (preloader) {
    setTimeout(() => {
      preloader.style.display = "none"; // Hide the preloader after 2 seconds
    }, 1000); //LOADER TIME adjust na lanng
  }
});
