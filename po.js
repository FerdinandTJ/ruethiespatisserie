// Perkiraan saja; harga dan jumlah selalu dihitung ulang oleh server.
const poForm = document.getElementById('po-form');
if (poForm) {
  const total = document.getElementById('po-total');
  const update = () => {
    let amount = 0;
    poForm.querySelectorAll('[data-price]').forEach(input => {
      const qty = Number(input.value);
      const min = Number(input.dataset.minQty || 1);
      input.setCustomValidity(!input.disabled && qty > 0 && qty < min ? `Minimal pembelian produk ini ${min} buah, atau isi 0 jika tidak dipilih.` : '');
      if (!input.disabled && Number.isInteger(qty) && (qty === 0 || qty >= min) && qty <= Number(input.max)) amount += qty * Number(input.dataset.price);
    });
    total.textContent = new Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR', maximumFractionDigits:0}).format(amount);
  };
  poForm.addEventListener('input', update);
  update();
}

const statusLink = document.getElementById('po-status-link');
const copyLink = document.getElementById('po-copy-link');
const copyFeedback = document.getElementById('po-copy-feedback');
if (statusLink && copyLink && copyFeedback) {
  copyLink.hidden = false;
  copyLink.addEventListener('click', async () => {
    let copied = false;
    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(statusLink.value);
        copied = true;
      }
    } catch (_) { /* Fallback untuk browser yang menolak izin clipboard. */ }
    if (!copied) {
      statusLink.focus();
      statusLink.select();
      statusLink.setSelectionRange(0, statusLink.value.length);
      try { copied = document.execCommand('copy'); } catch (_) { /* Tetap dapat disalin manual. */ }
    }
    copyFeedback.textContent = copied ? 'Tautan pesanan berhasil disalin.' : 'Tautan sudah dipilih. Tekan Salin atau Ctrl+C / Cmd+C untuk menyalinnya.';
  });
}
