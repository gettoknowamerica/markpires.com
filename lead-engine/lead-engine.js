/**
 * Goliath Omni V119.4 Universal Lead Bridge
 * Routes real lead forms to /lead-engine/capture.php.
 * Add once before </body>:
 * <script src="/lead-engine/lead-engine.js?v=1194" defer></script>
 */
(function () {
  'use strict';

  var ENDPOINT = '/lead-engine/capture.php';
  var BUSY = new WeakSet();

  function str(v) { return v == null ? '' : String(v).trim(); }
  function lower(v) { return str(v).toLowerCase(); }
  function uuid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'lead_' + Date.now() + '_' + Math.random().toString(16).slice(2);
  }
  function getStorage(store, key) { try { return store.getItem(key) || ''; } catch (_) { return ''; } }
  function field(form, names) {
    for (var i = 0; i < names.length; i++) {
      var el = form.querySelector('[name="' + names[i] + '"],#' + names[i]);
      if (el && str(el.value)) return str(el.value);
    }
    return '';
  }
  function checkedValues(form, name) {
    return Array.prototype.slice.call(form.querySelectorAll('[name="' + name + '"]:checked')).map(function (x) { return x.value; }).join(', ');
  }
  function isLeadForm(form) {
    if (!form || form.tagName !== 'FORM') return false;
    if (form.matches('[data-goliath-ignore], [data-no-lead-capture]')) return false;
    if (form.matches('[data-goliath-lead], [data-lead-form]')) return true;
    var fingerprint = lower([form.id, form.className, form.getAttribute('name'), form.getAttribute('action')].join(' '));
    if (/(lead|contact|valuation|value|buyer|seller|relocat|expired|inherited|absentee|guide|consult|inquiry)/.test(fingerprint)) return true;
    var hasContact = !!form.querySelector('input[type="email"],input[type="tel"],[name="email"],[name="phone"],[name="mobile"]');
    var hasIdentity = !!form.querySelector('[name="name"],[name="full_name"],[name="first_name"],[name="firstName"]');
    return hasContact && hasIdentity;
  }
  function formPayload(form) {
    var fd = new FormData(form);
    var raw = {};
    fd.forEach(function (value, key) {
      if (raw[key] !== undefined) raw[key] = [].concat(raw[key], value);
      else raw[key] = value;
    });

    var first = field(form, ['first_name','firstName','firstname']);
    var last = field(form, ['last_name','lastName','lastname']);
    var fullName = field(form, ['name','full_name','fullName','contact_name']) || str(first + ' ' + last);

    return Object.assign({}, raw, {
      request_uid: field(form, ['request_uid']) || uuid(),
      name: fullName,
      email: field(form, ['email','contact_email','email_address','emailAddress']),
      phone: field(form, ['phone','mobile','contact_phone','phone_number','phoneNumber','telephone']),
      address: field(form, ['address','property_address','propertyAddress','street_address','streetAddress']),
      town: field(form, ['town','towns','city','municipality']),
      timeline: field(form, ['timeline','timeframe','move_timeline','selling_timeline']),
      goal: field(form, ['goal','intent','reason','objective']),
      message: field(form, ['message','notes','comments','details','additional_info']),
      type: field(form, ['type','lead_type','form_type']) || form.getAttribute('data-lead-type') || form.id || 'website_lead',
      source: 'markpires.com',
      page_url: window.location.href,
      referrer: document.referrer || '',
      utm_source: getStorage(localStorage, 'mp_utm_source'),
      utm_medium: getStorage(localStorage, 'mp_utm_medium'),
      utm_campaign: getStorage(localStorage, 'mp_utm_campaign'),
      utm_content: getStorage(localStorage, 'mp_utm_content'),
      utm_term: getStorage(localStorage, 'mp_utm_term'),
      visitor_id: getStorage(localStorage, 'mp_visitor_id'),
      session_id: getStorage(sessionStorage, 'mp_session_id'),
      consent: checkedValues(form, 'consent') || field(form, ['consent'])
    });
  }
  function statusNode(form) {
    var node = form.querySelector('[data-lead-status],.lead-status,.form-status');
    if (!node) {
      node = document.createElement('div');
      node.setAttribute('data-lead-status', '');
      node.setAttribute('role', 'status');
      node.style.marginTop = '10px';
      form.appendChild(node);
    }
    return node;
  }
  function setBusy(form, busy) {
    Array.prototype.forEach.call(form.querySelectorAll('button[type="submit"],input[type="submit"]'), function (b) { b.disabled = busy; });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!isLeadForm(form) || BUSY.has(form)) return;

    event.preventDefault();
    BUSY.add(form);
    setBusy(form, true);
    var status = statusNode(form);
    status.textContent = 'Submitting securely…';

    fetch(ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(formPayload(form))
    })
    .then(function (response) {
      return response.text().then(function (text) {
        var json;
        try { json = JSON.parse(text); } catch (_) { throw new Error('Capture returned invalid JSON (HTTP ' + response.status + ').'); }
        if (!response.ok || !json.success) throw new Error(json.error || 'Lead capture failed.');
        return json;
      });
    })
    .then(function (json) {
      status.textContent = json.message || 'Thank you. Mark will be calling you shortly.';
      form.dispatchEvent(new CustomEvent('goliath:lead-captured', { bubbles: true, detail: json }));
      var redirect = form.getAttribute('data-success-url');
      if (redirect) window.location.assign(redirect);
      else if (form.getAttribute('data-reset-on-success') !== 'false') form.reset();
    })
    .catch(function (error) {
      console.error('Goliath lead capture:', error);
      status.textContent = 'We could not submit this form. Please call or text Mark at 203-247-2655.';
      form.dispatchEvent(new CustomEvent('goliath:lead-error', { bubbles: true, detail: { error: error.message } }));
    })
    .finally(function () {
      BUSY.delete(form);
      setBusy(form, false);
    });
  }, true);
})();
