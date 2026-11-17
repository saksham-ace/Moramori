  </div>
</div>
<script>
document.querySelectorAll('[data-confirm]').forEach(el => el.addEventListener('click', e => { if (!confirm(el.dataset.confirm)) e.preventDefault(); }));
document.querySelectorAll('.flash').forEach(f => setTimeout(() => f.classList.add('gone'), 4500));
document.querySelectorAll('input[type=file][data-preview]').forEach(inp => inp.addEventListener('change', () => {
  const box = document.querySelector(inp.dataset.preview); if (!box) return; box.innerHTML = '';
  [...inp.files].forEach(f => { const r = new FileReader(); r.onload = () => { const i = new Image(); i.src = r.result; box.appendChild(i); }; r.readAsDataURL(f); });
}));
</script>
</body>
</html>
