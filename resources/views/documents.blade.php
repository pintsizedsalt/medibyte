@extends('layouts.app')

@section('title', 'Documents · MediByte')

@section('content')
  <div class="page-heading tone-blue">
    <div class="heading-main">
      <a class="back-link" href="/records" aria-label="Back to all records">
        <svg class="icon"><use href="#i-chevron-left"/></svg>
      </a>
      <span class="heading-ic" aria-hidden="true"><svg class="icon"><use href="#i-file"/></svg></span>
      <div>
        <p class="eyebrow">Health records</p>
        <h2>Documents</h2>
        <p>Store prescriptions, lab results, and medical certificates in your private record.</p>
      </div>
    </div>
  </div>

  <p id="msg" class="form-message" role="alert"></p>

  <section class="card doc-library">
    <div class="doc-toolbar">
      <label class="doc-search">
        <span class="sr-only">Search documents</span>
        <input id="doc-search" type="search" placeholder="Search by title, file name, or notes" oninput="setDocSearch(this.value)" autocomplete="off">
      </label>
      <div class="doc-view-toggle" role="group" aria-label="Change layout">
        <button type="button" id="view-grid" class="is-active" onclick="setDocView('grid')" aria-pressed="true">Grid</button>
        <button type="button" id="view-list" onclick="setDocView('list')" aria-pressed="false">List</button>
      </div>
    </div>

    <div class="doc-filters" id="doc-filters" role="group" aria-label="Filter by type"></div>

    <div class="card-head doc-library-head">
      <h3>Your files</h3>
      <span class="history-count" id="doc-count">0</span>
    </div>

    <div id="documents-list">
      <p class="muted">Loading…</p>
    </div>
  </section>

  {{-- Drag-and-drop hint --}}
  <div id="doc-drop-overlay" class="doc-drop-overlay" hidden>
    <div>
      <svg class="icon"><use href="#i-file"/></svg>
      <strong>Drop to upload</strong>
      <span>PDF, JPG, or PNG up to 10 MB</span>
    </div>
  </div>

  <button type="button" id="doc-fab" class="fab" onclick="openUploadModal()" aria-label="Upload a file" title="Upload a file" hidden>
    <svg class="icon"><use href="#i-plus"/></svg>
  </button>

  {{-- Upload pop-up --}}
  <div id="doc-upload-modal" class="doc-preview-modal" hidden>
    <div class="doc-preview-backdrop" onclick="closeUploadModal()"></div>
    <div class="doc-preview-dialog doc-upload-dialog" role="dialog" aria-modal="true" aria-labelledby="doc-upload-title">
      <header class="doc-preview-header">
        <div>
          <p class="eyebrow">Upload</p>
          <h3 id="doc-upload-title">Add a document</h3>
        </div>
        <button type="button" class="icon-btn" onclick="closeUploadModal()" aria-label="Close upload">
          <svg class="icon"><use href="#i-close"/></svg>
        </button>
      </header>

      <form id="doc-form" class="doc-upload-body" onsubmit="event.preventDefault(); uploadDocument()">
        <div class="form-grid">
          <label class="field">
            <span>Document type</span>
            <select id="doc_type" required>
              <option value="prescription">Prescription</option>
              <option value="lab_result">Lab result</option>
              <option value="medical_certificate">Medical certificate</option>
              <option value="other">Other</option>
            </select>
          </label>

          <label class="field">
            <span>Title</span>
            <input id="doc_title" type="text" maxlength="150" placeholder="e.g. Blood test — October">
          </label>

          <div class="field span-full">
            <span>File</span>
            <input id="doc_file" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" hidden onchange="updateFileLabel()">
            <label class="document-drop" for="doc_file">
              <svg class="icon"><use href="#i-file"/></svg>
              <span>
                <strong id="doc-file-label">Choose a PDF, JPG, or PNG</strong>
                <small>Up to 10 MB (under 8 MB recommended). Your file stays on a private disk.</small>
              </span>
            </label>
            <p id="doc-file-error" class="field-error" role="alert" hidden></p>
          </div>

          <label class="field span-full">
            <span>Notes (optional)</span>
            <textarea id="doc_notes" rows="3" maxlength="1000" placeholder="Anything you want to remember about this document."></textarea>
          </label>
        </div>

        <p id="upload-msg" class="form-message" role="alert"></p>

        <div class="form-actions">
          <button type="button" class="btn btn-ghost" onclick="closeUploadModal()">Cancel</button>
          <button type="submit" id="doc-submit" class="btn btn-primary">
            <svg class="icon"><use href="#i-plus"/></svg>
            Upload document
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- Preview pop-up --}}
  <div id="doc-preview-modal" class="doc-preview-modal" hidden>
    <div class="doc-preview-backdrop" onclick="closeDocPreview()"></div>
    <div class="doc-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="doc-preview-title">
      <header class="doc-preview-header">
        <div>
          <p class="eyebrow" id="doc-preview-type">Document</p>
          <h3 id="doc-preview-title">Preview</h3>
        </div>
        <div class="doc-preview-actions">
          <button type="button" class="btn btn-ghost" id="doc-preview-download" onclick="">
            <svg class="icon"><use href="#i-arrow"/></svg>
            Download
          </button>
          <button type="button" class="icon-btn" onclick="closeDocPreview()" aria-label="Close preview">
            <svg class="icon"><use href="#i-close"/></svg>
          </button>
        </div>
      </header>
      <div class="doc-preview-body" id="doc-preview-body"></div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
let documentRows = [];
let docFilter = 'all';
let docSearch = '';
let docView = 'grid';
let docUploading = false;

try {
  docView = localStorage.getItem('medibyte.docView') === 'list' ? 'list' : 'grid';
} catch (e) {}

const DOCUMENT_TYPES = {
  prescription: 'Prescription',
  lab_result: 'Lab result',
  medical_certificate: 'Medical certificate',
  other: 'Other'
};

const DOCUMENT_FILTERS = [
  ['all', 'All'],
  ['prescription', 'Prescriptions'],
  ['lab_result', 'Lab results'],
  ['medical_certificate', 'Certificates'],
  ['other', 'Other']
];

function formatBytes(bytes) {
  const value = Number(bytes || 0);
  if (value < 1024) return value + ' B';
  if (value < 1024 * 1024) return (value / 1024).toFixed(1) + ' KB';
  return (value / (1024 * 1024)).toFixed(1) + ' MB';
}

function isImageMime(mime) {
  return typeof mime === 'string' && mime.startsWith('image/');
}

function isPdfMime(mime) {
  return mime === 'application/pdf' || (typeof mime === 'string' && mime.includes('pdf'));
}

function previewUrl(id) {
  return '/api/v1/documents/' + id + '/preview';
}

/* ---------- Upload pop-up ---------- */

function setFileError(message) {
  const el = $('doc-file-error');
  if (!el) return;
  if (message) {
    el.textContent = message;
    el.hidden = false;
  } else {
    el.textContent = '';
    el.hidden = true;
  }
}

function clearFileError() {
  setFileError('');
}

function updateFileLabel() {
  const file = $('doc_file').files[0];
  const label = $('doc-file-label');
  if (label) label.textContent = file ? file.name : 'Choose a PDF, JPG, or PNG';

  if (file && file.size > 10 * 1024 * 1024) {
    setFileError('The file must be 10 MB or smaller.');
  } else if (file && file.size > 8 * 1024 * 1024) {
    setFileError('This file is over 8 MB. The server may reject it — try a smaller file if upload fails.');
  } else {
    clearFileError();
  }
}

function setSelectedFile(file) {
  try {
    const transfer = new DataTransfer();
    transfer.items.add(file);
    $('doc_file').files = transfer.files;
  } catch (e) {
    return false;
  }
  updateFileLabel();
  return true;
}

function openUploadModal(file) {
  $('doc-form').reset();
  $('upload-msg').textContent = '';
  clearFileError();
  updateFileLabel();

  if (file) setSelectedFile(file);

  $('doc-upload-modal').hidden = false;
  document.body.classList.add('doc-preview-open');
  setTimeout(() => $(file ? 'doc_title' : 'doc_type')?.focus({ preventScroll: true }), 50);
}

function closeUploadModal() {
  if (docUploading) return;
  const modal = $('doc-upload-modal');
  if (modal) modal.hidden = true;
  if ($('doc-preview-modal')?.hidden !== false) {
    document.body.classList.remove('doc-preview-open');
  }
}

function uploadErrorMessage(result, file) {
  const raw = errText(result) || '';
  const lower = raw.toLowerCase();

  if (
    lower.includes('content-length') ||
    lower.includes('post content-length') ||
    lower.includes('exceeds the limit') ||
    lower.includes('upload_max_filesize') ||
    lower.includes('post_max_size') ||
    lower.includes('file is too large') ||
    (file && file.size > 8 * 1024 * 1024 && (!result || !result.errors))
  ) {
    return 'This file is too large for the server to accept right now. Please use a file under 8 MB.';
  }

  const plain = raw.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
  if (!plain || plain === 'Something went wrong. Please try again.') {
    return 'Upload failed. Please try a smaller file or try again.';
  }
  return plain;
}

async function uploadDocument() {
  if (docUploading) return;

  const file = $('doc_file').files[0];
  clearFileError();
  $('upload-msg').textContent = '';

  if (!file) {
    setFileError('Choose a file to upload.');
    return;
  }

  if (file.size > 10 * 1024 * 1024) {
    setFileError('The file must be 10 MB or smaller.');
    return;
  }

  if (!$('doc_title').value.trim()) {
    const base = file.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ').trim();
    if (base) $('doc_title').value = base.slice(0, 150);
  }

  const formData = new FormData();
  formData.append('type', $('doc_type').value);
  formData.append('title', $('doc_title').value.trim() || 'Untitled document');
  formData.append('notes', $('doc_notes').value.trim());
  formData.append('file', file);

  const submit = $('doc-submit');
  docUploading = true;
  submit.disabled = true;
  $('upload-msg').textContent = 'Uploading…';

  let result;
  try {
    result = await api('/documents', 'POST', formData);
  } finally {
    docUploading = false;
    submit.disabled = false;
  }

  if (result && result.id) {
    toast('Document uploaded.');
    closeUploadModal();
    $('doc-form').reset();
    updateFileLabel();
    await loadDocuments();
  } else {
    const message = uploadErrorMessage(result, file);
    if (
      message.toLowerCase().includes('too large') ||
      message.toLowerCase().includes('10 mb') ||
      message.toLowerCase().includes('8 mb')
    ) {
      setFileError(message);
      $('upload-msg').textContent = '';
    } else {
      $('upload-msg').textContent = message;
    }
    toast(message, 'error');
  }
}

/* ---------- Library: filters, search, layout ---------- */

function setDocFilter(value) {
  docFilter = value;
  renderDocuments();
}

function setDocSearch(value) {
  docSearch = String(value || '').trim().toLowerCase();
  renderDocuments();
}

function setDocView(view) {
  docView = view === 'list' ? 'list' : 'grid';
  try { localStorage.setItem('medibyte.docView', docView); } catch (e) {}
  renderDocuments();
}

function visibleDocuments() {
  return documentRows.filter(doc => {
    if (docFilter !== 'all' && doc.type !== docFilter) return false;
    if (!docSearch) return true;
    return [doc.title, doc.original_name, doc.notes, DOCUMENT_TYPES[doc.type]]
      .some(value => String(value || '').toLowerCase().includes(docSearch));
  });
}

function renderFilters() {
  const box = $('doc-filters');
  if (!box) return;

  box.hidden = documentRows.length === 0;
  box.innerHTML = DOCUMENT_FILTERS.map(([value, label]) => {
    const total = value === 'all'
      ? documentRows.length
      : documentRows.filter(doc => doc.type === value).length;
    const active = docFilter === value;
    return `<button type="button" class="doc-chip${active ? ' is-active' : ''}" aria-pressed="${active}" onclick="setDocFilter('${value}')">
      ${esc(label)} <span>${total}</span>
    </button>`;
  }).join('');
}

function docThumb(doc) {
  const id = Number(doc.id);
  if (isImageMime(doc.mime_type)) {
    return `<button type="button" class="doc-thumb" onclick="openDocPreview(${id})" aria-label="Preview ${esc(doc.title)}">
      <img src="${previewUrl(id)}" alt="" loading="lazy" onerror="this.parentElement.classList.add('doc-thumb-fallback')">
    </button>`;
  }
  if (isPdfMime(doc.mime_type)) {
    return `<button type="button" class="doc-thumb doc-thumb-pdf" onclick="openDocPreview(${id})" aria-label="Preview ${esc(doc.title)}">
      <svg class="icon"><use href="#i-file"/></svg>
      <span>PDF</span>
    </button>`;
  }
  return `<button type="button" class="doc-thumb doc-thumb-file" onclick="openDocPreview(${id})" aria-label="Preview ${esc(doc.title)}">
    <svg class="icon"><use href="#i-file"/></svg>
  </button>`;
}

function docActions(doc) {
  const id = Number(doc.id);
  return `<div class="record-actions">
    <button type="button" class="record-row-action" onclick="openDocPreview(${id})" aria-label="Preview document" title="Preview">
      <svg class="icon"><use href="#i-file"/></svg>
    </button>
    <button type="button" class="record-row-action" onclick="downloadDocument(${id})" aria-label="Download document" title="Download">
      <svg class="icon"><use href="#i-arrow"/></svg>
    </button>
    <button type="button" class="record-row-action danger" onclick="deleteDocument(${id})" aria-label="Delete document" title="Delete">
      <svg class="icon"><use href="#i-trash"/></svg>
    </button>
  </div>`;
}

function docCardPreview(doc) {
  const id = Number(doc.id);
  let inner;
  if (isImageMime(doc.mime_type)) {
    inner = `<img src="${previewUrl(id)}" alt="" loading="lazy" onerror="this.remove()">`;
  } else {
    inner = `<svg class="icon"><use href="#i-file"/></svg><span>${isPdfMime(doc.mime_type) ? 'PDF' : 'FILE'}</span>`;
  }
  return `<button type="button" class="doc-card-preview${isImageMime(doc.mime_type) ? '' : ' is-icon'}" onclick="openDocPreview(${id})" aria-label="Preview ${esc(doc.title)}">${inner}</button>`;
}

function renderDocuments() {
  const list = $('documents-list');
  const count = $('doc-count');
  if (count) count.textContent = documentRows.length;
  $('doc-fab')?.toggleAttribute('hidden', documentRows.length === 0);

  $('view-grid')?.classList.toggle('is-active', docView === 'grid');
  $('view-list')?.classList.toggle('is-active', docView === 'list');
  $('view-grid')?.setAttribute('aria-pressed', docView === 'grid');
  $('view-list')?.setAttribute('aria-pressed', docView === 'list');
  document.querySelector('.doc-toolbar')?.toggleAttribute('hidden', documentRows.length === 0);

  renderFilters();

  if (!documentRows.length) {
    list.innerHTML = `<div class="empty-state doc-empty">
      <span class="empty-orb"><svg class="icon"><use href="#i-file"/></svg></span>
      <h4>No file attached</h4>
      <p>Your prescriptions, lab results, and medical certificates will show up here.</p>
      <button type="button" class="btn btn-primary" onclick="openUploadModal()">
        <svg class="icon"><use href="#i-plus"/></svg>
        Upload a file
      </button>
    </div>`;
    return;
  }

  const rows = visibleDocuments();

  if (!rows.length) {
    list.innerHTML = `<div class="empty-state doc-empty">
      <span class="empty-orb"><svg class="icon"><use href="#i-file"/></svg></span>
      <h4>No matching documents</h4>
      <p>Try a different search or choose another type.</p>
    </div>`;
    return;
  }

  if (docView === 'list') {
    list.innerHTML = '<div class="record-list">' + rows.map(doc => `
      <div class="record document-row">
        ${docThumb(doc)}
        <div class="record-main">
          <strong>${esc(doc.title)} · ${esc(DOCUMENT_TYPES[doc.type] || 'Other')}</strong>
          <span class="record-meta">${esc(doc.original_name)} · ${formatBytes(doc.size)} · ${esc(dateLabel(doc.created_at))}</span>
          ${doc.notes ? `<span class="record-meta">${esc(doc.notes)}</span>` : ''}
        </div>
        ${docActions(doc)}
      </div>
    `).join('') + '</div>';
    return;
  }

  list.innerHTML = '<div class="doc-grid">' + rows.map(doc => `
    <article class="doc-card">
      ${docCardPreview(doc)}
      <div class="doc-card-info">
        <strong title="${esc(doc.title)}">${esc(doc.title)}</strong>
        <span class="doc-card-type">${esc(DOCUMENT_TYPES[doc.type] || 'Other')}</span>
        <span class="record-meta">${formatBytes(doc.size)} · ${esc(dateLabel(doc.created_at))}</span>
      </div>
      ${docActions(doc)}
    </article>
  `).join('') + '</div>';
}

async function loadDocuments() {
  const data = await api('/documents');
  documentRows = Array.isArray(data) ? data : [];
  renderDocuments();
}

/* ---------- Download, preview, delete ---------- */

async function downloadDocument(id) {
  const documentRecord = documentRows.find(item => Number(item.id) === Number(id));
  if (!documentRecord) return;

  const response = await fetch('/api/v1/documents/' + id + '/download', {
    headers: {
      Accept: '*/*',
      'X-Requested-With': 'XMLHttpRequest'
    },
    credentials: 'same-origin'
  });

  if (response.status === 401) {
    location.replace('/login');
    return;
  }

  if (!response.ok) {
    $('msg').textContent = 'Could not download this document. Please try again.';
    return;
  }

  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = documentRecord.original_name || 'document';
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

function openDocPreview(id) {
  const doc = documentRows.find(item => Number(item.id) === Number(id));
  if (!doc) return;

  const modal = $('doc-preview-modal');
  const body = $('doc-preview-body');
  const titleEl = $('doc-preview-title');
  const typeEl = $('doc-preview-type');
  const downloadBtn = $('doc-preview-download');

  titleEl.textContent = doc.title || doc.original_name || 'Document';
  typeEl.textContent = DOCUMENT_TYPES[doc.type] || 'Document';
  downloadBtn.onclick = () => downloadDocument(id);

  body.innerHTML = '';

  if (isImageMime(doc.mime_type)) {
    const img = document.createElement('img');
    img.src = previewUrl(id);
    img.alt = doc.title || 'Document preview';
    img.className = 'doc-preview-image';
    body.appendChild(img);
  } else if (isPdfMime(doc.mime_type)) {
    const frame = document.createElement('iframe');
    frame.src = previewUrl(id);
    frame.title = doc.title || 'PDF preview';
    frame.className = 'doc-preview-pdf';
    body.appendChild(frame);
  } else {
    body.innerHTML = `<div class="doc-preview-fallback">
      <svg class="icon"><use href="#i-file"/></svg>
      <p>Preview is not available for this file type.</p>
      <button type="button" class="btn btn-primary" onclick="downloadDocument(${Number(id)})">Download file</button>
    </div>`;
  }

  modal.hidden = false;
  document.body.classList.add('doc-preview-open');
}

function closeDocPreview() {
  const modal = $('doc-preview-modal');
  const body = $('doc-preview-body');
  if (body) body.innerHTML = '';
  if (modal) modal.hidden = true;
  if ($('doc-upload-modal')?.hidden !== false) {
    document.body.classList.remove('doc-preview-open');
  }
}

document.addEventListener('keydown', function (e) {
  if (e.key !== 'Escape') return;
  if (!$('doc-preview-modal')?.hidden) closeDocPreview();
  else if (!$('doc-upload-modal')?.hidden) closeUploadModal();
});

async function deleteDocument(id) {
  const doc = documentRows.find(item => Number(item.id) === Number(id));
  const ok = await confirmAction({
    title: 'Delete this document?',
    body: (doc ? doc.title : 'This document') + ' will be removed permanently.',
    confirmLabel: 'Delete'
  });
  if (!ok) return;

  const result = await api('/documents/' + id, 'DELETE');

  if (result && result.message === 'Deleted') {
    $('msg').textContent = '';
    toast('Document deleted.');
    closeDocPreview();
    await loadDocuments();
  } else {
    $('msg').textContent = errText(result);
    toast(errText(result), 'error');
  }
}

/* ---------- Drag a file anywhere on the page to upload ---------- */

(function () {
  let depth = 0;
  const overlay = () => $('doc-drop-overlay');
  const hasFiles = e => e.dataTransfer && Array.from(e.dataTransfer.types || []).includes('Files');

  window.addEventListener('dragenter', e => {
    if (!hasFiles(e)) return;
    depth++;
    overlay().hidden = false;
  });

  window.addEventListener('dragleave', e => {
    if (!hasFiles(e)) return;
    depth = Math.max(0, depth - 1);
    if (!depth) overlay().hidden = true;
  });

  window.addEventListener('dragover', e => {
    if (hasFiles(e)) e.preventDefault();
  });

  window.addEventListener('drop', e => {
    if (!hasFiles(e)) return;
    e.preventDefault();
    depth = 0;
    overlay().hidden = true;
    const file = e.dataTransfer.files && e.dataTransfer.files[0];
    if (file) openUploadModal(file);
  });
})();

loadDocuments();
</script>
@endpush