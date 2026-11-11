/* ==========================================================================
   Eye interface behaviour (eye.js)

   Replaces remodal.min.js, remodal-auth.js, remodal-user.js, remodal-devices.js,
   select-auto.js, the jQuery Select2 bootstrap and the duplicated filter
   listeners that used to live in admin/iplist/index.php.

   The server contracts are unchanged: same forms, same field names, same
   /utils/*.php endpoints; only the transport moved from $.ajax to fetch().
   ========================================================================== */
(function () {
  'use strict';

  var body = document.body;
  // captions come from PHP (inc/header.php, data-eye-i18n); these are fallbacks
  // captions come from PHP (inc/header.php, data-eye-i18n); these are neutral fallbacks
  var L = { dense: 'Compact', normal: 'Normal', roomy: 'Roomy', theme: 'Theme: auto',
            themeL: 'Theme: light', themeD: 'Theme: dark', sel: 'selected', clear: 'Clear',
            failed: 'Failed:', busy: 'Working...',
            typeahead: 'Type to filter', nomatch: 'Nothing found',
            more: 'first %d of %d: narrow the search' };
  try { Object.assign(L, JSON.parse(body.getAttribute('data-eye-i18n') || '{}')); } catch (e) {}

  var $  = function (sel, root) { return (root || body).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  /* 1. Hooks that pages call straight from inline attributes */

  // checkAll(this.checked) selects every row of the list
  window.checkAll = function (checked) {
    var form = document.def || $('#def');
    if (!form) return;
    $$('input[type=checkbox]', form).forEach(function (cb) { cb.checked = checked; });
    syncBulk();
  };

  // the caption of the buttons of a dialog while its mass operation runs. the state of
  // the caption belongs to the safety net of inc/header.php, which marks the buttons the
  // same way: two owners of one caption is how a refused operation left the button
  // saying 'processing' for ever
  window.blockForm = function (formId) {
    if (window.EYE_MASS_BLOCK) { window.EYE_MASS_BLOCK(formId); return; }
    var form = document.getElementById(formId);
    if (!form) return;
    $$('input[type=submit], button[type=submit]', form).forEach(function (b) {
      if (b.dataset.eyeOldLabel !== undefined) return;
      b.dataset.eyeOldLabel = '1';
      b.dataset.eyeOld = (b.value !== undefined) ? b.value : b.textContent;
      b.disabled = true;
      if (b.value !== undefined) { b.value = L.busy; } else { b.textContent = L.busy; }
    });
  };

  window.undoBlockForm = function (formId) {
    if (window.EYE_MASS_RELEASE) { window.EYE_MASS_RELEASE(formId); return; }
    var form = document.getElementById(formId);
    if (!form) return;
    $$('input[type=submit], button[type=submit]', form).forEach(function (b) {
      if (b.dataset.eyeOldLabel === undefined) return;
      delete b.dataset.eyeOldLabel;
      b.disabled = false;
      if (b.dataset.eyeOld) {
        if (b.value !== undefined) { b.value = b.dataset.eyeOld; } else { b.textContent = b.dataset.eyeOld; }
      }
      delete b.dataset.eyeOld;
    });
  };

  /* 2. Theme and density */

  var root = document.documentElement;

  // density cycle, the choice is kept in localStorage. the attribute of the root element
  // always names the mode: the stylesheet keeps the usual look in its own root values and
  // lifts the rows by the attribute, inc/header.php writes the same attribute before the
  // first paint, and eye.js reads it back. the compact look is the default, as the caption
  // of the button in the rail says
  var DENS = ['compact', 'normal', 'roomy'];
  var DENS_DEFAULT = 'compact';
  var DENS_LABEL = { compact: L.dense, normal: L.normal, roomy: L.roomy };

  function densKnown(mode) {
    return (DENS.indexOf(mode) >= 0) ? mode : null;
  }

  // the mode which is really in effect: the remembered one, then the one the page was
  // painted with, then the default. the cycle used to read the attribute alone and to take
  // 'compact' when there was none, while the code wrote the mode 'normal' by removing the
  // attribute: the second press computed 'compact' again, the next mode was 'normal' again,
  // and the button could never reach 'roomy' at all from the second press on
  function densNow() {
    var saved = null;
    try { saved = localStorage.getItem('eye.dens'); } catch (e) {}
    return densKnown(saved) || densKnown(root.getAttribute('data-eye-dens')) || DENS_DEFAULT;
  }

  function paintDens(mode) {
    root.setAttribute('data-eye-dens', mode);
    // the caption is what a wide rail shows; a narrow one turns it into an icon by
    // font-size: 0, so the name of the mode must live in the attributes as well, or the
    // button becomes anonymous for a screen reader and gives no hint to a pointing hand
    $$('[data-eye-dens-btn]').forEach(function (b) {
      b.textContent = DENS_LABEL[mode];
      b.title = DENS_LABEL[mode];
      b.setAttribute('aria-label', DENS_LABEL[mode]);
    });
  }

  function setDens(mode) {
    mode = densKnown(mode) || DENS_DEFAULT;
    paintDens(mode);
    try { localStorage.setItem('eye.dens', mode); } catch (e) {}
  }

  // theme: auto (follows the system) / light / dark - an attribute only
  var THEME_LABEL = { auto: L.theme, light: L.themeL, dark: L.themeD };
  function setTheme(mode) {
    if (mode === 'auto') { root.removeAttribute('data-eye'); }
    else { root.setAttribute('data-eye', mode); }
    try { localStorage.setItem('eye.theme', mode); } catch (e) {}
    $$('[data-eye-theme-btn]').forEach(function (b) {
      b.textContent = THEME_LABEL[mode];
      b.title = THEME_LABEL[mode];
      b.setAttribute('aria-label', THEME_LABEL[mode]);
    });
  }

  function addControls() {
    var foot = $('#eye-rail .rail-foot');
    if (!foot) return;
    var dens = foot.querySelector('[data-eye-dens-btn]');
    if (dens && !dens.dataset.eyeBound) {
      dens.dataset.eyeBound = '1';
      dens.addEventListener('click', function () {
        setDens(DENS[(DENS.indexOf(densNow()) + 1) % DENS.length]);
      });
    }
    var theme = document.createElement('button');
    theme.type = 'button';
    theme.className = 'btn sm';
    theme.setAttribute('data-eye-theme-btn', '');
    theme.addEventListener('click', function () {
      var cur = root.getAttribute('data-eye') || 'auto';
      setTheme(cur === 'auto' ? 'light' : (cur === 'light' ? 'dark' : 'auto'));
    });
    if (foot) foot.appendChild(theme);
  }

  /* 3. Dialogs Pages print <a class="mainButton" href="#modal"> next to <dialog id="modal">: without JS the link is a plain anchor, with JS it opens a native dialog. */

  function openDialog(id) {
    var dlg = document.getElementById(id);
    if (dlg && typeof dlg.showModal === 'function') { dlg.showModal(); return true; }
    return false;
  }

  function wireDialogs() {
    $$('a[href^="#"], button[data-open]').forEach(function (a) {
      var id = (a.getAttribute('data-open') || a.getAttribute('href') || '').replace(/^#/, '');
      var dlg = id && document.getElementById(id);
      if (!dlg || dlg.tagName !== 'DIALOG') return;
      a.addEventListener('click', function (ev) { if (openDialog(id)) ev.preventDefault(); });
    });
    $$('dialog').forEach(function (dlg) {
      // close button and click on the backdrop
      $$('[data-close]', dlg).forEach(function (b) {
        b.addEventListener('click', function () { dlg.close(); });
      });
      dlg.addEventListener('click', function (ev) { if (ev.target === dlg) dlg.close(); });
      dlg.addEventListener('close', function () {
        var f = dlg.querySelector('form'); if (f) undoBlockForm(f.id);
      });
    });
  }

  /* 4. Mass actions: same endpoints, same field names as before */

  // the endpoints of the mass operations are printed into the page by inc/header.php,
  // where they can not grow old beside the base; the map below is only the last resort
  // for a page which came without the block of the safety net
  var ACTIONS = window.EYE_MASS || {
    formAuthApply:    { url: '/utils/auth_apply.php' },
    formAuthDel:      { url: '/utils/auth_remove.php' },
    formAuthExport:   { url: '/utils/auth_export.php', download: 'all-ips.csv', type: 'text/csv' },
    formUserApply:    { url: '/utils/user_apply.php' },
    formUserDel:      { url: '/utils/user_remove.php' },
    formNetDevApply:  { url: '/utils/devices_apply.php' },
    formNetDevDel:    { url: '/utils/devices_remove.php' }
  };

  function note(dialog, text, isErr) {
    var box = dialog.querySelector('.dlg-error');
    if (!box) {
      box = document.createElement('p');
      box.className = 'note dlg-error' + (isErr ? ' err' : '');
      var submit = dialog.querySelector('[type=submit]');
      if (submit && submit.parentNode) submit.parentNode.insertBefore(box, submit);
      else dialog.appendChild(box);
    }
    box.className = 'note dlg-error' + (isErr ? ' err' : '');
    box.textContent = text;
  }

  function wireActions() {
    Object.keys(ACTIONS).forEach(function (formId) {
      var form = document.getElementById(formId);
      if (!form) return;
      // the safety net of header.php steps aside when the form is marked, so the same
      // request is never sent twice: the mark says that the dialog is owned by this
      // script, not that it worked without an error
      form.dataset.eyeMassWired = '1';
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var cfg = ACTIONS[formId];
        var dlg = form.closest('dialog');
        var list = document.getElementById('def');           // the list form that carries fid[]
        var data = new FormData();
        if (list) {
          new FormData(list).forEach(function (v, k) { data.append(k, v); });
        }
        new FormData(form).forEach(function (v, k) { data.append(k, v); });
        blockForm(formId);

        fetch(cfg.url, { method: 'POST', body: data, credentials: 'same-origin' })
          .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            // export is a binary answer, everything else is a .result HTML block
            return cfg.download ? r.blob().then(function (b) { return { blob: b }; })
                                : r.text().then(function (s2) { return { html: s2 }; });
          })
          .then(function (res) {
            if (cfg.download && res.blob) {
              var a = document.createElement('a');
              a.href = URL.createObjectURL(res.blob);
              a.download = cfg.download;
              document.body.appendChild(a); a.click(); a.remove();
              if (dlg) dlg.close();
              refreshList();
              return;
            }
            var failed = /class=\"result err\"/.test(res.html || '');
            if (failed) { toast(res.html, true); undoBlockForm(formId); return; }                // the dialog stays open
            flashSet(res.html);                                                    // survives the list reload
            if (dlg) dlg.close();
            refreshList();
          })
          .catch(function (err) {
            undoBlockForm(formId);
            note(dlg || form, L.failed + ' ' + err.message, true);
          });
      });
    });
  }

  // refresh the list the same way as before: re-apply the current filter
  function refreshList() {
    var btn = document.getElementById('btn_filter');
    if (btn) { setTimeout(function () { btn.click(); }, 350); return; }
    setTimeout(function () { location.reload(); }, 350);
  }

  /* 5. The apply-this-field checkboxes in the dialogs Same e_<field>/a_<field> pattern the old JS files used, plus unmarked rows now also look inactive. */

  function wireMassFields() {
    $$('dialog input.putField[type=checkbox]').forEach(function (cb) {
      var row = cb.closest('tr') || cb.closest('.mrow');
      if (!row) return;
      // make the caption in the next cell clickable (form names and ids untouched)
      var lab = row.children ? row.children[1] : null;
      if (lab && !lab.querySelector('label') && lab.textContent.trim() && !lab.querySelector('input,select')) {
        if (!cb.id) cb.id = 'eye-' + (cb.name || 'f') + '-' + Math.random().toString(36).slice(2, 7);
        var txt = lab.firstChild, span = document.createElement('label');
        span.setAttribute('for', cb.id);
        span.textContent = txt.textContent;
        lab.replaceChild(span, txt);
      }
      var sync = function () {
        row.classList.toggle('off', !cb.checked);
        if (lab) lab.classList.toggle('off', !cb.checked);
        $$('select, input:not([type=checkbox])', row).forEach(function (f) { f.disabled = !cb.checked; });
      };
      cb.addEventListener('change', sync);
      sync();
    });
  }

  /* 6. The "N selected" bar The buttons come from the page itself (mainButton/delButton/exportButton), so no second set of actions ever appears. */

  var bar = null;
  function syncBulk() {
    var boxes = $$('input[name="fid[]"]').filter(function (b) { return !b.disabled; });
    if (!boxes.length) return;
    var n = boxes.filter(function (b) { return b.checked; }).length;
    if (!bar) {
      bar = document.createElement('div');
      bar.className = 'bulk';
      bar.innerHTML = '<b>0</b><span>' + L.sel + '</span><span class="sp"></span>';
      $$('a.mainButton, a.exportButton, a.delButton, a[class$="Button"]').forEach(function (src) {
        if (bar.querySelector('[data-mirror="' + src.className + '"]')) return;
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn sm ' + (src.classList.contains('delButton') ? 'dan' : '');
        b.textContent = src.textContent;
        b.setAttribute('data-mirror', src.className);
        b.addEventListener('click', function () { src.click(); });
        bar.appendChild(b);
      });
      var clr = document.createElement('button');
      clr.type = 'button'; clr.className = 'btn sm'; clr.textContent = L.clear;
      clr.addEventListener('click', function () {
        boxes.forEach(function (b) { b.checked = false; });
        var head = $('#headcb') || $$('input[type=checkbox]').filter(function (i) {
          return i.onclick && /checkAll/.test(String(i.onclick)); })[0];
        if (head) head.checked = false;
        syncBulk();
      });
      bar.appendChild(clr);
      document.body.appendChild(bar);
    }
    bar.querySelector('b').textContent = n;
    bar.classList.toggle('show', n > 0);
  }

  function wireBulk() {
    document.addEventListener('change', function (ev) {
      var t = ev.target;
      if (t && t.matches('input[type=checkbox]')) syncBulk();
    });
  }

  /* 7. Filters: one delegated handler instead of seven copies */

  function wireFilters() {
    $$('#cont form').forEach(function (form) {
      var btn = form.querySelector('#btn_filter, [name=btn_filter]');
      if (!btn) return;
      form.addEventListener('change', function (ev) {
        var t = ev.target;
        if (t && (t.tagName === 'SELECT')) btn.click();
      });
    });
    // "/" focuses the search box, as on network consoles
    document.addEventListener('keydown', function (ev) {
      if (ev.key !== '/' || /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) return;
      var q = $('#q, input[name=search_str]');
      if (q) { ev.preventDefault(); q.focus(); q.select(); }
    });
  }

  /* 8. Searchable select, replaces Select2 Wraps the ordinary <select class="js-select-single"> that the print_* helpers emit. The select stays in the form: name, value and submission are unchanged. Without JS it is a plain dropdown. */

  var esSeq = 0;
  // A list of a hundred thousand subscribers is not a list: only the head of the filtered
  // result reaches the dom. An unbounded render froze the whole tab for seconds on every
  // opening and on every keystroke of the search.
  var ES_MAX = 200;      // rows that reach the dom at once
  var ES_CHUNK = 2000;   // option nodes read before the loop looks at what it has
  var ES_LIMIT = 5000;   // options a pass goes through: a pass is tens of ms, not seconds
  var ES_MIN = 60;       // two rows of the list: less than this a dropdown is useless

  // A part of the option list, read from the dom. The text of a hundred thousand options is
  // nothing to touch at once: every read also lower cases the caption. The pass stops when it
  // has met ES_LIMIT matching options or has reached the end of the list, and what it has read
  // stays cached for the next keystroke.
  function esReadOptions(sel, from, out) {
    var opts = sel.options;
    var n = Math.min(opts.length, from + ES_CHUNK);
    for (var i = from; i < n; i++) {
      var o = opts[i];
      var t = o.textContent.trim();
      var v = String(o.value);
      out.push({ v: v, t: t, l: t.toLowerCase(), lv: v.toLowerCase(), d: !!o.disabled });
    }
    return n >= opts.length;
  }

  function esSelectedText(sel) {
    var o = sel.options[sel.selectedIndex];
    return o ? String(o.textContent).trim() : '';
  }

  function esEnhance(sel) {
    if (sel.dataset.eyeSelect) return;
    sel.dataset.eyeSelect = '1';

    var id = sel.id || (sel.name || 'select') + '-es' + (++esSeq);
    var wrap = document.createElement('span');
    wrap.className = 'es';
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'es-btn';
    // the label is a node of its own, so a long name can be cut with an ellipsis
    var label = document.createElement('span');
    label.className = 'es-txt';
    btn.appendChild(label);
    btn.setAttribute('role', 'combobox');
    btn.setAttribute('aria-haspopup', 'listbox');
    btn.setAttribute('aria-expanded', 'false');
    var pop = document.createElement('span');
    pop.className = 'es-pop';
    pop.hidden = true;
    pop.setAttribute('role', 'listbox');
    var q = document.createElement('input');
    q.type = 'search';
    q.className = 'es-q';
    q.setAttribute('placeholder', L.typeahead);
    q.setAttribute('aria-label', L.typeahead);
    var list = document.createElement('ul');
    list.className = 'es-list';
    pop.appendChild(q); pop.appendChild(list);
    sel.classList.add('es-native');
    sel.tabIndex = -1;
    sel.setAttribute('aria-hidden', 'true');
    // insert the wrapper exactly where the select was: read parentNode BEFORE moving nodes,
    // otherwise insertBefore into own descendant raises DOMException (incorrect node tree)
    var host = sel.parentNode;
    if (!host) return;
    host.insertBefore(wrap, sel);
    wrap.appendChild(btn); wrap.appendChild(sel); wrap.appendChild(pop);

    var all = [];              // the options read from the dom so far, in lower case
    var done = false;          // the whole list was read
    var whole = false;         // the filter ran over everything that was read
    var shown = [];            // the head of the rows that pass the filter
    var total = 0;             // how many pass, it stops at ES_LIMIT
    var act = -1;              // highlighted row
    var actEl = null;          // its node, to unmark it without a walk over the list

    function paint() {
      label.textContent = esSelectedText(sel) || '—';
      btn.title = label.textContent;
      btn.disabled = sel.disabled;
      wrap.classList.toggle('es-dis', sel.disabled);
    }

    function rows() {
      var needle = q.value.trim().toLowerCase();
      shown = [];
      total = 0;
      whole = false;

      // the cached part is walked from the beginning at every keystroke: those are plain
      // objects, and an option which was read before must not disappear from the result.
      // new options are read from the dom only while the walk has not had enough of them
      var stop = false;
      while (!stop) {
        if (!done) { done = esReadOptions(sel, all.length, all); }
        for (var i = 0; i < all.length; i++) {
          var o = all[i];
          if (!needle || o.l.indexOf(needle) > -1 || o.lv.indexOf(needle) > -1) {
            total++;
            if (shown.length < ES_MAX) { shown.push(o); }
            if (total >= ES_LIMIT) { break; }
          }
        }
        stop = (total >= ES_LIMIT) || (done && i >= all.length);
        if (stop) { whole = done && i >= all.length; }
      }

      var frag = document.createDocumentFragment();
      shown.forEach(function (item, n) {
        var li = document.createElement('li');
        li.className = 'es-opt' + (item.v === sel.value ? ' on' : '');
        li.setAttribute('role', 'option');
        li.setAttribute('aria-selected', item.v === sel.value ? 'true' : 'false');
        li.id = id + '-o' + n;
        li.dataset.i = n;
        li.textContent = item.t || item.v;
        if (item.d) { li.classList.add('dis'); li.setAttribute('aria-disabled', 'true'); }
        frag.appendChild(li);
      });
      if (!total) {
        var empty = document.createElement('li');
        empty.className = 'es-opt es-none';
        empty.textContent = L.nomatch;
        frag.appendChild(empty);
      } else if (total > shown.length || !whole) {
        var more = document.createElement('li');
        more.className = 'es-opt es-more';
        // both placeholders are filled one by one: the captions of eye carry two %d
        more.textContent = L.more.replace('%d', shown.length).replace('%d', total);
        frag.appendChild(more);
      }

      list.textContent = '';
      list.appendChild(frag);
      actEl = null;
      mark(shown.length ? 0 : -1);
    }

    function mark(i) {
      if (actEl) { actEl.classList.remove('act'); actEl = null; }
      act = i;
      if (i > -1 && i < shown.length) {
        actEl = list.querySelector('li[data-i="' + i + '"]');
        if (actEl) {
          actEl.classList.add('act');
          if (actEl.scrollIntoView) { actEl.scrollIntoView({ block: 'nearest' }); }
          btn.setAttribute('aria-activedescendant', actEl.id);
        }
      } else {
        btn.removeAttribute('aria-activedescendant');
      }
    }

    function pick(i) {
      if (i < 0 || !shown[i] || shown[i].d) return;
      sel.value = shown[i].v;
      paint();
      close();
      // the search field of the list is hidden together with the list, and a hidden field
      // leaves the focus of the browser nowhere but the body: after a choice the operator
      // stood outside the dialog with his keyboard, and the first Tab began from the top of
      // the page. the button of the field takes the focus back
      btn.focus();
      sel.dispatchEvent(new Event('change', { bubbles: true }));   // auto-apply filters and inline onchange keep firing
    }

    // The list of the widget does not hang in the cell any more: it is fixed to the
    // viewport. Any ancestor which scrolls cuts what steps over its edge, and the body of
    // a dialog is exactly such an ancestor - a short dialog leaves less room for the list
    // than the list needs, and the operator again sees some rows only. The frame which must
    // hold the list is the dialog of the widget while it is open, and the window on a page.
    // When the space under the button is not enough and above there is more, the list goes
    // over the button, and it takes no more rows than the frame leaves: the list gets a
    // scrollbar of its own instead of sending the rest of its rows behind a border.
    // the box which does not let the widget over its edge: the body of a dialog is such a
    // box, and it is the only one which matters. the answer is also used straight from the
    // scroll handler, because a frame of the animation is not a promise: on a hidden or a
    // throttled tab the list would stay hanging over an empty place
    function outOfSight() {
      var b = wrap.getBoundingClientRect();
      if (b.bottom < 0 || b.top > window.innerHeight) { return true; }
      var node = wrap.parentElement;
      while (node && node !== document.body && node !== document.documentElement) {
        var cs = getComputedStyle(node);
        if (cs.position === 'fixed') { break; }
        if (cs.overflowY !== 'visible' || cs.overflowX !== 'visible') {
          var r = node.getBoundingClientRect();
          return (b.bottom < r.top + 2 || b.top > r.bottom - 2);
        }
        node = node.parentElement;
      }
      return false;
    }

    function place() {
      var b = wrap.getBoundingClientRect();
      var host = wrap.closest('dialog');
      var box = (host && host.open) ? host.getBoundingClientRect() : null;
      // sideways the list keeps to the panel it belongs to, so it does not hang over the
      // border of the dialog as the wide tables of it did once; from top to bottom the hard
      // limit is the window only: the panel of a short dialog is smaller than the list needs,
      // and the rows of a dropdown may cover the head and the action of the dialog, as any
      // menu does - the list is a guest which does not stay, and it vanishes on the first
      // scroll, on a click aside and on Escape
      var limit = { top: 0, bottom: window.innerHeight,
                    left: box ? box.left : 0, right: box ? box.right : window.innerWidth };
      var gap = 6;                       // the space between the button and the list

      // a fixed node measures itself against the viewport, so the width of the pop stops
      // being a share of the cell and becomes the width of the button it belongs to
      pop.style.position = 'fixed';
      pop.style.minWidth = '0';
      pop.style.width = Math.round(Math.min(b.width, limit.right - limit.left - 8)) + 'px';

      var up = b.top - limit.top, down = limit.bottom - b.bottom;
      var above = (pop.offsetHeight + gap > down) && (up > down);
      wrap.classList.toggle('es-up', above);

      // the search field and the padding of the pop eat from the room before the rows
      var shell = pop.offsetHeight - list.offsetHeight + gap;
      var room = (above ? up : down) - shell;
      if (room < ES_MIN) { room = ES_MIN; }
      list.style.maxHeight = (room < list.offsetHeight) ? room + 'px' : '';

      // The widget may be scrolled out of the window, and out of the box which clips it:
      // the body of a dialog cuts its own rows, so a field which is gone from the panel has
      // no list to belong to, and the list must go away with it instead of hanging over the
      // head of the dialog. The clipping box is asked only for this decision - the room for
      // the rows is measured against the window, or the list of a short dialog could show
      // two rows out of twenty
      if (outOfSight()) { close(); return; }

      var h = pop.offsetHeight;
      var left = b.left;
      if (left + pop.offsetWidth > limit.right - 4) { left = limit.right - 4 - pop.offsetWidth; }
      if (left < limit.left + 4) { left = limit.left + 4; }
      pop.style.left = Math.round(left) + 'px';
      pop.style.top = Math.round(above ? b.top - gap - h : b.bottom + gap) + 'px';
    }

    // A fixed list stands outside the flow of the page it belongs to, so every movement
    // under it - a scroll of the body of the dialog, of the page, a resize of the window -
    // leaves the list hanging over another row. Closing the list on such a movement was the
    // first thought and was wrong: the browser scrolls the page itself when the list is
    // revealed on a short window, and the operator saw a list which vanished the moment it
    // appeared. The list is put in its place again instead, once per frame.
    var follow = 0;
    function reflow() {
      if (pop.hidden) { return; }
      if (outOfSight()) { close(); return; }
      if (follow) { return; }
      follow = requestAnimationFrame(function () { follow = 0; place(); });
    }

    function forgetFollow() {
      if (follow) { cancelAnimationFrame(follow); follow = 0; }
    }

    function open() {
      if (sel.disabled || !pop.hidden) return;
      pop.hidden = false; q.value = ''; rows(); place();
      wrap.classList.add('open');
      btn.setAttribute('aria-expanded', 'true');
      document.addEventListener('mousedown', outside, true);
      document.addEventListener('scroll', reflow, true);
      window.addEventListener('resize', reflow);
      q.focus();
    }

    function close() {
      if (pop.hidden) return;
      pop.hidden = true;
      wrap.classList.remove('open', 'es-up');
      btn.setAttribute('aria-expanded', 'false');
      forgetFollow();
      document.removeEventListener('mousedown', outside, true);
      document.removeEventListener('scroll', reflow, true);
      window.removeEventListener('resize', reflow);
    }

    function outside(ev) { if (!wrap.contains(ev.target)) close(); }

    // one listener for the whole list instead of a listener per row: with a long list the
    // listeners themselves were a second reason of the freeze and of the memory.
    // pressing the row is answered in full only in the click of the mouse: the old code
    // chose the row at the mousedown and hid the list in the same moment, so the release of
    // the button fell on the empty place where the row had laid - the browser gives such a
    // pair of events to the dialog itself, and the handler of the panel which closes it on a
    // click aside read the choice of a row as a click on the backdrop. the list stays open
    // through both events now, and the mousedown only keeps the focus of the search field
    // from running away and forbids the selection of the text
    list.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
    list.addEventListener('click', function (ev) {
      var li = ev.target.closest ? ev.target.closest('li') : null;
      if (!li || !list.contains(li) || li.dataset.i === undefined) return;
      pick(parseInt(li.dataset.i, 10));
    });
    btn.addEventListener('click', function () { pop.hidden ? open() : close(); });
    sel.addEventListener('focus', open);              // clicking <label for="..."> lands here too
    q.addEventListener('input', function () {
      var needle = q.value.trim().toLowerCase();
      // a key that did not change the filter must not rebuild anything (ime and case shifts
      // fire several events per sign)
      if (needle === (q.dataset.eyeNeedle || '')) { return; }
      q.dataset.eyeNeedle = needle;
      rows();
    });
    q.addEventListener('keydown', function (ev) {
      var k = ev.key;
      if (k === 'ArrowDown') { ev.preventDefault(); mark(Math.min(act + 1, shown.length - 1)); }
      else if (k === 'ArrowUp') { ev.preventDefault(); mark(Math.max(act - 1, 0)); }
      else if (k === 'Home') { ev.preventDefault(); mark(0); }
      else if (k === 'End') { ev.preventDefault(); mark(shown.length - 1); }
      else if (k === 'Enter') { ev.preventDefault(); pick(act); }
      else if (k === 'Escape') { ev.preventDefault(); close(); btn.focus(); }
      else if (k === 'Tab') { close(); }
    });
    btn.addEventListener('keydown', function (ev) {
      if (ev.key === 'ArrowDown' || ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); open(); }
    });
    new MutationObserver(paint).observe(sel, { attributes: true, attributeFilter: ['disabled'] });

    // a page may refill the options of a select from its own script: the cache is then void
    var esReset = new MutationObserver(function () {
      all = []; done = false;
      if (!pop.hidden) { rows(); }            // an open list is rebuilt from the new options
    });
    esReset.observe(sel, { childList: true, subtree: true });
    paint();
  }

  function wireSearchableSelects(root) {
    $$('select.js-select-single', root || document).forEach(esEnhance);
  }


  /* 9. Label rows inside form tables edituser.php and editauth.php print a row of captions ("Login", "Per day") followed by a row of inputs. CSS cannot look at the next row, so JS marks the caption row. */

  function dataTables() {
    // table.data plus the plain tables inside the content column: printed by auto_rules.php,
    // building.php and the filter tables of the old pages
    var seen = [], out = [];
    $$('table.data, #cont table').forEach(function (tbl) {
      if (seen.indexOf(tbl) > -1) return; seen.push(tbl); out.push(tbl);
    });
    return out;
  }

  function markLabelRows() {
    dataTables().forEach(function (tbl) {
      if (tbl.closest('dialog')) return;              // dialogs caption their rows differently
      // in data lists the first row is the column header and CSS handles it;
      // in form tables (edituser, editauth) the first row really is field captions
      // captions are exactly what has to be marked here
      // a data list is recognised by array-style field names (fid[], f_auth_id[],
      // d_port_index[]); a single checkbox such as PTR-only does not make a list
      var isList = !!tbl.querySelector('[name*="\\[\\]"]');
      var rows = $$(':scope > tbody > tr, :scope > tr', tbl);
      for (var i = 0; i < rows.length - 1; i++) {
        var r = rows[i], next = rows[i + 1];
        var hasCtl = function (el) { return !!el.querySelector('input,select,textarea,button'); };
        var textOnly = Array.prototype.every.call(r.children, function (td) {
          return td.children.length === 0 || !hasCtl(td);
        });
        if (isList && r === rows[0]) continue;               // the column header is not a field caption
        // an empty separator row is not a caption
        if (!r.textContent.trim()) continue;
        if (textOnly && !hasCtl(r) && hasCtl(next)) r.classList.add('lblrow');
      }
    });
  }


  /* 10. Caption and field in the same cell edituser.php prints <td>New address IP:&nbsp<input></td>. The short leading text becomes a span so it reads as a label; long text is treated as data. */

  function labelizeCells() {
    $$('table.data td, #cont table td').forEach(function (td) {
      if (td.dataset.eyeLabeled) return;
      if (!td.querySelector('input, select, textarea')) return;   // cells that hold a field only
      var n = td.firstChild;
      if (!n || n.nodeType !== 3) return;                          // text has to come first
      var raw = n.nodeValue, lead = raw.replace(/^\s+/, '').replace(/[\s\u00a0]+$/, '');
      if (!lead || lead.length > 40) return;
      if (lead.indexOf(':') === -1 && lead.length > 24) return;
      var headWs = raw.slice(0, raw.indexOf(lead));
      var tailWs = raw.slice(raw.lastIndexOf(lead) + lead.length);
      var span = document.createElement('span');
      span.className = 'cell-lbl';
      span.textContent = lead;
      var frag = document.createDocumentFragment();
      if (headWs) frag.appendChild(document.createTextNode(headWs));
      frag.appendChild(span);
      if (tailWs) frag.appendChild(document.createTextNode(tailWs));
      td.replaceChild(frag, n);
      td.dataset.eyeLabeled = '1';
    });
  }

  /* 11. Key cells ("Created by:", "Created:") editauth.php and the report pages print <td>key:</td><td>value</td>, so the caption is recognised by its trailing colon. */

  function markKeyCells() {
    $$('table.data td, #cont table td').forEach(function (td) {
      if (td.dataset.eyeKeyed) return;                       // already processed
      if (td.querySelector('input, select, textarea, button, .cell-lbl')) return;
      if (td.children.length > 1) return;
      if (td.parentElement.children.length === 1) return;   // one-cell caption row: CSS takes care of it
      var n = td.firstChild;
      if (!n || n.nodeType !== 3) return;
      var raw = n.nodeValue, txt = raw.replace(/[\s\u00a0]+$/, '');
      if (!/:$/.test(txt.trim()) || txt.trim().length > 60) return;
      var span = document.createElement('span');
      span.className = 'cell-key';
      span.textContent = txt.replace(/^\s+/, '');
      var frag = document.createDocumentFragment();
      frag.appendChild(span);
      if (raw.slice(raw.indexOf(txt.trim()) + txt.trim().length)) {
        frag.appendChild(document.createTextNode(raw.slice(raw.indexOf(txt.trim()) + txt.trim().length)));
      }
      td.insertBefore(frag, n);
      td.removeChild(n);
      td.dataset.eyeKeyed = '1';                             // flag only when the wrap really happened
    });
  }


  /* 12. A caption to the left of its own field Applies to forms only; data lists are recognised by their array-style checkbox names and are skipped. */

  function markInlineLabels() {
    dataTables().forEach(function (tbl) {
      if (tbl.closest('dialog')) return;
      // data lists are marked by checkbox names ending in [] (fid[], f_auth_id[]);
      // they are not captions: the text there is data
      if (tbl.querySelector('input[name*="\\[\\]"]')) return;
      $$(':scope > tbody > tr, :scope > tr', tbl).forEach(function (r) {
        if (r.querySelector('input[type=checkbox]')) return;      // a row with selection is not a form
        var cells = Array.prototype.slice.call(r.children);
        for (var i = 0; i < cells.length - 1; i++) {
          var td = cells[i], nxt = cells[i + 1];
          if (td.querySelector('*') || td.dataset.eyeKeyed) continue;   // purely textual cells only
          var txt = td.textContent.trim();
          if (!txt || txt.length > 40 || /:$/.test(txt)) continue;      // "Key:" cells were marked earlier
          if (/\d/.test(txt)) continue;                                  // "Count: 1" is data, not a caption
          if (!hasField(nxt)) continue;                                  // the caption must lead to an input field
          var span = document.createElement('span');
          span.className = 'cell-key';
          span.textContent = txt;
          td.textContent = '';
          td.appendChild(span);
          td.dataset.eyeKeyed = '1';
        }
      });
    });
    // a real field only: a -MAC button after "Count: 1" is not a caption
    function hasField(td) {
      return !!td && !!td.querySelector('input:not([type=submit]):not([type=button]):not([type=hidden]), select, textarea');
    }
  }



  /* 13. Navigation strips versus content containers #submenu and #subsubmenu are usually tab strips, but on 14 pages #contsubmenu is the content container itself (switchstatus.php keeps forms and tables inside it), so eye.css styles only the .tabs class set here. */

  function classifyContainers() {
    $$('#submenu, #subsubmenu, #contsubmenu').forEach(function (el) {
      if (el.dataset.eyeRole) return;
      el.dataset.eyeRole = '1';
      var isStrip = !el.querySelector('table, form, input, select, textarea, img')
                  && el.querySelectorAll('a, b').length > 0
                  && el.textContent.trim().length < 260;
      el.classList.add(isStrip ? 'tabs' : 'pane');
    });
  }

  /* 14. "Open a window" buttons inside tables A <button> without type and without name is a submit button, so clicking the MAC counter also posted the port list. Only nameless window.open buttons are retyped; real actions such as Save and POE keep their names. */

  function fixWindowButtons() {
    $$('table button:not([type]):not([name])').forEach(function (b) {
      if (/window\.open/.test(b.getAttribute('onclick') || '')) { b.type = 'button'; }
    });
  }


  /* 15. List cells switchstatus and switchport print one device per line in a cell and the matching comment in the next one. Narrow columns used to wrap and break that alignment, so each value becomes its own block. */

  function markListCells() {
    $$('table.data td').forEach(function (td) {
      if (td.dataset.eyeList) return;
      if (td.querySelectorAll('br').length < 2) return;               // two or more breaks make a list
      if (td.querySelector('input:not([type=hidden]), select, textarea, table')) return;
      var frag = document.createDocumentFragment(), line = document.createElement('span');
      line.className = 'ln';
      Array.prototype.slice.call(td.childNodes).forEach(function (n) {
        if (n.nodeType === 1 && n.tagName === 'BR') {
          if (line.childNodes.length) frag.appendChild(line);
          line = document.createElement('span'); line.className = 'ln';
        } else {
          line.appendChild(n.cloneNode(true));
        }
      });
      if (line.childNodes.length) frag.appendChild(line);
      if (!frag.querySelector || frag.querySelectorAll('.ln').length < 2) return;
      td.textContent = '';
      td.appendChild(frag);
      td.classList.add('nl');
      td.dataset.eyeList = '1';
    });
  }


  /* 16. Links that open their own window switchport, switchstatus and mactable call window.open() from onclick; the .ext class lets CSS add the arrow so "opens separately" is visible. */

  function markExternalLinks() {
    $$('a[onclick*="window.open"]').forEach(function (a) { a.classList.add('ext'); });
  }


  /* 17. One accented button per form Save has to stand out from move / refresh / delete. Eye uses stable button names, so candidates are picked by name, ranked (edit* then save_ipset then save then s_save) and one per form is marked .pri. An explicit class="pri" in the markup wins over this heuristic; dialogs and the Show button are skipped, they have their own accent rule. */

  var PRI_RANK = [/^edit/i, /^save_ipset$/, /^save$/, /^s_save$/];

  function markPrimaryActions() {
    $$('form').forEach(function (f) {
      if (f.closest('dialog')) return;
      var found = $$(':is(input[type=submit], button[type=submit], button:not([type]))', f).filter(function (el) {
        if (el.closest('dialog')) return false;
        if (el.id === 'btn_filter' || el.name === 'btn_filter') return false;
        if (el.classList.contains('danger') || el.classList.contains('x')) return false;
        if (/\[\]$/.test(el.name || '')) return false;          // per-row actions of a list
        var rk = rank(el);
        return rk > -1;
      });
      var explicit = $$('.pri', f).filter(function (el) { return !el.closest('dialog'); });
      if (explicit.length) return;                                // the markup already decided
      if (!found.length) return;
      found.sort(function (a, b) { return rank(a) - rank(b); });
      found[0].classList.add('pri');
    });
    function rank(el) {
      var n = el.name || '';
      for (var i = 0; i < PRI_RANK.length; i++) { if (PRI_RANK[i].test(n)) return i; }
      return -1;
    }
  }


  /* 18. Operation result: a toast that survives the reload The answer from /utils/*.php used to be injected into the dialog form and lost on refresh. Success: flash plus reload. Failure: toast and the dialog stays open. */
  var toastTimer = null;
  function toast(html, isErr) {
    var t = document.querySelector('.toast');
    if (!t) { t = document.createElement('div'); t.className = 'toast'; document.body.appendChild(t); }
    t.innerHTML = html || (isErr ? '<div class="result err">Failed</div>' : '<div class="result ok">Done</div>');
    t.classList.toggle('err', !!isErr);
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      if (t.parentNode) t.parentNode.removeChild(t);
      toastTimer = null;
    }, isErr ? 9000 : 5000);
  }
  function flashSet(html) { try { if (html) sessionStorage.setItem('eye.flash', html); } catch (e) {} }
  function flashTake() {
    var h = null;
    try { h = sessionStorage.getItem('eye.flash'); if (h) sessionStorage.removeItem('eye.flash'); } catch (e) {}
    return h;
  }

  /* init */

  // every init step is isolated: one failure must not switch the mechanism off on the remaining pages (the console says which step failed)
  // on the other pages, console.warn reports what did not run
  function safe(fn, what) {
    try { fn(); }
    catch (e) { if (window.console && console.warn) console.warn('[eye] ' + what + ': ' + e.message); }
  }

  safe(addControls, 'controls');
  try {
    // the mode itself is applied by the inline script of inc/header.php before the first
    // paint; here the attribute is only made sure of and the caption of the button is
    // synced with it. nothing is written to the storage of a visitor who never pressed the
    // button: his choice must stay absent, not become a record of the default mode
    paintDens(densNow());
    var savedTheme = localStorage.getItem('eye.theme');
    if (savedTheme && savedTheme !== 'auto') setTheme(savedTheme);
    else setTheme(root.getAttribute('data-eye') === 'dark' ? 'dark'
                : (root.getAttribute('data-eye') === 'light' ? 'light' : 'auto'));
  } catch (e) {}
  safe(wireDialogs, 'dialogs');
  safe(wireActions, 'actions');
  safe(wireMassFields, 'mass fields');
  safe(wireBulk, 'selection bar');
  safe(wireSearchableSelects, 'searchable select');
  safe(markLabelRows, 'label rows');
  safe(labelizeCells, 'cell labels');
  safe(markKeyCells, 'key cells');
  safe(markInlineLabels, 'inline labels');
  safe(classifyContainers, 'containers');
  safe(fixWindowButtons, 'window buttons');
  safe(markListCells, 'list cells');
  safe(markExternalLinks, 'external links');
  safe(markPrimaryActions, 'primary actions');
  safe(wireFilters, 'filters');
  safe(syncBulk, 'bulk count');
  var flash = flashTake();
  if (flash) { safe(function () { toast(flash, /class="result err"/.test(flash)); }, 'flash'); }
})();
