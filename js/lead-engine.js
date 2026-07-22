/**
 * LEAD ENGINE — lead-engine.js
 * markpires.com | Frontend lead capture for all standard forms
 * Install as: /public_html/js/lead-engine.js
 *
 * Purpose:
 * - Keeps all normal forms sending to /lead-engine/capture.php
 * - Adds stronger sticky valuation CTA
 * - Adds universal "Talk to Mark" qualification funnel
 * - Sends consent, UTM, page_url, source, and qualifying details to the live backend
 */

(function () {
  'use strict';

  const API = '/lead-engine/capture.php';
  const STORAGE_KEY = 'mp_lead_captured';
  const CONSULT_STORAGE_KEY = 'mp_consult_funnel_seen';
  let popupShown = false;
  let consultOpen = false;

  function addConsentDefaults(payload) {
    payload.consent = payload.consent || 'yes';
    payload.consent_text = payload.consent_text || 'User agreed to be contacted by Mark Pires, Discover Connecticut, and their representatives by phone, text, email, and AI assistant regarding this request. Consent is not a condition of purchase.';
    payload.consent_url = payload.consent_url || 'https://markpires.com/communication-consent.html';
    return payload;
  }

  function addUTM(payload) {
    ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].forEach(k => {
      const v = sessionStorage.getItem(k);
      if (v) payload[k] = v;
    });
    return payload;
  }

  async function submitLead(payload) {
    payload.page_url = payload.page_url || window.location.href;
    payload.source = payload.source || window.location.hostname;
    payload = addUTM(addConsentDefaults(payload));

    const res = await fetch(API, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload)
    });

    const text = await res.text();
    let data;

    try {
      data = JSON.parse(text);
    } catch (e) {
      throw new Error('Server returned non-JSON response: ' + text);
    }

    if (!data.success) {
      throw new Error(data.error || 'Lead capture failed');
    }

    sessionStorage.setItem(STORAGE_KEY, '1');
    return data;
  }

  function showThankYou(container, message) {
    if (!container) return;
    container.innerHTML = `
      <div style="text-align:center;padding:40px 20px">
        <div style="font-size:48px;margin-bottom:15px">✅</div>
        <h3 style="color:#1a1a2e;margin-bottom:10px">${message || "Got it — Mark's team will be in touch shortly."}</h3>
        <p style="color:#666;font-size:14px">Jessica may call shortly to confirm the details.</p>
        <p style="color:#666;font-size:14px">— Mark Pires · 203-247-2655</p>
      </div>`;
  }

  function wireAllForms() {
    document.querySelectorAll('[data-lead-form]').forEach(form => {
      if (form.dataset.leadWired === '1') return;
      form.dataset.leadWired = '1';

      form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const btn = form.querySelector('[type=submit], button:not([type])');
        const originalText = btn ? btn.textContent : '';

        if (btn) {
          btn.disabled = true;
          btn.textContent = 'Sending...';
        }

        const fd = new FormData(form);
        const payload = {};
        fd.forEach((v, k) => payload[k] = v);

        payload.type = payload.type || form.dataset.leadType || 'general';
        payload.tag = payload.tag || form.dataset.leadTag || 'form';

        try {
          const data = await submitLead(payload);

          if (typeof gtag === 'function') {
            gtag('event', 'lead_capture', {
              event_category: 'Lead',
              event_label: payload.type,
              value: 1
            });
          }

          const msg = form.dataset.thankYou || null;
          showThankYou(form.parentElement || form, msg);

        } catch (err) {
          if (btn) {
            btn.disabled = false;
            btn.textContent = originalText || 'Try Again';
          }
          console.error('Lead submit error:', err);
          alert('Something went wrong. Please call Mark directly at 203-247-2655 or try again.');
        }
      });
    });
  }

  function captureUTM() {
    const params = new URLSearchParams(window.location.search);
    ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].forEach(k => {
      const v = params.get(k);
      if (v) sessionStorage.setItem(k, v);
    });
  }

  function injectLeadStyles() {
    if (document.getElementById('mp-lead-engine-styles')) return;

    const style = document.createElement('style');
    style.id = 'mp-lead-engine-styles';
    style.textContent = `
      #mp-popup input:focus,
      #mp-consult input:focus,
      #mp-consult select:focus,
      #mp-consult textarea:focus {
        border-color:#c8a96e!important;
        outline:none;
      }
      .mp-consult-choice {
        border:1px solid #e0dbd4;
        border-radius:8px;
        padding:13px;
        cursor:pointer;
        background:#fff;
        color:#1a1a2e;
        font-size:13px;
        line-height:1.25;
        transition:all .2s;
        text-align:center;
        font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
      }
      .mp-consult-choice:hover,
      .mp-consult-choice.active {
        border-color:#c8a96e;
        background:#fff8ec;
      }
      .mp-consult-field {
        margin-bottom:12px;
      }
      .mp-consult-field label {
        display:block;
        font-size:11px;
        color:#777;
        text-transform:uppercase;
        letter-spacing:1px;
        margin-bottom:5px;
        font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
      }
      .mp-consult-field input,
      .mp-consult-field select,
      .mp-consult-field textarea {
        width:100%;
        padding:12px 13px;
        border:1px solid #ddd;
        border-radius:6px;
        font-size:14px;
        font-family:inherit;
      }
      .mp-consult-btn {
        width:100%;
        background:#1a1a2e;
        color:white;
        padding:14px;
        border:none;
        border-radius:6px;
        font-size:15px;
        cursor:pointer;
        font-weight:700;
        letter-spacing:.5px;
        font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
      }
      .mp-consult-btn:hover { background:#c8a96e; }
      @media(max-width:520px){
        #mp-sticky-bar .mp-sticky-inner{
          flex-direction:column;
          align-items:flex-start!important;
          padding:12px 14px!important;
        }
        #mp-sticky-bar .mp-sticky-actions{
          width:100%;
        }
        #mp-sticky-bar .mp-sticky-actions a,
        #mp-sticky-bar .mp-sticky-actions button.mp-talk-link{
          flex:1;
          text-align:center;
          justify-content:center;
        }
      }
    `;
    document.head.appendChild(style);
  }

  function triggerPopup(tag) {
    if (popupShown || document.getElementById('mp-popup')) return;
    popupShown = true;
    injectLeadStyles();

    const popup = document.createElement('div');
    popup.id = 'mp-popup';
    popup.innerHTML = `
      <div id="mp-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:99998;display:flex;align-items:center;justify-content:center;padding:18px">
        <div style="background:white;border-radius:12px;padding:34px;max-width:480px;width:100%;position:relative;box-shadow:0 30px 80px rgba(0,0,0,.4)">
          <button type="button" id="mp-popup-close" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:24px;cursor:pointer;color:#999;line-height:1">&times;</button>
          <div style="text-align:center;margin-bottom:22px">
            <div style="font-size:11px;letter-spacing:3px;color:#c8a96e;text-transform:uppercase;margin-bottom:8px">Before You Go</div>
            <h2 style="color:#1a1a2e;font-size:22px;margin-bottom:10px;line-height:1.3">Curious What Your House Is Worth?</h2>
            <p style="color:#666;font-size:14px;line-height:1.6">Start with a free, personally reviewed home valuation from Mark's team — not a generic computer estimate.</p>
          </div>

          <form id="mp-popup-form" data-lead-form data-lead-type="valuation" data-lead-tag="${tag}" data-thank-you="Perfect — Mark's team received your request. Jessica may call shortly to confirm details.">
            <input type="hidden" name="type" value="valuation">
            <input type="hidden" name="tag" value="${tag}">
            <input type="hidden" name="consent" value="yes">
            <input type="hidden" name="consent_url" value="https://markpires.com/communication-consent.html">

            <div style="margin-bottom:12px">
              <input type="text" name="address" placeholder="Property address" required style="width:100%;padding:12px 14px;border:1px solid #ddd;border-radius:6px;font-size:14px;font-family:inherit">
            </div>
            <div style="margin-bottom:12px">
              <input type="text" name="name" placeholder="Your name" required style="width:100%;padding:12px 14px;border:1px solid #ddd;border-radius:6px;font-size:14px;font-family:inherit">
            </div>
            <div style="margin-bottom:12px">
              <input type="email" name="email" placeholder="Your email" required style="width:100%;padding:12px 14px;border:1px solid #ddd;border-radius:6px;font-size:14px;font-family:inherit">
            </div>
            <div style="margin-bottom:12px">
              <input type="tel" name="phone" placeholder="Phone for faster follow-up" style="width:100%;padding:12px 14px;border:1px solid #ddd;border-radius:6px;font-size:14px;font-family:inherit">
            </div>

            <label style="display:block;font-size:11px;line-height:1.45;color:#777;margin:12px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
              <input type="checkbox" required checked style="width:auto;margin-right:5px">
              I agree to be contacted by phone, text, email, and AI assistant regarding my request.
              <a href="/communication-consent.html" target="_blank" style="color:#c8a96e">View consent.</a>
            </label>

            <button type="submit" style="width:100%;background:#1a1a2e;color:white;padding:14px;border:none;border-radius:6px;font-size:15px;cursor:pointer;font-weight:600;letter-spacing:.5px;font-family:inherit">
              CHECK MY VALUE FREE →
            </button>
            <p style="text-align:center;font-size:11px;color:#999;margin-top:10px">No spam. No obligation.</p>
          </form>
        </div>
      </div>`;

    document.body.appendChild(popup);
    document.getElementById('mp-popup-close').addEventListener('click', () => popup.remove());
    wireAllForms();
  }

  function closeConsultFunnel() {
    const el = document.getElementById('mp-consult');
    if (el) el.remove();
    consultOpen = false;
    document.body.style.overflow = '';
  }

  function openConsultFunnel(tag = 'consultation_funnel') {
    if (consultOpen || document.getElementById('mp-consult')) return false;
    consultOpen = true;
    sessionStorage.setItem(CONSULT_STORAGE_KEY, '1');
    injectLeadStyles();

    const wrap = document.createElement('div');
    wrap.id = 'mp-consult';
    wrap.innerHTML = `
      <div style="position:fixed;inset:0;background:rgba(0,0,0,.72);z-index:99999;display:flex;align-items:center;justify-content:center;padding:18px">
        <div style="background:white;border-radius:14px;max-width:560px;width:100%;max-height:92vh;overflow:auto;position:relative;box-shadow:0 30px 90px rgba(0,0,0,.45);color:#1a1a2e">
          <button type="button" id="mp-consult-close" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:26px;cursor:pointer;color:#999;line-height:1">&times;</button>

          <div style="padding:32px 32px 24px;border-bottom:1px solid #eee;text-align:center">
            <div style="font-size:11px;letter-spacing:3px;color:#c8a96e;text-transform:uppercase;margin-bottom:8px">Connect With Mark</div>
            <h2 style="font-size:25px;line-height:1.15;margin-bottom:9px;color:#1a1a2e">What Can Mark Help You With?</h2>
            <p style="color:#666;font-size:14px;line-height:1.55;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">Answer a few quick questions so Jessica and Mark know exactly how to help.</p>
          </div>

          <form id="mp-consult-form" style="padding:26px 32px 32px">
            <input type="hidden" name="type" value="consultation">
            <input type="hidden" name="tag" value="${tag}">
            <input type="hidden" name="goal" id="mp_goal" value="">
            <input type="hidden" name="consent" value="yes">
            <input type="hidden" name="consent_url" value="https://markpires.com/communication-consent.html">

            <div class="mp-consult-field">
              <label>What are you looking to do?</label>
              <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px">
                <div class="mp-consult-choice" data-goal="Sell my home">Sell</div>
                <div class="mp-consult-choice" data-goal="Buy a home">Buy</div>
                <div class="mp-consult-choice" data-goal="Buy and sell">Buy + Sell</div>
                <div class="mp-consult-choice" data-goal="Relocating to Connecticut">Relocating</div>
              </div>
            </div>

            <div class="mp-consult-field">
              <label>Where do you live currently?</label>
              <input type="text" name="current_location" placeholder="Town, state, or NYC neighborhood">
            </div>

            <div class="mp-consult-field">
              <label>Where are you looking to move or focus?</label>
              <input type="text" name="target_area" placeholder="Fairfield, Westport, Greenwich, Stamford, etc.">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div class="mp-consult-field">
                <label>Do you own or rent?</label>
                <select name="own_rent">
                  <option value="">Select</option>
                  <option>Own</option>
                  <option>Rent</option>
                  <option>Both / investment</option>
                </select>
              </div>
              <div class="mp-consult-field">
                <label>Timeline</label>
                <select name="timeline">
                  <option value="">Select</option>
                  <option>Immediately</option>
                  <option>1–3 months</option>
                  <option>3–6 months</option>
                  <option>6–12 months</option>
                  <option>Just exploring</option>
                </select>
              </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div class="mp-consult-field">
                <label>Budget / Value Range</label>
                <select name="budget">
                  <option value="">Select</option>
                  <option>Under $400,000</option>
                  <option>$400,000 - $600,000</option>
                  <option>$600,000 - $900,000</option>
                  <option>$900,000 - $1.5M</option>
                  <option>$1.5M+</option>
                </select>
              </div>
              <div class="mp-consult-field">
                <label>Want your current home value?</label>
                <select id="mp_wants_value" name="wants_home_value">
                  <option value="">Select</option>
                  <option>No</option>
                  <option>Yes</option>
                  <option>Maybe</option>
                </select>
              </div>
            </div>

            <div class="mp-consult-field" id="mp_address_wrap" style="display:none">
              <label>Property address for valuation</label>
              <input type="text" name="address" placeholder="123 Main St, Fairfield CT">
            </div>

            <div class="mp-consult-field">
              <label>Your Name</label>
              <input type="text" name="name" placeholder="First and last name" required>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div class="mp-consult-field">
                <label>Email</label>
                <input type="email" name="email" placeholder="your@email.com" required>
              </div>
              <div class="mp-consult-field">
                <label>Phone</label>
                <input type="tel" name="phone" placeholder="203-555-0100" required>
              </div>
            </div>

            <div class="mp-consult-field">
              <label>Anything Mark should know?</label>
              <textarea name="notes" placeholder="Tell us anything helpful..." style="height:74px"></textarea>
            </div>

            <label style="display:block;font-size:11px;line-height:1.45;color:#777;margin:12px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
              <input type="checkbox" required checked style="width:auto;margin-right:5px">
              I agree to be contacted by phone, text, email, and AI assistant regarding my request.
              <a href="/communication-consent.html" target="_blank" style="color:#c8a96e">View consent.</a>
            </label>

            <button class="mp-consult-btn" type="submit">SEND TO MARK →</button>
          </form>
        </div>
      </div>`;

    document.body.appendChild(wrap);
    document.body.style.overflow = 'hidden';

    document.getElementById('mp-consult-close').addEventListener('click', closeConsultFunnel);

    wrap.addEventListener('click', function(e) {
      if (e.target === wrap.firstElementChild) closeConsultFunnel();
    });

    wrap.querySelectorAll('.mp-consult-choice').forEach(choice => {
      choice.addEventListener('click', function() {
        wrap.querySelectorAll('.mp-consult-choice').forEach(c => c.classList.remove('active'));
        choice.classList.add('active');
        document.getElementById('mp_goal').value = choice.dataset.goal || '';
      });
    });

    const wantsValue = document.getElementById('mp_wants_value');
    const addressWrap = document.getElementById('mp_address_wrap');
    wantsValue.addEventListener('change', function() {
      addressWrap.style.display = /yes|maybe/i.test(wantsValue.value) ? 'block' : 'none';
    });

    document.getElementById('mp-consult-form').addEventListener('submit', async function(e) {
      e.preventDefault();
      const form = e.target;
      const btn = form.querySelector('.mp-consult-btn');
      const original = btn.textContent;

      btn.disabled = true;
      btn.textContent = 'Sending...';

      const fd = new FormData(form);
      const p = {};
      fd.forEach((v, k) => p[k] = v);

      const messageParts = [
        p.current_location && `Current location: ${p.current_location}`,
        p.target_area && `Target area: ${p.target_area}`,
        p.own_rent && `Own/rent: ${p.own_rent}`,
        p.wants_home_value && `Wants home value: ${p.wants_home_value}`,
        p.notes && `Notes: ${p.notes}`
      ];

      const payload = {
        type: p.type || 'consultation',
        tag: p.tag || tag,
        name: p.name || '',
        email: p.email || '',
        phone: p.phone || '',
        address: p.address || '',
        timeline: p.timeline || '',
        goal: p.goal || '',
        budget: p.budget || '',
        price_range: p.budget || '',
        message: messageParts.filter(Boolean).join(' | '),
        source: 'markpires.com',
        page_url: window.location.href,
        consent: 'yes',
        consent_url: 'https://markpires.com/communication-consent.html'
      };

      try {
        await submitLead(payload);

        if (typeof gtag === 'function') {
          gtag('event', 'consultation_funnel_submitted', {
            event_category: 'Lead',
            event_label: payload.goal || 'consultation',
            value: 1
          });
        }

        wrap.querySelector('form').style.display = 'none';
        const panel = wrap.querySelector('div[style*="background:white"]');
        panel.insertAdjacentHTML('beforeend', `
          <div style="text-align:center;padding:34px">
            <div style="font-size:52px;margin-bottom:12px">✅</div>
            <h2 style="color:#1a1a2e;margin-bottom:10px">Received.</h2>
            <p style="color:#666;font-size:15px;line-height:1.6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">Jessica may call shortly to confirm details. Mark's team now has the context.</p>
            <button type="button" id="mp-consult-done" class="mp-consult-btn" style="margin-top:16px">Done</button>
          </div>
        `);
        document.getElementById('mp-consult-done').addEventListener('click', closeConsultFunnel);

      } catch (err) {
        console.error('Consult funnel submit error:', err);
        btn.disabled = false;
        btn.textContent = original;
        alert('Something went wrong. Please call Mark directly at 203-247-2655.');
      }
    });

    return false;
  }

  function initExitIntent() {
    if (sessionStorage.getItem(STORAGE_KEY)) return;

    document.addEventListener('mouseleave', function (e) {
      if (e.clientY < 10) triggerPopup('exit_intent');
    });

    let lastY = window.scrollY;
    let lastT = Date.now();

    window.addEventListener('scroll', function () {
      const nowY = window.scrollY;
      const nowT = Date.now();
      const velocity = (lastY - nowY) / Math.max(1, nowT - lastT);
      if (velocity > 2 && nowY < 300 && Date.now() > 30000) {
        triggerPopup('mobile_exit_intent');
      }
      lastY = nowY;
      lastT = nowT;
    }, {passive: true});

    setTimeout(() => {
      if (!sessionStorage.getItem(STORAGE_KEY)) triggerPopup('timed_45s');
    }, 45000);
  }

  function initStickyBar() {
    if (sessionStorage.getItem(STORAGE_KEY)) return;
    if (document.getElementById('mp-sticky-bar')) return;
    injectLeadStyles();

    const bar = document.createElement('div');
    bar.id = 'mp-sticky-bar';
    bar.innerHTML = `
      <div class="mp-sticky-inner" style="position:fixed;bottom:0;left:0;right:0;background:#1a1a2e;color:white;padding:12px 18px;z-index:9999;display:flex;align-items:center;justify-content:space-between;gap:12px;box-shadow:0 -4px 20px rgba(0,0,0,.3);font-family:Georgia,serif">
        <span style="font-size:13px;color:rgba(255,255,255,.9)">🏠 Curious what your house is worth?</span>
        <div class="mp-sticky-actions" style="display:flex;gap:10px;align-items:center">
          <a href="/home-valuation.php" style="background:#c8a96e;color:white;padding:9px 14px;border-radius:5px;text-decoration:none;font-size:13px;font-weight:700;white-space:nowrap;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">Check Now Free →</a>
          <button type="button" class="mp-talk-link" id="mp-sticky-talk" style="background:transparent;border:1px solid rgba(255,255,255,.22);color:white;padding:9px 13px;border-radius:5px;text-decoration:none;font-size:13px;font-weight:700;white-space:nowrap;cursor:pointer;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">Talk to Mark →</button>
          <button type="button" id="mp-sticky-close" style="background:none;border:none;color:rgba(255,255,255,.55);cursor:pointer;font-size:20px;line-height:1;padding:4px">&times;</button>
        </div>
      </div>`;

    setTimeout(() => {
      if (!sessionStorage.getItem(STORAGE_KEY)) {
        document.body.appendChild(bar);
        document.body.style.paddingBottom = '76px';
        document.getElementById('mp-sticky-close').addEventListener('click', () => {
          bar.remove();
          document.body.style.paddingBottom = '';
        });
        document.getElementById('mp-sticky-talk').addEventListener('click', () => openConsultFunnel('sticky_consultation'));
      }
    }, 10000);
  }

  function initValuationForm() {
    const form = document.getElementById('home-valuation.php');
    if (!form) return;

    form.setAttribute('data-lead-form', '');
    form.setAttribute('data-lead-type', 'valuation');
    form.setAttribute('data-lead-tag', 'valuation_inline');
    form.setAttribute('data-thank-you', "Perfect — Mark's team received your valuation request.");
  }

  function initCTAInterceptors() {
    document.addEventListener('click', function(e) {
      const el = e.target.closest('a,button');
      if (!el) return;

      const href = el.getAttribute('href') || '';
      const onclick = el.getAttribute('onclick') || '';
      const text = (el.textContent || '').toLowerCase();

      const isBrokenValuation =
        href === '#home-valuation' ||
        href === '/#home-valuation' ||
        href === '#valuation-form' ||
        href === '/home-valuation.php.php';

      if (isBrokenValuation) {
        e.preventDefault();
        window.location.href = '/home-valuation.php';
        return;
      }

      const isContactIntent =
        el.dataset.openConsult === 'true' ||
        /openmodal\(['"]consultation['"]\)/i.test(onclick) ||
        (
          /consult|contact mark|call mark|talk to mark|schedule/i.test(text) &&
          !href.startsWith('tel:') &&
          !href.startsWith('mailto:')
        );

      if (isContactIntent && !href.includes('home-valuation.php')) {
        e.preventDefault();
        openConsultFunnel('cta_consultation');
      }
    });
  }

  window.MarkPiresLeadEngine = {
    submitLead,
    triggerPopup,
    openConsultFunnel,
    closeConsultFunnel
  };

  document.addEventListener('DOMContentLoaded', function () {
    captureUTM();
    injectLeadStyles();
    initValuationForm();
    wireAllForms();
    initCTAInterceptors();
    initExitIntent();
    initStickyBar();
  });
})();