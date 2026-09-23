/* Multi-step application wizard: step navigation, auto percentage, live preview.
   Progressive enhancement — with JS disabled all steps show and the form still submits. */
(function () {
  'use strict';
  var LABELS = {
    name: 'Full Name', father_name: "Father's Name", mother_name: "Mother's Name",
    date_of_birth: 'Date of Birth', gender: 'Gender', category: 'Category',
    nationality: 'Nationality', aadhaar: 'Aadhaar Number', religion: 'Religion',
    blood_group: 'Blood Group', phone: 'Mobile Number', city: 'City / Town',
    state: 'State', pincode: 'PIN Code', guardian_name: 'Parent / Guardian Name',
    address: 'Correspondence Address', board_10: 'Class 10 Board', year_10: 'Class 10 Year',
    roll_10: 'Class 10 Roll', total_10: 'Class 10 Total Marks', obtained_10: 'Class 10 Obtained',
    percentage_10: 'Class 10 %', board_12: 'Class 12 Board', year_12: 'Class 12 Year',
    stream_12: 'Class 12 Stream', roll_12: 'Class 12 Roll', total_12: 'Class 12 Total Marks',
    obtained_12: 'Class 12 Obtained', percentage_12: 'Class 12 %',
    qualification: 'Highest Qualification', completion_year: 'Completion Year',
    board_university: 'Board / University', entrance_exam: 'Entrance Examination',
    entrance_rank: 'Entrance Rank / Score', note: 'Additional Information'
  };
  var DOCS = {
    doc_photo: 'Photograph', doc_signature: 'Signature', doc_marksheet10: 'Class 10 Marksheet',
    doc_marksheet12: 'Class 12 Marksheet', doc_caste: 'Caste Certificate',
    doc_pwd: 'Disability (PwD) Certificate', doc_domicile: 'Domicile Certificate',
    doc_other: 'Any Other Document'
  };
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function pct(total, obtained) {
    total = parseInt(total, 10); obtained = parseInt(obtained, 10);
    if (!total || total <= 0 || isNaN(obtained) || obtained < 0 || obtained > total) return '';
    return (Math.round(obtained / total * 10000) / 100).toFixed(2);
  }
  document.querySelectorAll('ol.w-steps').forEach(function (ol) {
    var form = ol.closest('form');
    if (!form) return;
    var steps = Array.prototype.slice.call(form.querySelectorAll('fieldset.w-step'));
    if (!steps.length) return;
    var dots = Array.prototype.slice.call(ol.querySelectorAll('[data-wdot]'));
    var hidden = form.querySelector('input[name="wizard_step"]');
    var current = hidden ? parseInt(hidden.value, 10) || 1 : 1;
    form.classList.add('w-js');
    function show(n) {
      current = Math.min(steps.length, Math.max(1, n));
      steps.forEach(function (s) { s.classList.toggle('active', s.getAttribute('data-wstep') === String(current)); });
      dots.forEach(function (d) {
        var k = parseInt(d.getAttribute('data-wdot'), 10);
        d.classList.toggle('done', k < current);
        d.classList.toggle('active', k === current);
      });
      if (hidden) hidden.value = String(current);
      if (current === steps.length) renderPreview();
      ol.scrollIntoView({ block: 'nearest' });
    }
    function calc() {
      ['10', '12'].forEach(function (lv) {
        var t = form.querySelector('[name="total_' + lv + '"]');
        var o = form.querySelector('[name="obtained_' + lv + '"]');
        var p = form.querySelector('[name="percentage_' + lv + '"]');
        if (t && o && p) p.value = pct(t.value, o.value);
      });
    }
    function renderPreview() {
      var box = form.querySelector('#wPreview');
      if (!box) return;
      var html = '';
      var photo = form.querySelector('[name="doc_photo"]');
      if (photo && photo.files && photo.files[0]) {
        html += '<div class="w-pv-photo"><img id="wPhotoImg" alt="Photograph preview"><small>Photograph</small></div>';
      }
      html += '<dl class="w-pv-grid">';
      Object.keys(LABELS).forEach(function (name) {
        var el = form.querySelector('[name="' + name + '"]');
        if (!el) return;
        var v = (el.value || '').trim();
        html += '<div><dt>' + esc(LABELS[name]) + '</dt><dd>' + (v === '' ? '<span class="w-empty">—</span>' : esc(v)) + '</dd></div>';
      });
      html += '</dl><h3 class="w-sub">Documents Attached</h3><ul class="w-pv-docs">';
      var any = false;
      Object.keys(DOCS).forEach(function (name) {
        var el = form.querySelector('[name="' + name + '"]');
        if (el && el.files && el.files[0]) { any = true; html += '<li>✓ ' + esc(DOCS[name]) + ' — ' + esc(el.files[0].name) + '</li>'; }
      });
      if (!any) html += '<li class="w-empty">No documents attached yet (optional).</li>';
      html += '</ul>';
      box.innerHTML = html;
      var img = box.querySelector('#wPhotoImg');
      if (img && photo.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) { img.src = e.target.result; };
        reader.readAsDataURL(photo.files[0]);
      }
    }
    form.addEventListener('click', function (e) {
      var next = e.target.closest('[data-wnext]');
      var prev = e.target.closest('[data-wprev]');
      if (next) { e.preventDefault(); show(current + 1); }
      else if (prev) { e.preventDefault(); show(current - 1); }
    });
    form.addEventListener('input', calc);
    form.addEventListener('submit', function (e) {
      if (e.submitter && e.submitter.hasAttribute('formnovalidate')) return;
      calc();
      for (var i = 0; i < steps.length; i++) {
        var bad = steps[i].querySelector('[required]:invalid, select[required]:invalid, input[required]:invalid, textarea[required]:invalid, input[type="checkbox"][required]:not(:checked)');
        if (bad) {
          e.preventDefault();
          show(i + 1);
          if (bad.reportValidity) bad.reportValidity();
          else if (bad.focus) bad.focus();
          return;
        }
      }
      if (current !== steps.length) renderPreview();
    });
    calc();
    show(current, false);
  });
})();
