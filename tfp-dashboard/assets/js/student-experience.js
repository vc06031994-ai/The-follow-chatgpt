(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var cal = document.querySelector('[data-tfp-calendar]');
    var docs = document.querySelector('[data-tfp-documents]');
    var settings = window.tfpStudentExperience || {};
    function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (s) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[s]; }); }
    function post(action, data) {
      var fd = new FormData(); fd.append('action', action); fd.append('nonce', settings.calendarNonce || settings.documentsNonce || '');
      Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
      return fetch(settings.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd }).then(function (r) { return r.json(); });
    }
    if (cal) {
      var detail = cal.querySelector('[data-calendar-detail]');
      var skipModal = cal.querySelector('[data-skip-modal]');
      function showDay(date) {
        if (!detail) return;
        detail.innerHTML = '<div class="tfp-exp-loading">Loading…</div>';
        post('tfp_calendar_day', { date: date }).then(function (r) {
          if (!r.success) { detail.innerHTML = '<p>' + esc(r.data && r.data.message || 'Unable to load this date.') + '</p>'; return; }
          var d = r.data, html = '<div class="tfp-cal-selected-date">' + esc(d.date) + '</div>';
          if (d.off_day) html += '<div class="tfp-exp-alert"><strong>' + esc(d.off_day.name) + '</strong><span>Organization off day</span></div>';
          if (d.skip) html += '<div class="tfp-exp-status">Skip request: <strong>' + esc(d.skip.status) + '</strong></div>';
          if (d.meetings && d.meetings.length) {
            d.meetings.forEach(function (m) {
              html += '<div class="tfp-exp-card"><strong>' + esc(m.cohort) + '</strong><div>' + esc(m.start_label) + '</div><div>' + esc(m.schedule) + '</div>' + (m.facilitator ? '<div>Facilitator: ' + esc(m.facilitator) + '</div>' : '');
              if (m.meeting_url) html += '<a class="tfp-dash-btn tfp-dash-btn--primary" target="_blank" rel="noopener" href="' + esc(m.meeting_url) + '">Join Meeting</a>';
              if (!d.skip) html += '<button type="button" class="tfp-dash-btn tfp-dash-btn--primary tfp-exp-skip" data-date="' + esc(m.date) + '" data-cohort="' + esc(m.cohort_id) + '">Request a Skip</button>';
              html += '</div>';
            });
          } else if (!d.off_day) html += '<div class="tfp-cal-sidebar-empty-state"><p>No meetings scheduled for this day.</p></div>';
          detail.innerHTML = html;
          detail.querySelectorAll('.tfp-exp-skip').forEach(function (b) { b.addEventListener('click', function () { openSkip(b.dataset.date, b.dataset.cohort); }); });
        });
      }
      function openSkip(date, cohort) {
        if (!skipModal) return;
        skipModal.classList.add('is-open');
        skipModal.querySelector('[name="skip_date"]').value = date;
        skipModal.querySelector('[name="skip_cohort"]').value = cohort;
      }
      cal.querySelectorAll('[data-calendar-date]').forEach(function (cell) { cell.addEventListener('click', function () { cal.querySelectorAll('.tfp-cal-day.is-active').forEach(function (x) { x.classList.remove('is-active'); }); cell.classList.add('is-active'); showDay(cell.dataset.calendarDate); }); });
      cal.querySelectorAll('[data-open-skip]').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); openSkip(b.dataset.date || '', b.dataset.cohort || ''); }); });
      cal.querySelectorAll('[data-close-modal]').forEach(function (b) { b.addEventListener('click', function () { b.closest('.tfp-exp-modal').classList.remove('is-open'); }); });
      var form = skipModal && skipModal.querySelector('form');
      if (form) form.addEventListener('submit', function (e) { e.preventDefault(); var status = form.querySelector('[data-form-status]'); status.textContent = 'Submitting…'; post('tfp_skip_request', { date: form.querySelector('[name="skip_date"]').value, cohort_id: form.querySelector('[name="skip_cohort"]').value, reason: form.querySelector('[name="reason"]').value, notes: form.querySelector('[name="notes"]').value }).then(function (r) { status.textContent = r.success ? (r.data.message || 'Submitted.') : (r.data && r.data.message || 'Unable to submit.'); if (r.success) { setTimeout(function () { location.reload(); }, 600); } }); });
    }
    if (docs) {
      docs.querySelectorAll('[data-sign-document]').forEach(function (b) { b.addEventListener('click', function () { var modal = docs.querySelector('[data-document-modal]'); modal.classList.add('is-open'); modal.querySelector('[name="document_id"]').value = b.dataset.signDocument; modal.querySelector('[data-doc-title]').textContent = b.dataset.title || 'Document'; modal.querySelector('[name="signature"]').value = ''; }); });
      docs.querySelectorAll('[data-close-modal]').forEach(function (b) { b.addEventListener('click', function () { b.closest('.tfp-exp-modal').classList.remove('is-open'); }); });
      var form = docs.querySelector('[data-sign-form]');
      if (form) form.addEventListener('submit', function (e) { e.preventDefault(); var status = form.querySelector('[data-form-status]'); status.textContent = 'Signing…'; post('tfp_sign_document', { document_id: form.querySelector('[name="document_id"]').value, signature: form.querySelector('[name="signature"]').value }).then(function (r) { status.textContent = r.success ? (r.data.message || 'Signed.') : (r.data && r.data.message || 'Unable to sign.'); if (r.success) setTimeout(function () { location.reload(); }, 600); }); });
    }
  });
})();