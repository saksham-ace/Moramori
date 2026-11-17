/* MORA MORI — storefront interactions */
(() => {
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];
  const BASE = $('meta[name=base]')?.content || '';
  const CSRF = $('meta[name=csrf]')?.content || '';
  const body = document.body;

  /* ---------- page fade in / out ---------- */
  requestAnimationFrame(() => body.classList.add('is-loaded'));
  setTimeout(() => body.classList.add('is-loaded'), 80);
  window.addEventListener('pageshow', e => { if (e.persisted) body.classList.remove('is-leaving'); });
  document.addEventListener('click', e => {
    const a = e.target.closest('a');
    if (!a || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || a.target === '_blank') return;
    const href = a.getAttribute('href') || '';
    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || a.hasAttribute('download')) return;
    if (a.origin !== location.origin) return;
    if (a.pathname === location.pathname && a.search === location.search && a.hash) return;
    e.preventDefault();
    body.classList.add('is-leaving');
    setTimeout(() => { location.href = a.href; }, 260);
  });

  /* ---------- header behaviour ---------- */
  const header = $('#siteHeader');
  let lastY = scrollY;
  const onScroll = () => {
    const y = scrollY;
    header?.classList.toggle('scrolled', y > 10);
    if (header && !body.classList.contains('no-hide')) {
      header.classList.toggle('hidden', y > lastY && y > 400 && !body.classList.contains('locked'));
    }
    lastY = y;
  };
  addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------- reveal on scroll ---------- */
  const io = new IntersectionObserver(entries => {
    entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
  }, { rootMargin: '0px 0px -8% 0px' });
  $$('.reveal').forEach(el => io.observe(el));

  /* ---------- parallax ---------- */
  const para = $$('[data-parallax]');
  if (para.length && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const tick = () => {
      para.forEach(el => {
        const r = el.parentElement.getBoundingClientRect();
        if (r.bottom < 0 || r.top > innerHeight) return;
        const p = (r.top + r.height / 2 - innerHeight / 2) / innerHeight;
        el.style.transform = `translate3d(0, ${p * -60}px, 0)`;
      });
      requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }

  /* ---------- drawers / overlays ---------- */
  const scrim = $('#scrim');
  const openPanel = el => {
    closeAll();
    el.classList.add('open'); el.setAttribute('aria-hidden', 'false');
    scrim.classList.add('on'); body.classList.add('locked');
    body.style.overflow = 'hidden';
  };
  const closeAll = () => {
    $$('.drawer.open, .search-overlay.open').forEach(d => { d.classList.remove('open'); d.setAttribute('aria-hidden', 'true'); });
    scrim?.classList.remove('on'); body.classList.remove('locked');
    body.style.overflow = '';
  };
  scrim?.addEventListener('click', closeAll);
  $$('[data-close]').forEach(b => b.addEventListener('click', closeAll));
  addEventListener('keydown', e => { if (e.key === 'Escape') { closeAll(); $('.lightbox')?.classList.remove('open'); } });
  $('#menuOpen')?.addEventListener('click', () => openPanel($('#menuDrawer')));
  $('#searchOpen')?.addEventListener('click', () => { openPanel($('#searchOverlay')); setTimeout(() => $('#searchOverlay input')?.focus(), 300); });
  $('#cartOpen')?.addEventListener('click', () => openCart());

  /* ---------- toasts ---------- */
  const toast = (msg, type = 'success') => {
    let box = $('.toasts');
    if (!box) { box = document.createElement('div'); box.className = 'toasts'; body.appendChild(box); }
    const t = document.createElement('div');
    t.className = 'toast toast-' + type; t.textContent = msg;
    box.appendChild(t);
    setTimeout(() => t.remove(), 5200);
  };
  window.mmToast = toast;

  /* ---------- cart ---------- */
  const api = async (data) => {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    fd.append('_csrf', CSRF);
    const r = await fetch(BASE + '/ajax/cart.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    return r.json();
  };
  const setCount = n => {
    $$('[data-cart-count]').forEach(el => {
      el.textContent = n > 0 ? n : '';
      el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump');
    });
  };
  const renderDrawer = html => {
    const b = $('#cartDrawerBody');
    if (b) b.innerHTML = html;
  };
  async function openCart() {
    openPanel($('#cartDrawer'));
    const res = await api({ action: 'view' });
    renderDrawer(res.html); setCount(res.count);
  }
  window.mmOpenCart = openCart;

  document.addEventListener('submit', async e => {
    const f = e.target.closest('form[data-add-to-cart]');
    if (!f) return;
    e.preventDefault();
    const btn = f.querySelector('[type=submit]');
    const sizeInputs = f.querySelectorAll('input[name=size]');
    const size = f.querySelector('input[name=size]:checked')?.value || '';
    if (sizeInputs.length && !size) { toast('Please select a size', 'error'); f.querySelector('.sizes')?.animate([{ transform: 'translateX(-6px)' }, { transform: 'translateX(6px)' }, { transform: 'none' }], { duration: 300 }); return; }
    btn?.classList.add('loading');
    try {
      const res = await api({ action: 'add', product_id: f.product_id.value, size, qty: f.qty?.value || 1 });
      if (!res.ok) { toast(res.message, 'error'); return; }
      setCount(res.count); renderDrawer(res.html);
      openPanel($('#cartDrawer'));
    } catch { toast('Something went wrong. Please try again.', 'error'); }
    finally { btn?.classList.remove('loading'); }
  });

  // quick add buttons on cards (products without sizes)
  document.addEventListener('click', async e => {
    const q = e.target.closest('[data-quick-add]');
    if (!q) return;
    e.preventDefault(); e.stopPropagation();
    const res = await api({ action: 'add', product_id: q.dataset.quickAdd, size: '', qty: 1 });
    if (!res.ok) { toast(res.message, 'error'); return; }
    setCount(res.count); renderDrawer(res.html); openPanel($('#cartDrawer'));
  });

  // drawer + cart page qty / remove
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-cart-qty], [data-cart-remove]');
    if (!b) return;
    e.preventDefault();
    const key = b.dataset.key;
    const qty = b.hasAttribute('data-cart-remove') ? 0 : parseInt(b.dataset.cartQty, 10);
    const res = await api({ action: 'update', key, qty });
    if (!res.ok && res.message) toast(res.message, 'error');
    setCount(res.count);
    if (b.closest('#cartDrawer')) renderDrawer(res.html);
    else location.reload();
  });

  /* ---------- PDP qty stepper ---------- */
  $$('[data-stepper]').forEach(st => {
    const input = st.querySelector('input');
    st.addEventListener('click', e => {
      const d = e.target.closest('[data-step]');
      if (!d) return;
      const max = parseInt(input.max || '99', 10);
      input.value = Math.min(max, Math.max(1, (parseInt(input.value, 10) || 1) + parseInt(d.dataset.step, 10)));
    });
  });

  /* ---------- accordion ---------- */
  $$('.acc-head').forEach(h => h.addEventListener('click', () => h.parentElement.classList.toggle('open')));

  /* ---------- lightbox ---------- */
  const gallery = $$('.pdp-gallery figure img');
  if (gallery.length) {
    const lb = document.createElement('div');
    lb.className = 'lightbox'; lb.innerHTML = '<img alt="">';
    body.appendChild(lb);
    lb.addEventListener('click', () => lb.classList.remove('open'));
    gallery.forEach(img => img.addEventListener('click', () => {
      if (innerWidth < 900) return;
      lb.querySelector('img').src = img.src; lb.classList.add('open');
    }));
  }

  /* ---------- dropzone preview ---------- */
  $$('.dropzone').forEach(dz => {
    const input = dz.querySelector('input[type=file]');
    const prev = dz.querySelector('.dz-preview');
    const title = dz.querySelector('.dz-title');
    ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, () => dz.classList.add('drag')));
    ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, () => dz.classList.remove('drag')));
    input?.addEventListener('change', () => {
      const f = input.files[0];
      if (!f) return;
      dz.classList.add('has-file');
      if (title) title.textContent = f.name;
      if (prev && f.type.startsWith('image/')) {
        const r = new FileReader();
        r.onload = () => { prev.src = r.result; };
        r.readAsDataURL(f);
      }
    });
  });

  /* ---------- checkout: delivery location ---------- */
  const co = $('#checkoutForm');
  if (co) {
    const subtotal = parseFloat(co.dataset.subtotal);
    const cur = co.dataset.currency;
    const fmt = n => cur + ' ' + Math.round(n).toLocaleString('en-IN');
    const codRadio = co.querySelector('input[name=payment_method][value=cod]');
    const qrRadio = co.querySelector('input[name=payment_method][value=qr]');
    const codNote = co.querySelector('[data-cod-note]');
    const method = () => co.querySelector('input[name=payment_method]:checked')?.value || (qrRadio ? 'qr' : 'cod');
    const update = () => {
      const sel = co.querySelector('input[name=location_id]:checked');
      const fee = sel ? parseFloat(sel.dataset.fee) : 0;
      $$('[data-fee]').forEach(el => el.textContent = sel ? (fee > 0 ? fmt(fee) : 'Free') : '—');
      $$('[data-total]').forEach(el => el.textContent = fmt(subtotal + fee));
      // cash on delivery depends on the chosen location
      if (codRadio) {
        const codOk = !sel || sel.dataset.cod === '1';
        codRadio.disabled = !codOk;
        codRadio.closest('.pay-method').classList.toggle('disabled', !codOk);
        if (codNote) codNote.textContent = codOk ? 'Pay in cash when your order arrives'
          : 'Not available for ' + sel.closest('.loc-opt').querySelector('.loc-name').firstChild.textContent.trim();
        if (!codOk && codRadio.checked && qrRadio) qrRadio.checked = true;
      }
      const m = method();
      $$('[data-pay-panel]', co).forEach(p => { p.hidden = p.dataset.payPanel !== m; });
    };
    co.addEventListener('change', e => { if (e.target.name === 'location_id' || e.target.name === 'payment_method') update(); });
    update();
    const ls = $('#locSearch');
    ls?.addEventListener('input', () => {
      const v = ls.value.toLowerCase().trim();
      $$('.loc-opt').forEach(o => { o.style.display = o.dataset.name.includes(v) ? '' : 'none'; });
    });
    co.addEventListener('submit', e => {
      if (!co.querySelector('input[name=location_id]:checked')) { e.preventDefault(); toast('Please choose a delivery location', 'error'); return; }
      if (method() === 'cod' && codRadio?.disabled) { e.preventDefault(); toast('Cash on delivery is not available for this location', 'error'); return; }
      if (method() === 'qr' && !co.querySelector('input[name=screenshot]')?.files.length) { e.preventDefault(); toast('Please upload your payment screenshot', 'error'); return; }
      co.querySelector('[type=submit]')?.classList.add('loading');
    });
  }

  /* ---------- progress bars ---------- */
  $$('.progress-bar span[data-w]').forEach(s => setTimeout(() => { s.style.width = s.dataset.w; }, 300));

  /* ---------- newsletter ---------- */
  $$('[data-newsletter]').forEach(f => f.addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(f); fd.append('_csrf', CSRF);
    const r = await fetch(BASE + '/ajax/newsletter.php', { method: 'POST', body: fd }).then(r => r.json()).catch(() => ({ message: 'Please try again.' }));
    toast(r.message, r.ok ? 'success' : 'error');
    if (r.ok) f.reset();
  }));
})();
