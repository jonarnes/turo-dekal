(function () {
  const root = document.getElementById('config-root');
  if (!root) return;

  const uploadUrl = root.dataset.uploadUrl;
  const deleteUrl = root.dataset.deleteUrl;
  const saveUrl = root.dataset.saveUrl;
  const csrf = root.dataset.csrf;
  const urlBase = root.dataset.urlBase || '';

  const dragState = {
    card: null,
    indicator: null,
  };

  function findByToken(token) {
    if (!token) return null;
    if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
      return root.querySelector('.asset-card[data-token="' + CSS.escape(token) + '"]');
    }
    return root.querySelector('.asset-card[data-token="' + token.replace(/"/g, '\\"') + '"]');
  }

  function collectLayout() {
    const items = [];
    const columnLayouts = { 1: [], 2: [], 3: [] };
    root.querySelectorAll('.preview-col').forEach(function (zone) {
      const col = parseInt(zone.dataset.column, 10);
      zone.querySelectorAll('.preview-images .asset-card').forEach(function (card, idx) {
        const token = card.dataset.token || '';
        if (token) {
          columnLayouts[col].push(token);
        }
        const id = parseInt(card.dataset.id, 10);
        if (id) {
          items.push({ id: id, column_index: col, sort_order: idx });
        }
      });
    });
    return { items: items, columnLayouts: columnLayouts };
  }

  function collectColumnTexts() {
    const out = {};
    root.querySelectorAll('.column-text-input').forEach(function (input) {
      const col = input.dataset.columnText;
      if (col) {
        out[col] = input.value || '';
      }
    });
    return out;
  }

  function previewImageUrl(assetId) {
    const base = (urlBase || '').replace(/\/+$/, '');
    const path = 'index.php?route=layout_image&id=' + encodeURIComponent(String(assetId));
    return base ? base + '/' + path : '/' + path;
  }

  let saveTimer = null;
  function scheduleSave() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveLayout, 400);
  }

  function saveLayout() {
    const layout = collectLayout();
    const columnTexts = collectColumnTexts();
    fetch(saveUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        csrf_token: csrf,
        items: layout.items,
        column_layouts: layout.columnLayouts,
        column_texts: columnTexts,
      }),
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data.ok) {
          console.warn('Lagring feilet', data);
        }
      })
      .catch(function (e) {
        console.warn(e);
      });
  }

  root.addEventListener('input', function (e) {
    if (e.target.closest('.column-text-input')) {
      scheduleSave();
    }
  });

  function getListColumnNumber(list) {
    const v = list.dataset.previewImages;
    return v ? parseInt(v, 10) : NaN;
  }

  function syncTextareaColumnAfterMove(card, columnNumber) {
    const ta = card.querySelector('.column-text-input');
    if (!ta) return;
    const col = String(columnNumber);
    ta.dataset.columnText = col;
    ta.id = 'column-text-' + col;
    const lbl = card.querySelector('label.preview-text-label');
    if (lbl) {
      lbl.setAttribute('for', ta.id);
    }
  }

  function removeDropIndicator() {
    if (dragState.indicator && dragState.indicator.parentNode) {
      dragState.indicator.parentNode.removeChild(dragState.indicator);
    }
    dragState.indicator = null;
    root.querySelectorAll('.preview-images.is-drop-target').forEach(function (el) {
      el.classList.remove('is-drop-target');
    });
  }

  function ensureIndicator() {
    if (!dragState.indicator) {
      const el = document.createElement('div');
      el.className = 'drop-indicator';
      el.setAttribute('aria-hidden', 'true');
      dragState.indicator = el;
    }
    return dragState.indicator;
  }

  /** Finn første søsken-kort som ligger under musepekeren (for innsetting over). */
  function getDragAfterElement(list, y) {
    const cards = Array.from(list.querySelectorAll(':scope > .asset-card')).filter(function (c) {
      return !c.classList.contains('is-dragging');
    });
    let closest = { offset: Number.NEGATIVE_INFINITY, element: null };
    cards.forEach(function (child) {
      const box = child.getBoundingClientRect();
      const offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) {
        closest = { offset: offset, element: child };
      }
    });
    return closest.element;
  }

  function positionIndicator(list, clientY) {
    if (!dragState.card) return;
    const colNum = getListColumnNumber(list);
    const token = dragState.card.dataset.token || '';
    if (token === 'qr' && colNum !== 2) {
      removeDropIndicator();
      return;
    }
    list.classList.add('is-drop-target');
    const afterEl = getDragAfterElement(list, clientY);
    const ind = ensureIndicator();
    if (afterEl == null) {
      list.appendChild(ind);
    } else {
      list.insertBefore(ind, afterEl);
    }
  }

  root.addEventListener('dragstart', function (e) {
    const handle = e.target.closest('.drag-handle');
    if (!handle) return;
    const card = handle.closest('.asset-card');
    if (!card || !root.contains(card)) return;
    dragState.card = card;
    card.classList.add('is-dragging');
    e.dataTransfer.setData('text/token', String(card.dataset.token || ''));
    e.dataTransfer.setData('text/plain', String(card.dataset.id || ''));
    e.dataTransfer.effectAllowed = 'move';
    try {
      const rect = card.getBoundingClientRect();
      const ox = e.clientX - rect.left;
      const oy = e.clientY - rect.top;
      e.dataTransfer.setDragImage(
        card,
        Math.max(8, Math.min(ox, rect.width - 8)),
        Math.max(8, Math.min(oy, rect.height - 8))
      );
    } catch (err) {
      void err;
    }
  });

  root.addEventListener('dragover', function (e) {
    const list = e.target.closest('.preview-images');
    if (!list || !dragState.card) return;
    const colNum = getListColumnNumber(list);
    const token = dragState.card.dataset.token || '';
    if (token === 'qr' && colNum !== 2) {
      e.dataTransfer.dropEffect = 'none';
      return;
    }
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    positionIndicator(list, e.clientY);
  });

  root.addEventListener('dragleave', function (e) {
    const list = e.target.closest('.preview-images');
    if (!list || !dragState.card) return;
    if (!list.contains(e.relatedTarget)) {
      list.classList.remove('is-drop-target');
      if (dragState.indicator && dragState.indicator.parentNode === list) {
        list.removeChild(dragState.indicator);
      }
    }
  });

  root.addEventListener('drop', function (e) {
    const list = e.target.closest('.preview-images');
    if (!list || !dragState.card) return;
    e.preventDefault();
    const card = dragState.card;
    const colNum = getListColumnNumber(list);
    const token = card.dataset.token || '';
    if (token === 'qr' && colNum !== 2) {
      removeDropIndicator();
      return;
    }
    const ind = dragState.indicator;
    if (ind && ind.parentNode === list) {
      list.insertBefore(card, ind);
      ind.remove();
    } else {
      list.appendChild(card);
    }
    dragState.indicator = null;
    list.classList.remove('is-drop-target');
    card.classList.remove('is-dragging');
    dragState.card = null;
    syncTextareaColumnAfterMove(card, colNum);
    scheduleSave();
  });

  root.addEventListener('dragend', function (e) {
    const handle = e.target.closest('.drag-handle');
    if (!handle) return;
    const card = handle.closest('.asset-card');
    if (card) {
      card.classList.remove('is-dragging');
    }
    removeDropIndicator();
    dragState.card = null;
  });

  function createAssetCard(asset) {
    const card = document.createElement('div');
    card.className = 'asset-card preview-card';
    card.draggable = false;
    card.dataset.id = String(asset.id);
    card.dataset.token = 'img:' + String(asset.id);
    card.dataset.storedPath = asset.stored_path || '';
    card.innerHTML =
      '<div class="preview-card-head">' +
      '<button type="button" class="drag-handle" draggable="true" aria-label="Dra for å flytte">⋮⋮</button>' +
      '</div>' +
      '<img class="preview-image" draggable="false" alt="">' +
      '<div class="asset-card-meta">' +
      '<span class="name"></span>' +
      '<button type="button" class="btn small danger btn-remove" data-id="' +
      asset.id +
      '">Slett</button>' +
      '</div>';
    const img = card.querySelector('.preview-image');
    img.src = previewImageUrl(asset.id);
    card.querySelector('.name').textContent = asset.filename || 'bilde';
    wireCard(card);
    return card;
  }

  function handleUploadInput(input) {
    if (!input.files || !input.files[0]) return;
    const fd = new FormData();
    fd.append('csrf_token', csrf);
    fd.append('image', input.files[0]);
    fd.append('column_index', input.dataset.uploadColumn || '1');
    fetch(uploadUrl, { method: 'POST', body: fd })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data.ok) {
          alert(data.error || 'Opplasting feilet');
          return;
        }
        const a = data.asset;
        const col = root.querySelector('.preview-col[data-column="' + a.column_index + '"] .preview-images');
        if (!col) return;
        const card = createAssetCard(a);
        col.appendChild(card);
        scheduleSave();
      })
      .catch(function () {
        alert('Nettverksfeil');
      });
    input.value = '';
  }

  root.querySelectorAll('.inline-image-input').forEach(function (input) {
    input.addEventListener('change', function () {
      handleUploadInput(input);
    });
  });

  function wireCard(card) {
    const rm = card.querySelector('.btn-remove');
    if (rm) {
      rm.addEventListener('click', function () {
        const id = rm.dataset.id;
        const fd = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('id', id);
        fetch(deleteUrl, { method: 'POST', body: fd })
          .then(function (r) {
            return r.json();
          })
          .then(function (data) {
            if (data.ok) {
              card.remove();
              scheduleSave();
            } else {
              alert(data.error || 'Sletting feilet');
            }
          });
      });
    }
  }

  root.querySelectorAll('.asset-card').forEach(wireCard);
})();
