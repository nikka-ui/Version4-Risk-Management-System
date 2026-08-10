<script>
(function () {
  const revisionBlocked = {{ $revisionBlocked ? 'true' : 'false' }};
  const submitVerb = {{ $isRevise ? "'Resubmit ticket'" : "'Submit ticket'" }};
  const confirmBox = document.getElementById('confirmBox');
  const submitBtn = document.getElementById('submitBtn');
  const submitBtnNative = document.getElementById('submitBtnNative');
  const submitForm = document.getElementById('submitForm');
  const reviewSection = document.getElementById('reviewSubmissionSection');
  const reviewConfirmBox = document.getElementById('reviewConfirmBox');
  const reviewHint = document.getElementById('reviewConfirmHint');

  function update() {
    const checked = confirmBox.checked && !revisionBlocked;
    submitBtn.classList.toggle('btn-enterprise-primary--inactive', !checked);
    submitBtn.disabled = revisionBlocked;
    reviewSection?.classList.toggle('review-submission-section--confirmed', checked);
    if (reviewHint) {
      reviewHint.textContent = revisionBlocked
        ? 'Update the returned report before submitting.'
        : checked ? 'Confirmed — click "' + submitVerb + '" to send it now.' : 'Required — check this box to enable ' + submitVerb + '.';
    }
  }

  submitBtn?.addEventListener('click', function () {
    if (revisionBlocked) return;
    if (!confirmBox.checked) return;
    submitBtnNative.click();
  });
  submitForm?.addEventListener('submit', function (e) {
    if (revisionBlocked) { e.preventDefault(); return; }
    if (e.submitter?.id === 'submitBtnNative' && !confirmBox.checked) e.preventDefault();
  });
  confirmBox?.addEventListener('change', update);
  update();
})();
</script>
