// Perkiraan saja; harga dan jumlah selalu dihitung ulang oleh server.
const poForm = document.getElementById('po-form');
if (poForm) {
  const total = document.getElementById('po-total');
  const update = () => {
    let amount = 0;
    poForm.querySelectorAll('[data-price]').forEach(input => {
      const qty = Number(input.value);
      if (!input.disabled && Number.isInteger(qty) && qty >= 0 && qty <= Number(input.max)) amount += qty * Number(input.dataset.price);
    });
    total.textContent = new Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR', maximumFractionDigits:0}).format(amount);
  };
  poForm.addEventListener('input', update);
  update();
}
