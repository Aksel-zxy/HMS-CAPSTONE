// ACTIVE LINK HANDLING (excluding dropdown toggles)
const allSideMenuLinks = document.querySelectorAll('#sidebar .side-menu.top li a:not(.dropdown-toggle)');

allSideMenuLinks.forEach(item => {
	const li = item.parentElement;

	item.addEventListener('click', function () {
		allSideMenuLinks.forEach(i => {
			i.parentElement.classList.remove('active');
		});
		li.classList.add('active');
	});
});

// TOGGLE SIDEBAR
const menuBar = document.querySelector('#content nav .bi.bi-list'); // make sure this matches your HTML icon
const sidebar = document.getElementById('sidebar');

menuBar.addEventListener('click', function () {
	sidebar.classList.toggle('hide');
});

// SEARCH BAR TOGGLE
const searchButton = document.querySelector('#content nav form .form-input button');
const searchButtonIcon = searchButton.querySelector('.bi');
const searchForm = document.querySelector('#content nav form');

searchButton.addEventListener('click', function (e) {
	if (window.innerWidth < 576) {
		e.preventDefault();
		searchForm.classList.toggle('show');
		if (searchForm.classList.contains('show')) {
			searchButtonIcon.classList.replace('bi-search', 'bi-x');
		} else {
			searchButtonIcon.classList.replace('bi-x', 'bi-search');
		}
	}
});

// WINDOW RESIZE BEHAVIOUR
if (window.innerWidth < 768) {
	sidebar.classList.add('hide');
} else if (window.innerWidth > 576) {
	searchButtonIcon.classList.replace('bi-x', 'bi-search');
	searchForm.classList.remove('show');
}

window.addEventListener('resize', function () {
	if (this.innerWidth > 576) {
		searchButtonIcon.classList.replace('bi-x', 'bi-search');
		searchForm.classList.remove('show');
	}
});

// DARK MODE TOGGLE
const switchMode = document.getElementById('switch-mode');
switchMode.addEventListener('change', function () {
	if (this.checked) {
		document.body.classList.add('dark');
	} else {
		document.body.classList.remove('dark');
	}
});

// SIDEBAR DROPDOWN TOGGLES
document.querySelectorAll('#sidebar .side-menu li > a.dropdown-toggle').forEach(toggle => {
	toggle.addEventListener('click', (e) => {
		e.preventDefault();
		const li = toggle.parentElement;

		// Optional: close other open dropdowns
		document.querySelectorAll('#sidebar .side-menu li.open').forEach(other => {
			if (other !== li) other.classList.remove('open');
		});

		// Toggle this one
		li.classList.toggle('open');
	});
});

document.querySelector('.toggle-btn').addEventListener('click', () => {
    document.querySelector('.sidebar').classList.toggle('collapsed');
});

document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.getElementById("sidebar");
    const content = document.getElementById("content");
    const toggleBtn = document.querySelector("nav .bi-list");

    toggleBtn.addEventListener("click", () => {
        sidebar.classList.toggle("collapsed");
        content.classList.toggle("expanded");
    });
});

// Auto-fill today's date 
document.addEventListener("DOMContentLoaded", function() {
    let today = new Date().toISOString().split('T')[0];
    document.getElementById('signed_date').value = today;
});

$(".nextBtn").click(function(){
    if(currentStep === 2) {
        let files = document.querySelector('input[name="documents[]"]').files;
        if (files.length === 0) {
            alert("Please upload at least one PDF file.");
            return;
        }
        for (let i = 0; i < files.length; i++) {
            if (files[i].type !== "application/pdf") {
                alert("Only PDF files are allowed. Please remove invalid files.");
                return;
            }
        }
    }

    if ($("#step"+currentStep+" :input[required]").filter(function(){return !this.value;}).length===0) {
        $("#step"+currentStep).addClass("d-none");
        currentStep++; 
        $("#step"+currentStep).removeClass("d-none"); 
        updateProgress();
    } else { 
        alert("Please fill in all required fields."); 
    }
});
