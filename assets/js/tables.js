// =====================================================================
// Generic table enhancer — applied automatically to every .data-table.
// Adds: client-side search (unless the table opts out via
// data-has-server-search="true"), click-to-sort columns, and pagination.
// No markup changes needed in individual pages beyond the existing
// .data-table structure.
// =====================================================================

(function () {
  const PAGE_SIZE = 12;

  function debounce(fn, delay) {
    let timer = null;
    return function (...args) {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), delay);
    };
  }

  function initTable(table) {
    if (table.dataset.enhanced) return;
    table.dataset.enhanced = '1';

    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    const allRows = Array.from(tbody.querySelectorAll('tr'));
    if (allRows.length <= 1) return; // not worth enhancing a near-empty table

    const headerCells = Array.from(table.querySelectorAll('thead th'));
    const hasServerSearch = table.dataset.hasServerSearch === 'true';

    const state = { query: '', sortCol: null, sortDir: 1, page: 1 };

    // --- Column type detection (for sorting) ---
    function detectType(colIndex) {
      const sample = allRows[0]?.children[colIndex];
      if (!sample) return 'text';
      const text = sample.textContent.trim();
      const stripped = text.replace(/[+,%\s]/g, '').replace(/XAF/g, '');
      return stripped !== '' && !isNaN(stripped) ? 'number' : 'text';
    }

    function cellValue(row, colIndex) {
      return row.children[colIndex] ? row.children[colIndex].textContent.trim() : '';
    }

    // --- Build toolbar (search box + result count) ---
    let toolbar = null;
    if (!hasServerSearch) {
      toolbar = document.createElement('div');
      toolbar.className = 'table-toolbar';
      toolbar.innerHTML = `
        <div class="table-search">
          ${iconSvg('search')}
          <input type="text" placeholder="Filter this list...">
        </div>
        <span class="table-count"></span>`;
      table.parentNode.insertBefore(toolbar, table);
      toolbar.querySelector('input').addEventListener('input', debounce(function (e) {
        state.query = e.target.value.toLowerCase();
        state.page = 1;
        render();
      }, 150));
    }

    // --- Sortable headers ---
    headerCells.forEach((th, colIndex) => {
      if (th.textContent.trim() === '') return; // skip empty "actions" column headers
      th.classList.add('sortable');
      th.innerHTML = th.textContent + '<span class="sort-arrow">&#9662;</span>';
      th.addEventListener('click', () => {
        if (state.sortCol === colIndex) {
          state.sortDir *= -1;
        } else {
          state.sortCol = colIndex;
          state.sortDir = 1;
        }
        headerCells.forEach((h) => h.classList.remove('sort-asc', 'sort-desc'));
        th.classList.add(state.sortDir === 1 ? 'sort-asc' : 'sort-desc');
        render();
      });
    });

    // --- Pagination footer ---
    const pager = document.createElement('div');
    pager.className = 'table-pagination';
    table.parentNode.insertBefore(pager, table.nextSibling);

    function render() {
      // Filter
      let rows = allRows;
      if (state.query) {
        rows = rows.filter((r) => r.textContent.toLowerCase().includes(state.query));
      }

      // Sort
      if (state.sortCol !== null) {
        const type = detectType(state.sortCol);
        rows = rows.slice().sort((a, b) => {
          let va = cellValue(a, state.sortCol), vb = cellValue(b, state.sortCol);
          if (type === 'number') {
            va = parseFloat(va.replace(/[^0-9.-]/g, '')) || 0;
            vb = parseFloat(vb.replace(/[^0-9.-]/g, '')) || 0;
            return (va - vb) * state.sortDir;
          }
          return va.localeCompare(vb) * state.sortDir;
        });
      }

      // Paginate
      const totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
      state.page = Math.min(state.page, totalPages);
      const start = (state.page - 1) * PAGE_SIZE;
      const pageRows = rows.slice(start, start + PAGE_SIZE);

      // Render rows: hide all, then re-append visible ones in sorted order
      allRows.forEach((r) => (r.style.display = 'none'));
      pageRows.forEach((r) => { r.style.display = ''; tbody.appendChild(r); });

      if (toolbar) {
        toolbar.querySelector('.table-count').textContent =
          rows.length === allRows.length ? `${rows.length} total` : `${rows.length} of ${allRows.length}`;
      }

      renderPager(totalPages, rows.length);
    }

    function renderPager(totalPages, filteredCount) {
      if (filteredCount === 0) {
        pager.innerHTML = '<span class="table-count">No matching rows</span>';
        return;
      }
      if (totalPages <= 1) {
        pager.innerHTML = `<span class="table-count">Showing all ${filteredCount}</span>`;
        return;
      }
      const start = (state.page - 1) * PAGE_SIZE + 1;
      const end = Math.min(state.page * PAGE_SIZE, filteredCount);

      let btns = '';
      const maxBtns = 5;
      let from = Math.max(1, state.page - 2);
      let to = Math.min(totalPages, from + maxBtns - 1);
      from = Math.max(1, to - maxBtns + 1);

      btns += `<button class="page-btn" data-page="prev" ${state.page === 1 ? 'disabled' : ''}>&lsaquo;</button>`;
      for (let p = from; p <= to; p++) {
        btns += `<button class="page-btn ${p === state.page ? 'active' : ''}" data-page="${p}">${p}</button>`;
      }
      btns += `<button class="page-btn" data-page="next" ${state.page === totalPages ? 'disabled' : ''}>&rsaquo;</button>`;

      pager.innerHTML = `<span class="table-count">Showing ${start}&ndash;${end} of ${filteredCount}</span><div class="page-btns">${btns}</div>`;
      pager.querySelectorAll('.page-btn').forEach((b) => {
        b.addEventListener('click', () => {
          const p = b.dataset.page;
          if (p === 'prev') state.page = Math.max(1, state.page - 1);
          else if (p === 'next') state.page = Math.min(totalPages, state.page + 1);
          else state.page = parseInt(p, 10);
          render();
        });
      });
    }

    render();
  }

  function iconSvg(name) {
    const paths = { search: '<circle cx="10" cy="10" r="6.5"/><path d="M19 19l-4.3-4.3"/>' };
    return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + (paths[name] || '') + '</svg>';
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.data-table').forEach(initTable);
  });
})();
