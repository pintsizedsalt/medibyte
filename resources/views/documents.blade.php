@extends('layouts.app')

@section('title', 'Documents · MediByte')

@section('content')
  <div class="page-heading">
    <div class="heading-main">
      <a class="back-link" href="/records" aria-label="Back to all records">
        <svg class="icon"><use href="#i-chevron-left"/></svg>
      </a>
      <div>
        <p class="eyebrow">Health records</p>
        <h2>Documents</h2>
        <p>Store prescriptions, lab results, and medical certificates in your private record.</p>
      </div>
    </div>
    <a class="btn btn-ghost" href="/records">
      <svg class="icon"><use href="#i-file"/></svg>
      All records
    </a>
  </div>

  <p id="msg" class="form-message" role="alert"></p>

  <section class="card">
    <div class="card-head">
      <div>
        <p class="eyebrow">Upload</p>
        <h3>Add a document</h3>
      </div>
    </div>

    <form id="doc-form" onsubmit="event.preventDefault(); uploadDocument()">
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
          <input id="doc_title" type="text" maxlength="150" placeholder="e.g. Blood test — October" required>
        </label>

        <div class="field span-full">
          <span>File</span>
          <label class="document-drop" for="doc_file">
            <svg class="icon"><use href="#i-file"/></svg>
            <span>
              <strong id="doc-file-label">Choose a PDF, JPG, or PNG</strong>
              <small>Up to 10 MB. Your file stays on a private disk.</small>
            </span>
          </label>
        </div>

        <label class="field span-full">
          <span>Notes (optional)</span>
          <textarea id="doc_notes" rows="3" maxlength="1000" placeholder="Anything you want to remember about this document."></textarea>
        </label>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">
          <svg class="icon"><use href="#i-plus"/></svg>
          Upload document
        </button>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <div>
        <p class="eyebrow">Your files</p>
        <h3>Saved documents</h3>
      </div>
      <span class="history-count" id="doc-count">0</span>
    </div>
    <div id="documents-list">
      <p class="muted">Loading…</p>
    </div>
  </section>
@endsection

@push('scripts')
<script>
let documentRows = [];

const DOCUMENT_TYPES = {
  prescription: 'Prescription',
  lab_result: 'Lab result',
  medical_certificate: 'Medical certificate',
  other: 'Other'
};

function formatBytes(bytes) {
  const value = Number(bytes || 0);
  if (value < 1024) return value + ' B';
  if (value < 1024 * 1024) return (value / 1024).toFixed(1) + ' KB';
  return (value / (1024 * 1024)).toFixed(1) + ' MB';
}

function updateFileLabel() {
  const file = $('doc_file').files[0];
  const label = $('doc-file-label');
  if (label) label.textContent = file ? file.name : 'Choose a PDF, JPG, or PNG';
}

function renderDocuments() {
  const list = $('documents-list');
  const count = $('doc-count');
  if (count) count.textContent = documentRows.length;

  if (!documentRows.length) {
    list.innerHTML = `<div class="empty-state">
      <span class="empty-orb"><svg class="icon"><use href="#i-file"/></svg></span>
      <h4>No documents yet</h4>
      <p>Upload your first prescription, lab result, or medical certificate above.</p>
    </div>`;
    return;
  }

  list.innerHTML = '<div class="record-list">' + documentRows.map(doc => `
    <div class="record document-row">
      <span class="record-icon tone-blue"><svg class="icon"><use href="#i-file"/></svg></span>
      <div class="record-main">
        <strong>${esc(doc.title)} · ${esc(DOCUMENT_TYPES[doc.type] || 'Other')}</strong>
        <span class="record-meta">${esc(doc.original_name)} · ${formatBytes(doc.size)} · ${esc(dateLabel(doc.created_at))}</span>
        ${doc.notes ? `<span class="record-meta">${esc(doc.notes)}</span>` : ''}
      </div>
      <div class="record-actions">
        <button type="button" class="record-row-action" onclick="downloadDocument(${Number(doc.id)})" aria-label="Download document">
          <svg class="icon"><use href="#i-arrow"/></svg>
        </button>
        <button type="button" class="record-row-action danger" onclick="deleteDocument(${Number(doc.id)})" aria-label="Delete document">
          <svg class="icon"><use href="#i-trash"/></svg>
        </button>
      </div>
    </div>
  `).join('') + '</div>';
}

async function loadDocuments() {
  const data = await api('/documents');
  documentRows = Array.isArray(data) ? data : [];
  renderDocuments();
}

async function uploadDocument() {
  const file = $('doc_file').files[0];

  if (!file) {
    $('msg').textContent = 'Choose a file to upload.';
    return;
  }

  if (file.size > 10 * 1024 * 1024) {
    $('msg').textContent = 'The file must be 10 MB or smaller.';
    return;
  }

  const formData = new FormData();
  formData.append('type', $('doc_type').value);
  formData.append('title', $('doc_title').value.trim());
  formData.append('notes', $('doc_notes').value.trim());
  formData.append('file', file);

  $('msg').textContent = 'Uploading…';
  const result = await api('/documents', 'POST', formData);

  if (result && result.id) {
    $('msg').textContent = '';
    toast('Document uploaded.');
    $('doc-form').reset();
    updateFileLabel();
    await loadDocuments();
  } else {
    $('msg').textContent = errText(result);
  }
}

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
    await loadDocuments();
  } else {
    $('msg').textContent = errText(result);
    toast(errText(result), 'error');
  }
}

loadDocuments();
</script>
@endpush
