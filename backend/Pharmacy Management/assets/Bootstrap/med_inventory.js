class Medicine {
  constructor(name, brand, description, category, stock, price, expiry, dateAdded = new Date().toLocaleDateString()) {
    this.name = name;
    this.brand = brand;
    this.description = description;
    this.category = category;
    this.stock = stock;
    this.price = parseFloat(price).toFixed(2);
    this.expiry = expiry;
    this.dateAdded = dateAdded;
  }

  toRowHTML(index) {
    return `
      <td>${index}</td>
      <td>${this.name}</td>
      <td>${this.brand}</td>
      <td>${this.description}</td>
      <td>${this.category}</td>
      <td>${this.stock}</td>
      <td>₱ ${this.price}</td>
      <td>${this.expiry}</td>
      <td>${this.dateAdded}</td>
      <td>
        <button class="edit-btn">Edit</button>
        <button class="delete-btn">Delete</button>
      </td>
    `;
  }
}

class MedicineManager {
  constructor() {
    this.editingRow = null;
    this.modal = document.getElementById("medicineModal");
    this.form = document.getElementById("medicineForm");

    this.form.addEventListener("submit", (e) => this.saveMedicine(e));

    document.getElementById("medicineTable").addEventListener("click", (e) => {
      if (e.target.classList.contains("edit-btn")) {
        this.editRow(e.target);
      } else if (e.target.classList.contains("delete-btn")) {
        this.deleteRow(e.target);
      }
    });
  }

  renumberRows() {
  const rows = document.querySelector("#medicineTable tbody").rows;
  for (let i = 0; i < rows.length; i++) {
    rows[i].cells[0].innerText = i + 1;
  }
}


  openModal(isEdit = false) {
    this.modal.classList.remove("hidden");
    document.getElementById("modalTitle").innerText = isEdit ? "Edit Medicine" : "Add Medicine";

    if (!isEdit) {
      this.form.reset();
      this.editingRow = null;
    }
  }

  closeModal() {
    this.modal.classList.add("hidden");
  }

  saveMedicine(e) {
    e.preventDefault();

    const name = document.getElementById("medName").value;
    const brand = document.getElementById("medBrand").value;
    const description = document.getElementById("medDescription").value;
    const category = document.getElementById("medCategory").value;
    const stock = document.getElementById("medStock").value;
    const price = document.getElementById("medPrice").value;
    const expiry = document.getElementById("medExpiry").value;
    const dateAdded = new Date().toISOString().split("T")[0]; // "YYYY-MM-DD"


    const medicine = new Medicine(name, brand, description, category, stock, price, expiry, dateAdded);

    const table = document.getElementById("medicineTable").getElementsByTagName("tbody")[0];

    if (this.editingRow) {
      const cells = this.editingRow.cells;
      cells[1].innerText = medicine.name;
      cells[2].innerText = medicine.brand;
      cells[3].innerText = medicine.description;
      cells[4].innerText = medicine.category;
      cells[5].innerText = medicine.stock;
      cells[6].innerHTML = `₱ ${medicine.price}`;
      cells[7].innerText = medicine.expiry;
      cells[8].innerText = medicine.dateAdded;

      const tbody = document.querySelector("#medicineTable tbody");
      tbody.insertBefore(this.editingRow, tbody.firstChild);
    } else {
      const newRow = table.insertRow();
      newRow.innerHTML = medicine.toRowHTML(table.rows.length + 1);
    }

    this.renumberRows();
    this.closeModal();
    this.editingRow = null;
  }

  editRow(button) {
    const row = button.closest("tr");
    this.editingRow = row;

    const cells = row.cells;
    document.getElementById("medName").value = cells[1].innerText;
    document.getElementById("medBrand").value = cells[2].innerText;
    document.getElementById("medDescription").value = cells[3].innerText;
    document.getElementById("medCategory").value = cells[4].innerText;
    document.getElementById("medStock").value = cells[5].innerText;
    document.getElementById("medPrice").value = cells[6].innerText.replace("₱", "").trim();
    document.getElementById("medExpiry").value = cells[7].innerText;
    document.getElementById("medDateAdded").value = new Date().toISOString().split("T")[0];


    this.openModal(true);
  }

  deleteRow(button) {
    const row = button.closest("tr");
    if (confirm("Are you sure you want to delete this medicine?")) {
      row.remove();
      this.renumberRows();
    }
  }

  renumberRows() {
    const rows = document.querySelector("#medicineTable tbody").rows;
    for (let i = 0; i < rows.length; i++) {
      rows[i].cells[0].innerText = i + 1;
    }
  }
}

// Initialize
const medicineManager = new MedicineManager();

// Global functions to trigger modal open/close
function openModal(isEdit = false) {
  medicineManager.openModal(isEdit);
}

function closeModal() {
  medicineManager.closeModal();
}





