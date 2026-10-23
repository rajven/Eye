/* =============================================================================
   Eye — поведение интерфейса (eye.js)
   Заменяет: remodal.min.js + remodal-auth.js + remodal-user.js +
             remodal-devices.js + select-auto.js + jQuery-инициализацию Select2
   и 60 строк однотипных слушателей в admin/iplist/index.php.

   NOTHING HERE TOUCHES THE SERVER API: те же формы, те же имена полей, те же
   /utils/*.php. Изменена только оболочка диалогов (<dialog>) и способ отправки
   (fetch вместо $.ajax).
   ========================================================================== */
(function () {
  'use strict';

  var body = document.body;
  // подписи приходят из PHP (inc/header.php, data-eye-i18n), здесь — фолбэки
  var L = { dense: 'Плотно', normal: 'Обычно', roomy: 'Просторно', theme: 'Тема: авто',
            themeL: 'Тема: светлая', themeD: 'Тема: тёмная', sel: 'выбрано', clear: 'Снять',
            failed: 'Не выполнено:', busy: 'Обрабатывается…',
            typeahead: 'Начните вводить для поиска', nomatch: 'Ничего не найдено' };
  try { Object.assign(L, JSON.parse(body.getAttribute('data-eye-i18n') || '{}')); } catch (e) {}

  var $  = function (sel, root) { return (root || body).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  /* ---------- 1. то, что страницы вызывают инлайном из HTML ---------- */

  // onclick="checkAll(this.checked)" — выделяет все строки списка
  window.checkAll = function (checked) {
    var form = document.def || $('#def');
    if (!form) return;
    $$('input[type=checkbox]', form).forEach(function (cb) { cb.checked = checked; });
    syncBulk();
  };

  // onsubmit="blockForm('formAuthApply')" — кнопка не давит себя дважды
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

  /* ---------- 2. тема и плотность --------------------------------------- */

  var root = document.documentElement;

  // плотность: циклается, значение живёт в localStorage
  var DENS = ['compact', 'normal', 'roomy'];
  var DENS_LABEL = { compact: L.dense, normal: L.normal, roomy: L.roomy };
  function setDens(mode) {
    if (mode === 'normal') { root.removeAttribute('data-eye-dens'); }
    else { root.setAttribute('data-eye-dens', mode); }
    try { localStorage.setItem('eye.dens', mode); } catch (e) {}
    $$('[data-eye-dens-btn]').forEach(function (b) { b.textContent = DENS_LABEL[mode]; });
  }

  // тема: auto (как в системе) / light / dark — тоже только атрибут
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

  /* ---------- 3. диалоги --------------------------------------------------
     страницы печатают <a class="mainButton" href="#modal"> и <dialog id="modal">
     без JS ссылка просто уйдёт якорем, с JS — открывает нативный диалог.     */

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
      // кнопка закрытия и клик по затемнению
      $$('[data-close]', dlg).forEach(function (b) {
        b.addEventListener('click', function () { dlg.close(); });
      });
      dlg.addEventListener('click', function (ev) { if (ev.target === dlg) dlg.close(); });
      dlg.addEventListener('close', function () {
        var f = dlg.querySelector('form'); if (f) undoBlockForm(f.id);
      });
    });
  }

  /* ---------- 4. массовые операции: тех же эндпоинтов, что раньше -------- */

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
        var list = document.getElementById('def');           // форма со списком (fid[])
        var data = new FormData();
        if (list) {
          new FormData(list).forEach(function (v, k) { data.append(k, v); });
        }
        new FormData(form).forEach(function (v, k) { data.append(k, v); });
        blockForm(formId);

        fetch(cfg.url, { method: 'POST', body: data, credentials: 'same-origin' })
          .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.blob();
          })
          .then(function (blob) {
            if (cfg.download) {
              var a = document.createElement('a');
              a.href = URL.createObjectURL(blob);
              a.download = cfg.download;
              document.body.appendChild(a); a.click(); a.remove();
            }
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

  // обновление списка: как и раньше — повторно «показать» с текущим фильтром
  function refreshList() {
    var btn = document.getElementById('btn_filter');
    if (btn) { setTimeout(function () { btn.click(); }, 350); return; }
    setTimeout(function () { location.reload(); }, 350);
  }

  /* ---------- 5. чекбокс «применить это поле» в диалогах ------------------
     тот же паттерн e_<field>/a_<field>, что был в прежних JS-файлах — теперь он
     ещё и визуально гасит неприменяемые строки.                            */

  function wireMassFields() {
    $$('dialog input.putField[type=checkbox]').forEach(function (cb) {
      var row = cb.closest('tr') || cb.closest('.mrow');
      if (!row) return;
      // подписи в следующей ячейке делаем кликабельными (name/id не трогаем)
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

  /* ---------- 6. плашка «N выбрано» --------------------------------------
     кнопки берёт у страницы (она их и печати: mainButton/delButton/export),
     так что второй комплект действий не появляется.                         */

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

  /* ---------- 7. фильтры: одна делегированная обработка вместо 7 копий ---- */

  function wireFilters() {
    $$('#cont form').forEach(function (form) {
      var btn = form.querySelector('#btn_filter, [name=btn_filter]');
      if (!btn) return;
      form.addEventListener('change', function (ev) {
        var t = ev.target;
        if (t && (t.tagName === 'SELECT')) btn.click();
      });
    });
    // «/» — фокус в поиск, как в сетевых консолях
    document.addEventListener('keydown', function (ev) {
      if (ev.key !== '/' || /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) return;
      var q = $('#q, input[name=search_str]');
      if (q) { ev.preventDefault(); q.focus(); q.select(); }
    });
  }

  /* ---------- 7b. Searchable select вместо Select2 ------------------------
     Работает поверх обычных <select class="js-select-single">, которые печатают
     print_*() в inc/common.php. Нативный select остаётся в форме: имя, значение
     и отправка не меняются вообще. Без JS — обычный выпадающий список.
     Классы: .es (обёртка) .es-btn (видимая кнопка) .es-pop (панель) .es-q (фильтр)
     .es-list/.es-opt (варианты) .es-native (сам select).                        */

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
    // обёртка вставляется ровно на место select: родителя читаем ДО переноса узлов,
    // иначе insertBefore внутрь собственного потомка => DOMException (incorrect node tree)
    var host = sel.parentNode;
    if (!host) return;
    host.insertBefore(wrap, sel);
    wrap.appendChild(btn); wrap.appendChild(sel); wrap.appendChild(pop);

    var shown = [];          // текущий отфильтрованный список
    var act = -1;            // активная строка

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
      sel.dispatchEvent(new Event('change', { bubbles: true }));   // авто-фильтры и onchange работают
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
    sel.addEventListener('focus', open);              // клик по <label for="…"> тоже приводит сюда
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


  /* ---------- 7c. Подписи полей в таблицах-формах --------------------------
     edituser.php / editauth.php и др. печатают пару строк: сначала ячейки с
     текстом ("Логин", "В день", "Блокировка"), затем строка с полями ввода.
     CSS не умеет смотреть "в следующую строку", поэтому строка-подпись помечается
     классом .lblrow — разметку и состав POST это не затрагивает.            */

  function dataTables() {
    // table.data + голые <table> внутри полости: их печатают auto_rules.php,
    // building.php, ipcam.php и фильтры старых страниц
    var seen = [], out = [];
    $$('table.data, #cont table').forEach(function (tbl) {
      if (seen.indexOf(tbl) > -1) return; seen.push(tbl); out.push(tbl);
    });
    return out;
  }

  function markLabelRows() {
    dataTables().forEach(function (tbl) {
      if (tbl.closest('dialog')) return;              // в диалогах другие подписи
      // в списках данных (чекбоксы выделени) первая строка — шапка колонок, её
      // делает CSS; в таблицах-формах (edituser/editauth) первая строка — как раз
      // подписи полей, её помечать надо
      // признак списка данных — чекбокс/поле с массивным именем (fid[], f_auth_id[],
      // d_port_index[]); одиночный checkbox «Только PTR» в форме списком не считается
      var isList = !!tbl.querySelector('[name*="\\[\\]"]');
      var rows = $$(':scope > tbody > tr, :scope > tr', tbl);
      for (var i = 0; i < rows.length - 1; i++) {
        var r = rows[i], next = rows[i + 1];
        var hasCtl = function (el) { return !!el.querySelector('input,select,textarea,button'); };
        var textOnly = Array.prototype.every.call(r.children, function (td) {
          return td.children.length === 0 || !hasCtl(td);
        });
        if (isList && r === rows[0]) continue;               // шапка колонок — не подпись поля
        // пустая строка (разделитель групп) подписью не считается
        if (!r.textContent.trim()) continue;
        if (textOnly && !hasCtl(r) && hasCtl(next)) r.classList.add('lblrow');
      }
    });
  }


  /* ---------- 7d. Подпись и поле в одной ячейке ----------------------------
     edituser.php печатает <td>Новый адрес доступа IP:&nbsp<input></td>.
     Ведущий текст (короткий, обычно с двоеточием) заворачивается в span,
     чтобы отличаться от значения. Длинный текст считается данными, не подписью. */

  function labelizeCells() {
    $$('table.data td, #cont table td').forEach(function (td) {
      if (td.dataset.eyeLabeled) return;
      if (!td.querySelector('input, select, textarea')) return;   // только ячейки с полем
      var n = td.firstChild;
      if (!n || n.nodeType !== 3) return;                          // текст должен идти первым
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

  /* ---------- 7e. Ячейки-ключи («Кем создан:», «Создан:») ------------------
     editauth.php и страницы отчётов печатают <td>Ключ:</td><td>значение</td> —
     подписи нет в отдельной строке, опознаём по ведущему тексту с двоеточием. */

  function markKeyCells() {
    $$('table.data td, #cont table td').forEach(function (td) {
      if (td.dataset.eyeKeyed) return;                       // уже оформлена
      if (td.querySelector('input, select, textarea, button, .cell-lbl')) return;
      if (td.children.length > 1) return;
      if (td.parentElement.children.length === 1) return;   // заголовок раздела: его делает CSS
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
      td.dataset.eyeKeyed = '1';                             // флаг ставим только при успехе
    });
  }


  /* ---------- 7f. Подпись слева от поля в той же строке --------------------
     Встречается только в формах (в списках строк с чекбоксами.name таких нет,
     поэтому признаком формы служит отсутствие input[type=checkbox][name]). */

  function markInlineLabels() {
    dataTables().forEach(function (tbl) {
      if (tbl.closest('dialog')) return;
      // списки данных помечаются чекбоксами-массивами (fid[], f_auth_id[]) —
      // их подписывать нельзя: там текст является данными
      if (tbl.querySelector('input[name*="\\[\\]"]')) return;
      $$(':scope > tbody > tr, :scope > tr', tbl).forEach(function (r) {
        if (r.querySelector('input[type=checkbox]')) return;      // строка с выделением — не форма
        var cells = Array.prototype.slice.call(r.children);
        for (var i = 0; i < cells.length - 1; i++) {
          var td = cells[i], nxt = cells[i + 1];
          if (td.querySelector('*') || td.dataset.eyeKeyed) continue;   // только чисто текстовая
          var txt = td.textContent.trim();
          if (!txt || txt.length > 40 || /:$/.test(txt)) continue;      // «Ключ:» помечен ранее
          if (/\d/.test(txt)) continue;                                  // «Count: 1» — данные, не подпись
          if (!hasField(nxt)) continue;                                  // подпись ведёт к полю ввода
          var span = document.createElement('span');
          span.className = 'cell-key';
          span.textContent = txt;
          td.textContent = '';
          td.appendChild(span);
          td.dataset.eyeKeyed = '1';
        }
      });
    });
    // только настоящее поле: кнопка «-MAC» после «Count: 1» подписью не считается
    function hasField(td) {
      return !!td && !!td.querySelector('input:not([type=submit]):not([type=button]):not([type=hidden]), select, textarea');
    }
  }



  /* ---------- 7g. Полосы переходов vs контейнеры контента ------------------
     #submenu/#subsubmenu/#contsubmenu используются и как табы, и как обычные
     контейнеры (на 14 страницах внутри #contsubmenu лежат формы и таблицы).
     Флекс-раскладку в CSS вешаем только на класс .tabs — его проставляет здесь. */

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

  /* ---------- 7h. Кнопки «открыть окно» внутри таблиц ----------------------
     <button onclick="window.open(...); return false;">15</button> без type и без
     name: браузер считает его submit, и клик по счётчику MAC помимо окна
     отправлял форму списка портов. Чиним тип; кнопки с name не трогаем — это
     настоящие «Сохранить»/«POE on». */

  function fixWindowButtons() {
    $$('table button:not([type]):not([name])').forEach(function (b) {
      if (/window\.open/.test(b.getAttribute('onclick') || '')) { b.type = 'button'; }
    });
  }


  /* ---------- 7i. Ячейки-списки --------------------------------------------
     switchstatus/switchport печатают в одной ячейке список устройств через <br>,
     а в соседней — список комментариев: строки соответствуют друг другу.
     В узкой колонке браузер начинал переносить строки и соответствие пропадало.
     Помечаем такие ячейки классом .nl (не переносить + сверху), CSS доделывает. */

  function markListCells() {
    $$('table.data td').forEach(function (td) {
      if (td.dataset.eyeList) return;
      if (td.querySelectorAll('br').length < 2) return;               // 2+ переноса = список
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


  /* ---------- 7j. Ссылки, открывающие окно -------------------------------
     switchport/switchstatus/mactable зовут window.open() из onclick; таким
     даём класс .ext — CSS дорисует ↗, чтобы «откроется отдельно» было видно. */

  function markExternalLinks() {
    $$('a[onclick*="window.open"]').forEach(function (a) { a.classList.add('ext'); });
  }


  /* ---------- 7k. Одна акцентная кнопка на форму ----------------------------
     «Сохранить» нужно отделять от служебных (Переместить / Обновить / Удалить).
     В разметке Eye имена кнопок постоянны, поэтому выбираем кандидатов по имени,
     ранжируем (edit* → save_ipset → save → s_save) и помечаем .pri одного на форму.
     Явный class="pri" в разметке перекрывает эвристику. Диалоги и кнопку
     «Показать» не трогаем: у них своя логика акцента. */

  var PRI_RANK = [/^edit/i, /^save_ipset$/, /^save$/, /^s_save$/];

  function markPrimaryActions() {
    $$('form').forEach(function (f) {
      if (f.closest('dialog')) return;
      var found = $$(':is(input[type=submit], button[type=submit], button:not([type]))', f).filter(function (el) {
        if (el.closest('dialog')) return false;
        if (el.id === 'btn_filter' || el.name === 'btn_filter') return false;
        if (el.classList.contains('danger') || el.classList.contains('x')) return false;
        if (/\[\]$/.test(el.name || '')) return false;          // постночные действия списков
        var rk = rank(el);
        return rk > -1;
      });
      var explicit = $$('.pri', f).filter(function (el) { return !el.closest('dialog'); });
      if (explicit.length) return;                                // разметка уже всё решила
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

  /* ---------- старт ------------------------------------------------------- */

  // каждый шаг инициализации по отдельности: ошибка в одном не глушит механизм
  // на остальных страницах (в console.warn — что именно не отработало)
  function safe(fn, what) {
    try { fn(); }
    catch (e) { if (window.console && console.warn) console.warn('[eye] ' + what + ': ' + e.message); }
  }

  safe(addControls, 'controls');
  try {
    var savedDens = localStorage.getItem('eye.dens');
    setDens(savedDens || 'compact');
    /* значение уже применено инлайновым скриптом в inc/header.php — здесь только
       синхронизируем подписи кнопок с фактическим состоянием */
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
})();
