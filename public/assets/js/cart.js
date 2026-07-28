document.querySelectorAll('.cart-line-remove').forEach(btn => {
  btn.addEventListener('click', async () => {
    const line = btn.closest('.cart-line');
    const type = line.dataset.type;
    const id = parseInt(line.dataset.id, 10);

    try {
      const response = await fetch('/cart/remove', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type, id }),
      });
      if (!response.ok) throw new Error('Failed to remove');
      location.reload();
    } catch (err) {
      console.error(err);
    }
  });
});
