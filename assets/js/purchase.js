/* ==============================
   ADD ITEM FROM MODAL
============================== */
function addItemFromModal(code, name, rate) {

    const tbody = document.querySelector('#itemTable tbody');
    if (!tbody) return;

    const sr = tbody.children.length + 1;

    const tr = document.createElement('tr');

    tr.innerHTML = `
        <td class="fw-bold">${sr}</td>

        <td>
            <input name="item_code[]" 
            class="form-control" 
            value="${code}" readonly>
        </td>

        <td>
            <input name="item_name[]" 
            class="form-control" 
            value="${name}" readonly>
        </td>

        <td>
            <input name="qty[]" 
            type="number"
            min="1"
            class="form-control text-end"
            oninput="calcRow(this)" required>
        </td>

        <td>
            <input name="rate[]" 
            type="number"
            step="0.01"
            class="form-control text-end"
            value="${rate}"
            oninput="calcRow(this)" required>
        </td>

        <td>
            <input name="item_total[]" 
            class="form-control text-end"
            readonly>
        </td>

        <td>
            <button type="button"
            class="btn btn-sm btn-danger"
            onclick="removeRow(this)">X</button>
        </td>
    `;

    tbody.appendChild(tr);

    // Close Modal Safely
    let modalEl = document.getElementById('itemModal');
    let modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) {
        modal.hide();
    }

    // Remove leftover backdrop (extra safety)
    document.querySelectorAll('.modal-backdrop')
        .forEach(el => el.remove());
    document.body.classList.remove('modal-open');

    calcGrand();
}


/* ==============================
   CALCULATE ROW TOTAL
============================== */
function calcRow(el) {

    const tr = el.closest('tr');

    const qty = parseFloat(
        tr.querySelector('[name="qty[]"]').value
    ) || 0;

    const rate = parseFloat(
        tr.querySelector('[name="rate[]"]').value
    ) || 0;

    const total = qty * rate;

    tr.querySelector('[name="item_total[]"]')
        .value = total.toFixed(2);

    calcGrand();
}


/* ==============================
   REMOVE ROW
============================== */
function removeRow(btn) {

    btn.closest('tr').remove();

    // Re-number rows
    document.querySelectorAll('#itemTable tbody tr')
        .forEach((tr, i) => {
            tr.children[0].innerText = i + 1;
        });

    calcGrand();
}


/* ==============================
   CALCULATE GRAND TOTAL
============================== */
function calcGrand() {

    let total = 0;

    document.querySelectorAll('[name="item_total[]"]')
        .forEach(el => {
            total += parseFloat(el.value) || 0;
        });

    document.getElementById('grandTotal').value =
        total.toFixed(2);

    document.getElementById('billTotalText').innerText =
        total.toFixed(2);

    document.getElementById('itemCount').innerText =
        document.querySelectorAll(
            '#itemTable tbody tr'
        ).length;
}


/* ==============================
   CLEAR BILL
============================== */
function clearBill() {

    const tbody = document.querySelector('#itemTable tbody');
    if (tbody) tbody.innerHTML = '';

    document.getElementById('grandTotal').value = '0';
    document.getElementById('billTotalText').innerText = '0.00';
    document.getElementById('itemCount').innerText = '0';
}
