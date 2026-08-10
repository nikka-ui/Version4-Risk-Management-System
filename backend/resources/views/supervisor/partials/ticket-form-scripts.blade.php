<script>
(function () {
  const riskForm = document.getElementById('riskForm');
  const nextBtn = document.getElementById('nextBtn');
  const fileInput = document.getElementById('fileInput');
  const savedCount = {{ (int) $savedCount }};
  const dropzone = document.getElementById('dropzone');
  const browseBtn = document.getElementById('browseBtn');
  const filePreview = document.getElementById('filePreview');
  const pendingUploads = document.getElementById('pendingUploads');
  const uploadMessage = document.getElementById('uploadMessage');
  const aiLoading = document.getElementById('aiLoading');
  const isReviseMode = {{ $isRevise ? 'true' : 'false' }};
  const initialSnapshot = @json($initialSnapshot);

  let selectedFiles = [];
  const allowedExt = new Set(['pdf','png','jpg','jpeg']);

  function countSavedNotRemoved() {
    const boxes = document.querySelectorAll('input[name="removeAttachmentIds"]:checked');
    return Math.max(0, savedCount - boxes.length);
  }

  function syncInputFiles() {
    const dt = new DataTransfer();
    selectedFiles.forEach((f) => dt.items.add(f));
    fileInput.files = dt.files;
  }

  function renderPreview() {
    filePreview.innerHTML = '';
    if (!selectedFiles.length) {
      if (pendingUploads) pendingUploads.hidden = true;
      return;
    }
    if (pendingUploads) pendingUploads.hidden = false;
    selectedFiles.forEach((f, idx) => {
      const li = document.createElement('li');
      li.className = 'upload-preview-item upload-preview-item--pending';
      li.innerHTML = '<span class="upload-name"></span><span class="upload-meta"></span><button type="button" class="upload-remove-btn">Remove</button>';
      li.querySelector('.upload-name').textContent = f.name;
      li.querySelector('.upload-meta').textContent = (f.size / 1024 / 1024).toFixed(2) + ' MB · Ready to upload';
      li.querySelector('.upload-remove-btn').addEventListener('click', () => {
        selectedFiles.splice(idx, 1);
        syncInputFiles();
        renderPreview();
        updateNextState();
      });
      filePreview.appendChild(li);
    });
  }

  function validateFile(file) {
    const parts = String(file.name || '').toLowerCase().split('.');
    const ext = parts.length > 1 ? parts[parts.length - 1] : '';
    if (!allowedExt.has(ext)) return { ok: false, reason: 'Unsupported file type: ' + ext.toUpperCase() };
    if (file.size > 20 * 1024 * 1024) return { ok: false, reason: 'File exceeds 20MB: ' + file.name };
    return { ok: true };
  }

  function addFiles(files) {
    for (const f of Array.from(files || [])) {
      const v = validateFile(f);
      if (!v.ok) continue;
      selectedFiles.push(f);
    }
    selectedFiles = selectedFiles.slice(0, 10);
    syncInputFiles();
    renderPreview();
    updateNextState();
  }

  function setFieldInvalid(id, invalid) {
    const el = document.getElementById(id);
    const wrap = el && el.closest('.field');
    if (wrap) wrap.classList.toggle('field--invalid', invalid);
  }

  function isFormDirty() {
    if (!isReviseMode) return true;
    if (selectedFiles.length > 0) return true;
    if (document.querySelectorAll('input[name="removeAttachmentIds"]:checked').length > 0) return true;
    return ['title','location','what','why','where','when','who','how'].some(
      (id) => document.getElementById(id).value.trim() !== (initialSnapshot[id] || '').trim()
    );
  }

  function updateNextState() {
    const title = document.getElementById('title').value.trim();
    const location = document.getElementById('location').value.trim();
    const fields = ['what','why','where','when','who','how'].map((id) => document.getElementById(id).value.trim());
    const evCount = selectedFiles.length + countSavedNotRemoved();
    const revised = isFormDirty();
    ['title','location','what','why','where','when','who','how'].forEach((id) => {
      setFieldInvalid(id, !document.getElementById(id).value.trim());
    });
    const evidenceMissing = evCount === 0;
    document.getElementById('evidenceSection')?.classList.toggle('field--invalid', evidenceMissing);
    dropzone?.classList.toggle('upload-zone--invalid', evidenceMissing);
    document.getElementById('revisionRequiredHint')?.classList.toggle('revision-required-hint--visible', isReviseMode && !revised);
    const ready = title && location && fields.every(Boolean) && !evidenceMissing && revised;
    nextBtn.disabled = !ready;
    nextBtn.classList.toggle('btn-enterprise-next-ready', ready);
  }

  browseBtn?.addEventListener('click', () => fileInput.click());
  dropzone?.addEventListener('click', (e) => { if (e.target === dropzone) fileInput.click(); });
  fileInput?.addEventListener('change', (e) => { addFiles(e.target.files); e.target.value = ''; });
  ['dragenter','dragover'].forEach((evt) => dropzone?.addEventListener(evt, (e) => { e.preventDefault(); dropzone.classList.add('dragover'); }));
  ['dragleave','drop'].forEach((evt) => dropzone?.addEventListener(evt, (e) => { e.preventDefault(); dropzone.classList.remove('dragover'); }));
  dropzone?.addEventListener('drop', (e) => { if (e.dataTransfer?.files) addFiles(e.dataTransfer.files); });
  ['title','location','what','why','where','when','who','how'].forEach((id) => {
    document.getElementById(id)?.addEventListener('input', updateNextState);
  });
  document.querySelectorAll('input[name="removeAttachmentIds"]').forEach((el) => el.addEventListener('change', updateNextState));
  riskForm?.addEventListener('submit', (e) => {
    if (nextBtn.disabled) {
      e.preventDefault();
      return;
    }
    syncInputFiles();
    if (aiLoading) aiLoading.style.display = 'flex';
  });
  updateNextState();
})();
</script>
