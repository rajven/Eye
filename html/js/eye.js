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
            typeahead: 'Type to filter', nomatch: 'Nothing found' };
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

  // blockForm('formAuthApply') keeps the button from firing twice
  window.blockForm = function (formId) {
    var form = document.getElementById(formId);
    if (!form) return;
    $$('input[type=submit], button[type=submit]', form).forEach(function (b) {
      b.disabled = true;
      var txt = (b.value !== undefined) ? b.value : b.textContent;
      b.dataset.old = txt;
      if (b.value !== undefined) { b.value = L.busy; } else { b.textContent = L.busy; }
    });
  };

  window.undoBlockForm = function (formId) {
    var form = document.getElementById(formId);
    if (!form) return;
    $$('input[type=submit], button[type=submit]', form).forEach(function (b) {
      b.disabled = false;
      if (b.dataset.old) { if (b.value !== undefined) { b.value = b.dataset.old; } else { b.textContent = b.dataset.old; } }
    });
  };

  /* 2. Theme and density */

  var root = document.documentElement;

  // density cycle, the choice is kept in localStorage
  var DENS = ['compact', 'normal', 'roomy'];
  var DENS_LABEL = { compact: L.dense, normal: L.normal, roomy: L.roomy };
  function setDens(mode) {
    if (mode === 'normal') { root.removeAttribute('data-eye-dens'); }
    else { root.setAttribute('data-eye-dens', mode); }
    try { localStorage.setItem('eye.dens', mode); } catch (e) {}
    $$('[data-eye-dens-btn]').forEach(function (b) { b.textContent = DENS_LABEL[mode]; });
  }

  // theme: auto (follows the system) / light / dark - an attribute only
  var THEME_LABEL = { auto: L.theme, light: L.themeL, dark: L.themeD };
  function setTheme(mode) {
    if (mode === 'auto') { root.removeAttribute('data-eye'); }
    else { root.setAttribute('data-eye', mode); }
    try { localStorage.setItem('eye.theme', mode); } catch (e) {}
    $$('[data-eye-theme-btn]').forEach(function (b) { b.textContent = THEME_LABEL[mode]; });
  }

  function addControls() {
    var foot = $('#eye-rail .rail-foot');
    if (!foot) return;
    var dens = foot.querySelector('[data-eye-dens-btn]');
    if (dens && !dens.dataset.eyeBound) {
      dens.dataset.eyeBound = '1';
      dens.addEventListener('click', function () {
        var cur = root.getAttribute('data-eye-dens') || 'compact';
        setDens(DENS[(DENS.indexOf(cur) + 1) % DENS.length]);
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

  var ACTIONS = {
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
      if (!form || !form.dataset.eyeWired) { /* noop, wired below */ }
      if (!form) return;
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

  function esIndex(sel, text) {
    var t = (text || '').toLowerCase();
    return Array.prototype.map.call(sel.options, function (o) {
      return { v: o.value, t: o.textContent.trim(), d: o.disabled };
    }).filter(function (o) {
      return !t || o.t.toLowerCase().indexOf(t) > -1 || o.v.toLowerCase().indexOf(t) > -1;
    });
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

    var shown = [];          // options that pass the filter
    var act = -1;            // highlighted row

    function paint() {
      btn.textContent = esSelectedText(sel) || '—';
      btn.title = btn.textContent;
      btn.disabled = sel.disabled;
      wrap.classList.toggle('es-dis', sel.disabled);
    }

    function rows() {
      shown = esIndex(sel, q.value);
      list.innerHTML = '';
      shown.forEach(function (o, i) {
        var li = document.createElement('li');
        li.className = 'es-opt' + (o.v === sel.value ? ' on' : '');
        li.setAttribute('role', 'option');
        li.id = id + '-o' + i;
        li.textContent = o.t || o.v;
        if (o.d) li.classList.add('dis');
        li.addEventListener('mousedown', function (ev) { ev.preventDefault(); pick(i); });
        list.appendChild(li);
      });
      if (!shown.length) {
        var empty = document.createElement('li');
        empty.className = 'es-opt es-none';
        empty.textContent = L.nomatch;
        list.appendChild(empty);
      }
      mark(shown.length ? 0 : -1);
    }

    function mark(i) {
      $$('.es-opt', list).forEach(function (el, n) { el.classList.toggle('act', n === i); });
      act = i;
      if (i > -1 && shown[i]) btn.setAttribute('aria-activedescendant', id + '-o' + i);
      else btn.removeAttribute('aria-activedescendant');
    }

    function pick(i) {
      if (i < 0 || !shown[i] || shown[i].d) return;
      sel.value = shown[i].v;
      paint();
      close();
      sel.dispatchEvent(new Event('change', { bubbles: true }));   // auto-apply filters and inline onchange keep firing
    }

    function open() {
      if (sel.disabled || !pop.hidden) return;
      pop.hidden = false; q.value = ''; rows();
      wrap.classList.add('open');
      btn.setAttribute('aria-expanded', 'true');
      document.addEventListener('mousedown', outside, true);
      q.focus();
    }

    function close() {
      if (pop.hidden) return;
      pop.hidden = true;
      wrap.classList.remove('open');
      btn.setAttribute('aria-expanded', 'false');
      document.removeEventListener('mousedown', outside, true);
    }

    function outside(ev) { if (!wrap.contains(ev.target)) close(); }

    btn.addEventListener('click', function () { pop.hidden ? open() : close(); });
    sel.addEventListener('focus', open);              // clicking <label for="..."> lands here too
    q.addEventListener('input', rows);
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
    var savedDens = localStorage.getItem('eye.dens');
    setDens(savedDens || 'compact');
    /* the value was already applied by the inline script in inc/header.php; here the button captions are only synchronised with the actual state */
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
