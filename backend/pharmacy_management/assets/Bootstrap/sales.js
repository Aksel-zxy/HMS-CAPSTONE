class SaleManager {
  constructor() {
    this.editingRow = null;
    this.modal = document.getElementById("salesModal");
    this.form = document.getElementById("salesForm");

    this.form.addEventListener("submit", (e) => this.saveMedicine(e));

    document.getElementById("medicineTable").addEventListener("click", (e) => {
      if (e.target.classList.contains("edit-btn")) {
        this.editRow(e.target);
      } else if (e.target.classList.contains("delete-btn")) {
        this.deleteRow(e.target);
      }
    });
  }

  openModal(isEdit = false) {
    this.modal.classList.remove("hidden");
    document.getElementById("modalTitle").innerText = isEdit ? "Edit" : "Add";

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

    const data = {
      salesId: document.getElementById("salesId").value,
      staffName: document.getElementById("staffName").value,
      customerName: document.getElementById("customerName").value,
      quantity: parseFloat(document.getElementById("quantitySold").value),
      pricePerUnit: parseFloat(document.getElementById("pricePerUnit").value),
      saleDate: document.getElementById("saleDate").value,
      totalPrice: parseFloat(document.getElementById("totalPrice").value),
      paymentMethod: document.getElementById("paymentMethod").value
    };

    const table = document.getElementById("medicineTable").getElementsByTagName('tbody')[0];

    if (this.editingRow) {
      this.editingRow.cells[1].innerText = data.salesId; 
      this.editingRow.cells[2].innerText = data.staffName;
      this.editingRow.cells[3].innerText = data.customerName;
      this.editingRow.cells[4].innerText = `₱ ${data.quantity.toFixed(2)}`;
      this.editingRow.cells[5].innerText = `₱ ${data.pricePerUnit.toFixed(2)}`;
      this.editingRow.cells[6].innerText = data.saleDate;
      this.editingRow.cells[7].innerText = `₱ ${data.totalPrice.toFixed(2)}`;
      this.editingRow.cells[8].innerText = data.paymentMethod;
    } else {
      const newRow = table.insertRow();
      newRow.innerHTML = `
        <td>${table.rows.length}</td>
        <td>${data.salesId}</td>
        <td>${data.staffName}</td>
        <td>${data.customerName}</td>
        <td>₱ ${data.quantity.toFixed(2)}</td>
        <td>₱ ${data.pricePerUnit.toFixed(2)}</td>
        <td>${data.saleDate}</td>
        <td>₱ ${data.totalPrice.toFixed(2)}</td>
        <td>${data.paymentMethod}</td>
        <td>
    <button class="edit-btn" onclick="editRow(this)">Edit</button>
    <button class="delete-btn" onclick="deleteRow(this)">Delete</button>
    </td>
      `;
    }

    this.renumberRows();
    this.closeModal();
    this.editingRow = null;

  }

  editRow(button) {
    const row = button.closest('tr');
    this.editingRow = row;

    const cells = row.cells;
    document.getElementById("salesId").value = row.cells[1].innerText;
    document.getElementById("staffName").value = row.cells[2].innerText;
    document.getElementById("customerName").value = row.cells[3].innerText;
    document.getElementById("quantitySold").value = row.cells[4].innerText.replace("₱", "").trim();
    document.getElementById("pricePerUnit").value = row.cells[5].innerText.replace("₱", "").trim();
    document.getElementById("saleDate").value = row.cells[6].innerText;
    document.getElementById("totalPrice").value = row.cells[7].innerText.replace("₱", "").trim();
    document.getElementById("paymentMethod").value = row.cells[8].innerText;

    this.openModal(true);
  }

  deleteRow(button) {
    const row = button.closest('tr');
    if (confirm("Are you sure you want to delete this medicine?")) {
      row.remove();
      this.renumberRows();
    }
  }

  renumberRows() {
    const rows = document.getElementById("medicineTable").getElementsByTagName('tbody')[0].rows;
    for (let i = 0; i < rows.length; i++) {
      rows[i].cells[0].innerText = i + 1;
    }
  }
}


const salesManager = new SaleManager();


function openModal(isEdit = false) {
  salesManager.openModal(isEdit);
}

function closeModal() {
  salesManager.closeModal();
}

