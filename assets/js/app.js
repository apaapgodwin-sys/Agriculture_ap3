/**
 * AgriTrack Application Javascript
 */
document.addEventListener('DOMContentLoaded', () => {
    // Mobile sidebar toggle
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebar = document.querySelector('.app-sidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });
    }

    // Auto-calculate Total Harvest Value
    const qtyInput = document.getElementById('harvest_quantity_kg');
    const priceInput = document.getElementById('harvest_selling_price');
    const totalDisplay = document.getElementById('harvest_total_value_display');

    function calculateHarvestTotal() {
        if (!qtyInput || !priceInput || !totalDisplay) return;
        const qty = parseFloat(qtyInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        const total = qty * price;
        totalDisplay.textContent = '₱' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    if (qtyInput && priceInput) {
        qtyInput.addEventListener('input', calculateHarvestTotal);
        priceInput.addEventListener('input', calculateHarvestTotal);
        calculateHarvestTotal();
    }

    // Auto-calculate Expected Harvest Date in Planting Form
    const cropSelect = document.getElementById('planting_crop_id');
    const plantDateInput = document.getElementById('planting_date');
    const expectedDateInput = document.getElementById('planting_expected_date');

    function calculateExpectedHarvestDate() {
        if (!cropSelect || !plantDateInput || !expectedDateInput) return;
        const selectedOpt = cropSelect.options[cropSelect.selectedIndex];
        if (!selectedOpt) return;
        const growthDays = parseInt(selectedOpt.getAttribute('data-days') || '0', 10);
        const plantDateVal = plantDateInput.value;

        if (growthDays > 0 && plantDateVal) {
            const pDate = new Date(plantDateVal);
            if (!isNaN(pDate.getTime())) {
                pDate.setDate(pDate.getDate() + growthDays);
                const yyyy = pDate.getFullYear();
                const mm = String(pDate.getMonth() + 1).padStart(2, '0');
                const dd = String(pDate.getDate()).padStart(2, '0');
                expectedDateInput.value = `${yyyy}-${mm}-${dd}`;
            }
        }
    }

    if (cropSelect && plantDateInput) {
        cropSelect.addEventListener('change', calculateExpectedHarvestDate);
        plantDateInput.addEventListener('change', calculateExpectedHarvestDate);
    }
});
