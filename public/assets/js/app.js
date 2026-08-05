/* Link. — สคริปต์ส่วนกลาง */
(function () {
  'use strict';

  /* ---- โหมดสว่าง/มืด ---- */
  const KEY = 'link-theme';
  const root = document.documentElement;

  function applyTheme(t) {
    root.setAttribute('data-theme', t);
    document.querySelectorAll('[data-theme-icon]').forEach(function (el) {
      el.textContent = t === 'dark' ? '☀️' : '🌙';
    });
  }
  // ค่าเริ่มต้นคือโหมดมืด จนกว่าผู้ใช้จะกดสลับเอง
  applyTheme(localStorage.getItem(KEY) || 'dark');

  document.addEventListener('click', function (ev) {
    const toggle = ev.target.closest('[data-theme-toggle]');
    if (toggle) {
      const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      localStorage.setItem(KEY, next);
      applyTheme(next);
    }
  });

  /* ---- ข้อความแจ้งเตือนลอย ---- */
  let toastTimer;
  window.toast = function (msg) {
    let el = document.querySelector('.toast');
    if (!el) {
      el = document.createElement('div');
      el.className = 'toast';
      document.body.appendChild(el);
    }
    el.textContent = msg;
    requestAnimationFrame(function () { el.classList.add('show'); });
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.classList.remove('show'); }, 2200);
  };

  /* ---- ปุ่มคัดลอก ---- */
  document.addEventListener('click', async function (ev) {
    const btn = ev.target.closest('[data-copy]');
    if (!btn) return;
    ev.preventDefault();
    const text = btn.getAttribute('data-copy');
    try {
      await navigator.clipboard.writeText(text);
    } catch (e) {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      ta.remove();
    }
    window.toast('คัดลอกลิงก์แล้ว');
  });

  /* ---- ยืนยันก่อนทำรายการที่ย้อนกลับไม่ได้ ---- */
  document.addEventListener('submit', function (ev) {
    const msg = ev.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) ev.preventDefault();
  });

  /* ---- เมนูด้านข้างบนมือถือ ---- */
  document.addEventListener('click', function (ev) {
    if (ev.target.closest('.menu-btn')) {
      document.querySelector('.sidebar').classList.add('open');
      const bd = document.createElement('div');
      bd.className = 'backdrop';
      bd.addEventListener('click', function () {
        document.querySelector('.sidebar').classList.remove('open');
        bd.remove();
      });
      document.body.appendChild(bd);
    }
  });

  /* ---- สุ่มรหัส CAPTCHA ใหม่ ---- */
  document.addEventListener('click', function (ev) {
    if (!ev.target.closest('#captchaReload')) return;
    const img = document.getElementById('captchaImg');
    if (img) img.src = '/captcha.php?t=' + Date.now();
    const field = document.querySelector('.captcha-input');
    if (field) { field.value = ''; field.focus(); }
  });

  /* ---- แสดง/ซ่อนตัวเลือกขั้นสูง ---- */
  document.addEventListener('click', function (ev) {
    const t = ev.target.closest('[data-toggle-target]');
    if (!t) return;
    ev.preventDefault();
    const box = document.querySelector(t.getAttribute('data-toggle-target'));
    if (box) box.classList.toggle('hide');
  });
})();
