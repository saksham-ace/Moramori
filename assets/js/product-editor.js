/* Product editor: photo upload + drag-to-reorder, size chips, AJAX save with progress. */
(() => {
  const form = document.getElementById('productForm');
  if (!form) return;
  const grid = document.getElementById('photoGrid');
  const addTile = document.getElementById('photoAdd');
  const input = document.getElementById('photoInput');
  const countEl = document.getElementById('photoCount');
  const newFiles = new Map();   // token -> File
  const removed = new Set();    // existing image ids
  let seq = 0;

  /* ---------- photos ---------- */
  const tiles = () => [...grid.querySelectorAll('.ph')];
  const refresh = () => {
    tiles().forEach((t, i) => { t.querySelector('.ph-tag').textContent = i === 0 ? 'Cover' : i === 1 ? 'Hover' : ''; });
    const n = tiles().length;
    countEl.textContent = n ? n + (n === 1 ? ' photo' : ' photos') + ' · drag to reorder' : '';
  };

  const MAX_SIDE = 2000;
  // Resize large photos in the browser so uploads are fast and stay under the 5 MB limit.
  const shrink = file => new Promise(resolve => {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size < 1.2 * 1024 * 1024) return resolve(file);
    const img = new Image();
    img.onload = () => {
      const scale = Math.min(1, MAX_SIDE / Math.max(img.width, img.height));
      const c = document.createElement('canvas');
      c.width = Math.round(img.width * scale); c.height = Math.round(img.height * scale);
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      c.toBlob(b => resolve(b && b.size < file.size ? new File([b], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file), 'image/jpeg', 0.86);
      URL.revokeObjectURL(img.src);
    };
    img.onerror = () => resolve(file);
    img.src = URL.createObjectURL(file);
  });

  const addFiles = async files => {
    for (const f of files) {
      if (!f.type.startsWith('image/')) continue;
      const token = 'n' + (seq++);
      const tile = document.createElement('div');
      tile.className = 'ph ph-new is-loading';
      tile.dataset.token = token;
      tile.innerHTML = '<img alt="" draggable="false"><span class="ph-tag"></span><button type="button" class="ph-del" aria-label="Remove photo">&times;</button>';
      grid.insertBefore(tile, addTile);
      refresh();
      const ready = await shrink(f);
      newFiles.set(token, ready);
      tile.querySelector('img').src = URL.createObjectURL(ready);
      tile.classList.remove('is-loading');
    }
    refresh();
  };

  input.addEventListener('change', () => { addFiles([...input.files]); input.value = ''; });

  // drop files anywhere on the photo card
  const card = grid.closest('.card');
  ['dragenter', 'dragover'].forEach(ev => card.addEventListener(ev, e => {
    if (![...(e.dataTransfer?.types || [])].includes('Files')) return;
    e.preventDefault(); card.classList.add('drop-on');
  }));
  ['dragleave', 'drop'].forEach(ev => card.addEventListener(ev, e => {
    if (ev === 'dragleave' && card.contains(e.relatedTarget)) return;
    card.classList.remove('drop-on');
  }));
  card.addEventListener('drop', e => { if (e.dataTransfer?.files.length) { e.preventDefault(); addFiles([...e.dataTransfer.files]); } });

  grid.addEventListener('click', e => {
    const del = e.target.closest('.ph-del');
    if (!del) return;
    const tile = del.closest('.ph');
    const tok = tile.dataset.token;
    if (tok.startsWith('e:')) removed.add(tok.slice(2)); else newFiles.delete(tok);
    tile.classList.add('ph-out');
    setTimeout(() => { flip(() => tile.remove()); refresh(); }, 220);
  });

  /* ---------- drag to reorder (mouse + touch) ---------- */
  const flip = change => {
    const before = new Map(tiles().map(t => [t, t.getBoundingClientRect()]));
    change();
    tiles().forEach(t => {
      const a = before.get(t); if (!a) return;
      const b = t.getBoundingClientRect();
      const dx = a.left - b.left, dy = a.top - b.top;
      if (!dx && !dy) return;
      t.animate([{ transform: `translate(${dx}px, ${dy}px)` }, { transform: 'none' }], { duration: 260, easing: 'cubic-bezier(.22,.61,.36,1)' });
    });
  };

  let drag = null;
  grid.addEventListener('pointerdown', e => {
    const tile = e.target.closest('.ph');
    if (!tile || e.target.closest('.ph-del') || e.button > 0) return;
    e.preventDefault();
    const r = tile.getBoundingClientRect();
    const ghost = tile.cloneNode(true);
    ghost.className = 'ph ph-ghost';
    Object.assign(ghost.style, { width: r.width + 'px', height: r.height + 'px', left: r.left + 'px', top: r.top + 'px' });
    document.body.appendChild(ghost);
    tile.classList.add('ph-holding');
    drag = { tile, ghost, offX: e.clientX - r.left, offY: e.clientY - r.top, x0: r.left, y0: r.top, lastSwap: 0 };
    document.addEventListener('pointermove', onMove);
    document.addEventListener('pointerup', onUp, { once: true });
    document.addEventListener('pointercancel', onUp, { once: true });
  });

  function onMove(e) {
    if (!drag) return;
    const { tile, ghost, offX, offY, x0, y0 } = drag;
    ghost.style.transform = `translate(${e.clientX - offX - x0}px, ${e.clientY - offY - y0}px) scale(1.04) rotate(-1.5deg)`;
    if (performance.now() - drag.lastSwap < 120) return;
    const over = tiles().find(t => {
      if (t === tile) return false;
      const b = t.getBoundingClientRect();
      return e.clientX > b.left && e.clientX < b.right && e.clientY > b.top && e.clientY < b.bottom;
    });
    if (!over) return;
    const b = over.getBoundingClientRect();
    const after = e.clientX > b.left + b.width / 2;
    const ref = after ? over.nextElementSibling : over;
    if (ref === tile || ref === tile.nextElementSibling) return;
    drag.lastSwap = performance.now();
    flip(() => grid.insertBefore(tile, ref));
    refresh();
  }

  function onUp() {
    if (!drag) return;
    document.removeEventListener('pointermove', onMove);
    const { tile, ghost } = drag;
    const r = tile.getBoundingClientRect();
    ghost.style.transition = 'transform .22s cubic-bezier(.22,.61,.36,1)';
    ghost.style.transform = `translate(${r.left - drag.x0}px, ${r.top - drag.y0}px)`;
    setTimeout(() => { ghost.remove(); tile.classList.remove('ph-holding'); }, 220);
    drag = null;
    refresh();
  }
  refresh();

  /* ---------- sizes ---------- */
  const sizesValue = document.getElementById('sizesValue');
  const chipsBox = document.getElementById('sizeChips');
  const entry = document.getElementById('sizeEntry');
  let sizes = sizesValue.value ? sizesValue.value.split(',').filter(Boolean) : [];
  const renderSizes = () => {
    chipsBox.querySelectorAll('.size-chip').forEach(c => c.remove());
    sizes.forEach((s, i) => {
      const c = document.createElement('span');
      c.className = 'size-chip';
      c.innerHTML = `<span></span><button type="button" aria-label="Remove">&times;</button>`;
      c.firstChild.textContent = s;
      c.lastChild.onclick = () => { sizes.splice(i, 1); renderSizes(); };
      chipsBox.insertBefore(c, entry);
    });
    sizesValue.value = sizes.join(',');
  };
  const addSize = v => v.split(',').map(s => s.trim()).filter(Boolean).forEach(s => { if (!sizes.includes(s)) sizes.push(s); });
  entry.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addSize(entry.value); entry.value = ''; renderSizes(); }
    if (e.key === 'Backspace' && !entry.value && sizes.length) { sizes.pop(); renderSizes(); }
  });
  entry.addEventListener('blur', () => { if (entry.value.trim()) { addSize(entry.value); entry.value = ''; renderSizes(); } });
  chipsBox.addEventListener('click', e => { if (e.target === chipsBox) entry.focus(); });
  document.querySelectorAll('[data-sizes]').forEach(b => b.addEventListener('click', () => { addSize(b.dataset.sizes); renderSizes(); }));
  document.querySelector('[data-clear]')?.addEventListener('click', () => { sizes = []; renderSizes(); });
  renderSizes();

  /* ---------- new category ---------- */
  const cat = document.getElementById('category_id');
  const newCat = document.getElementById('newCategory');
  cat.addEventListener('change', () => {
    const on = cat.value === '__new';
    newCat.style.display = on ? '' : 'none';
    if (on) newCat.focus(); else newCat.value = '';
  });

  /* ---------- save ---------- */
  const errBox = document.getElementById('formErrors');
  const bar = document.getElementById('uploadBar');
  let submitter = null;
  form.querySelectorAll('[type=submit]').forEach(b => b.addEventListener('click', () => { submitter = b; }));

  form.addEventListener('submit', e => {
    e.preventDefault();
    if (entry.value.trim()) { addSize(entry.value); entry.value = ''; renderSizes(); }
    const problems = [];
    if (!form.elements.name.value.trim()) problems.push('Please enter a product name.');
    if (!(parseFloat(form.elements.price.value) > 0)) problems.push('Please enter a price greater than 0.');
    if (cat.value === '__new' && !newCat.value.trim()) problems.push('Please type the new category name.');
    if (problems.length) return showErrors(problems);

    const fd = new FormData(form);
    fd.delete('images[]');
    if (cat.value === '__new') fd.set('category_id', '');
    fd.set('after', submitter?.value || 'stay');
    let n = 0;
    tiles().forEach(t => {
      const tok = t.dataset.token;
      if (tok.startsWith('e:')) fd.append('image_order[]', tok);
      else if (newFiles.has(tok)) { fd.append('images[]', newFiles.get(tok)); fd.append('image_order[]', 'n:' + (n++)); }
    });
    removed.forEach(id => fd.append('removed_images[]', id));

    const buttons = form.querySelectorAll('[type=submit]');
    buttons.forEach(b => b.disabled = true);
    const label = form.querySelector('.btn-label');
    const xhr = new XMLHttpRequest();
    xhr.open('POST', location.href);
    xhr.setRequestHeader('X-Requested-With', 'fetch');
    xhr.upload.onprogress = ev => {
      if (!ev.lengthComputable) return;
      const pct = Math.round(ev.loaded / ev.total * 100);
      bar.classList.add('on'); bar.firstElementChild.style.width = pct + '%';
      if (label) label.textContent = pct < 100 ? `Uploading… ${pct}%` : 'Saving…';
    };
    xhr.onload = () => {
      let res = {};
      try { res = JSON.parse(xhr.responseText); } catch { res = { errors: ['Save failed (server said: ' + xhr.status + '). Please try again.'] }; }
      if (res.ok) { location.href = res.redirect; return; }
      reset(); showErrors(res.errors || ['Could not save.']);
    };
    xhr.onerror = () => { reset(); showErrors(['Network error — please try again.']); };
    xhr.send(fd);
    function reset() {
      buttons.forEach(b => b.disabled = false);
      if (label) label.textContent = 'Save product';
      bar.classList.remove('on'); bar.firstElementChild.style.width = '0';
    }
  });

  function showErrors(list) {
    errBox.textContent = list.join(' ');
    errBox.style.display = '';
    errBox.classList.remove('gone');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
})();
