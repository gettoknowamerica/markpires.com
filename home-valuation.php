<?php
// home-valuation.php — MarkPires.com valuation funnel
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>What's My Home Worth? — Mark Pires · Fairfield County</title>
  <meta name="description" content="Free, personally researched home valuation for Fairfield County properties. Not an algorithm — a real analysis from Mark Pires.">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:Georgia,serif;background:#f8f7f4;color:#1a1a2e;min-height:100vh}
    .nav{background:#1a1a2e;padding:16px 30px;display:flex;align-items:center;justify-content:space-between}
    .nav-brand{color:#c8a96e;font-size:13px;letter-spacing:3px;text-transform:uppercase;text-decoration:none}
    .nav-back{color:rgba(255,255,255,.7);font-size:13px;text-decoration:none}
    .hero{background:#1a1a2e;padding:58px 20px 80px;text-align:center}
    .hero-tag{color:#c8a96e;font-size:11px;letter-spacing:4px;text-transform:uppercase;margin-bottom:15px}
    .hero h1{color:white;font-size:clamp(28px,5vw,48px);line-height:1.2;margin-bottom:15px}
    .hero p{color:rgba(255,255,255,.72);font-size:16px;max-width:540px;margin:0 auto}
    .funnel-wrap{max-width:640px;margin:-40px auto 60px;padding:0 20px}
    .funnel-card{background:white;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.12);overflow:hidden}
    .progress-bar{height:4px;background:#f0ede8}
    .progress-fill{height:100%;background:#c8a96e;transition:width .4s ease;width:33%}
    .step{display:none}.step.active{display:block}
    .step-header{padding:25px 35px 20px;border-bottom:1px solid #f0ede8}
    .step-num{font-size:11px;color:#c8a96e;letter-spacing:3px;text-transform:uppercase;margin-bottom:6px}
    .step-title{font-size:20px;color:#1a1a2e;font-weight:700}
    .step-sub{font-size:14px;color:#888;margin-top:4px;font-style:italic;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
    .step-body{padding:30px 35px}
    .field{margin-bottom:18px}
    .field label{display:block;font-size:12px;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:7px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
    .field input,.field select,.field textarea{width:100%;padding:13px 16px;border:1.5px solid #e0dbd4;border-radius:6px;font-size:15px;font-family:Georgia,serif;color:#1a1a2e;outline:none;background:white}
    .field input:focus,.field select:focus,.field textarea:focus{border-color:#c8a96e}
    .field textarea{height:80px;resize:vertical}
    .choice-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .choice{border:1.5px solid #e0dbd4;border-radius:8px;padding:18px 16px;cursor:pointer;transition:all .2s;text-align:center;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
    .choice:hover,.choice.selected{border-color:#c8a96e;background:#fff8ec}
    .choice-icon{font-size:28px;margin-bottom:8px}
    .choice-label{font-size:13px;font-weight:600;color:#1a1a2e}
    .choice-sub{font-size:11px;color:#888;margin-top:3px}
    .btn-next{width:100%;background:#1a1a2e;color:white;padding:15px;border:none;border-radius:6px;font-size:15px;font-weight:600;letter-spacing:.5px;cursor:pointer;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;margin-top:10px}
    .btn-next:hover{background:#c8a96e}.btn-next:disabled{background:#ccc;cursor:not-allowed}
    .btn-back{background:none;border:none;color:#888;font-size:13px;cursor:pointer;padding:10px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
    .trust-bar{display:flex;gap:20px;justify-content:center;flex-wrap:wrap;padding:20px 35px;background:#f8f7f4;border-top:1px solid #f0ede8}
    .trust-item{display:flex;align-items:center;gap:6px;font-size:12px;color:#888;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
    .trust-icon{color:#c8a96e;font-size:14px}
    .expect{padding:30px 35px;background:#f8f7f4;border-top:1px solid #f0ede8}
    .expect h3{font-size:13px;color:#1a1a2e;letter-spacing:2px;text-transform:uppercase;margin-bottom:15px}
    .expect-item{display:flex;gap:12px;margin-bottom:14px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:13px}
    .expect-num{width:24px;height:24px;border-radius:50%;background:#c8a96e;color:white;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;margin-top:1px}
    .thankyou{text-align:center;padding:50px 35px}
    .thankyou-icon{font-size:56px;margin-bottom:20px}
    .thankyou h2{color:#1a1a2e;font-size:26px;margin-bottom:12px}
    .thankyou p{color:#666;font-size:15px;line-height:1.7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:430px;margin:0 auto 25px}
    .thankyou-meta{background:#f8f7f4;border-radius:8px;padding:20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:13px;color:#888}
    .social-proof{max-width:640px;margin:0 auto 60px;padding:0 20px}
    .review-card{background:white;border-radius:10px;padding:25px;margin-bottom:15px;box-shadow:0 2px 10px rgba(0,0,0,.06)}
    .stars{color:#c8a96e;font-size:16px;margin-bottom:10px}
    .review-text{font-size:14px;color:#555;line-height:1.7;font-style:italic;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
    .review-author{margin-top:12px;font-size:12px;color:#999;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;text-transform:uppercase;letter-spacing:1px}
    @media(max-width:500px){.step-body,.step-header,.trust-bar,.expect{padding-left:20px;padding-right:20px}.choice-grid{grid-template-columns:1fr}.nav{padding:14px 18px}}
  </style><script src="/lead-engine/conversion-tracker.js" defer></script>
</head>
<body>

<nav class="nav">
  <a class="nav-brand" href="/">Mark Pires</a>
  <a class="nav-back" href="/">← Back to Home</a>
</nav>

<div class="hero">
  <div class="hero-tag">Free · No Obligation · Personally Reviewed</div>
  <h1>Find Out What Your Home Could Sell For</h1>
  <p>Start with your address. Jessica may call to confirm details, then Mark's team reviews comparable sales for a smarter local valuation.</p>
</div>

<div class="funnel-wrap">
  <div class="funnel-card">
    <div class="progress-bar"><div class="progress-fill" id="progress"></div></div>

    <div class="step active" id="step-1">
      <div class="step-header">
        <div class="step-num">Step 1 of 3</div>
        <div class="step-title">Enter your property address</div>
        <div class="step-sub">This is the only thing needed to begin.</div>
      </div>
      <div class="step-body">
        <div class="field">
          <label>Property Address</label>
          <input type="text" id="address" placeholder="123 Main St, Fairfield CT" oninput="checkStep1()" autocomplete="street-address">
        </div>
        <button class="btn-next" id="btn1" disabled onclick="goStep(2)">GET MY HOME VALUE →</button>
      </div>
      <div class="trust-bar">
        <div class="trust-item"><span class="trust-icon">✓</span> No spam</div>
        <div class="trust-item"><span class="trust-icon">✓</span> No obligation</div>
        <div class="trust-item"><span class="trust-icon">✓</span> Reviewed by Mark's team</div>
      </div>
    </div>

    <div class="step" id="step-2">
      <div class="step-header">
        <div class="step-num">Step 2 of 3</div>
        <div class="step-title">Where should we send your valuation?</div>
        <div class="step-sub">Jessica, Mark's assistant, may call to confirm details.</div>
      </div>
      <div class="step-body">
        <div class="field">
          <label>Your Name</label>
          <input type="text" id="name" placeholder="First and last name" oninput="checkStep2()" autocomplete="name">
        </div>
        <div class="field">
          <label>Email Address</label>
          <input type="email" id="email" placeholder="your@email.com" oninput="checkStep2()" autocomplete="email">
        </div>
        <div class="field">
          <label>Phone</label>
          <input type="tel" id="phone" placeholder="203-555-0100" oninput="checkStep2()" autocomplete="tel">
        </div>

        <label style="display:block;font-size:12px;line-height:1.5;color:#666;margin:14px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
          <input id="communication_consent" type="checkbox" required checked="checked" onchange="checkStep2()" style="width:auto;margin-right:6px;">
          I agree to be contacted by Mark Pires, Discover Connecticut, and their representatives by phone, text, email, and AI assistant regarding my request.
          Consent is not a condition of purchase.
          <a href="/communication-consent.html" target="_blank" style="color:#c8a96e;">View communication consent.</a>
        </label>

        <button class="btn-next" id="btn2" disabled onclick="goStep(3)">CONTINUE →</button>
        <br><button class="btn-back" onclick="goStep(1)">← Back</button>
      </div>
    </div>

    <div class="step" id="step-3">
      <div class="step-header">
        <div class="step-num">Step 3 of 3</div>
        <div class="step-title">A few details improve the report</div>
        <div class="step-sub">This helps Mark compare your home to the right sales.</div>
      </div>
      <div class="step-body">
        <div class="choice-grid">
          <div class="choice" onclick="selectChoice(this,'prop_type','Single Family')"><div class="choice-icon">🏠</div><div class="choice-label">Single Family</div><div class="choice-sub">House</div></div>
          <div class="choice" onclick="selectChoice(this,'prop_type','Condo / Townhouse')"><div class="choice-icon">🏢</div><div class="choice-label">Condo / Townhouse</div><div class="choice-sub">HOA community</div></div>
          <div class="choice" onclick="selectChoice(this,'prop_type','Multi-Family')"><div class="choice-icon">🏘️</div><div class="choice-label">Multi-Family</div><div class="choice-sub">2–4 units</div></div>
          <div class="choice" onclick="selectChoice(this,'prop_type','Land')"><div class="choice-icon">🌳</div><div class="choice-label">Land / Lot</div><div class="choice-sub">Vacant/buildable</div></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-top:18px">
          <div class="field">
            <label>Bedrooms</label>
            <select id="bedrooms"><option value="">Select</option><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option><option>6+</option></select>
          </div>
          <div class="field">
            <label>Bathrooms</label>
            <select id="bathrooms"><option value="">Select</option><option>1</option><option>1.5</option><option>2</option><option>2.5</option><option>3</option><option>3.5</option><option>4+</option></select>
          </div>
        </div>

        <div class="field">
          <label>Rough Value Range (optional)</label>
          <select id="estimated_value">
            <option value="">Not sure yet</option>
            <option value="300000">Under $400,000</option>
            <option value="450000">$400,000–$500,000</option>
            <option value="600000">$500,000–$750,000</option>
            <option value="875000">$750,000–$1,000,000</option>
            <option value="1250000">$1,000,000–$1,500,000</option>
            <option value="2000000">$1,500,000+</option>
          </select>
        </div>

        <div class="field">
          <label>Selling Timeline</label>
          <select id="timeline">
            <option value="">When are you thinking?</option>
            <option>ASAP — actively looking to sell</option>
            <option>1–3 months</option>
            <option>3–6 months</option>
            <option>6–12 months</option>
            <option>Just curious about value</option>
          </select>
        </div>

        <div class="field">
          <label>Recent renovations? Optional</label>
          <textarea id="renovations" placeholder="New kitchen, roof, bath updates, finished basement..."></textarea>
        </div>

        <div style="background:#fff8ec;border-radius:8px;padding:16px;margin:15px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:13px;color:#666;border-left:3px solid #c8a96e">
          📋 <strong style="color:#1a1a2e">What you'll get:</strong> A personalized report with comparable sales, market timing guidance, and Mark's recommended list price range.
        </div>

        <button class="btn-next" id="btn3" onclick="submitValuation()">GET MY FREE VALUATION →</button>
        <br><button class="btn-back" onclick="goStep(2)">← Back</button>
        <p style="margin-top:15px;font-size:11px;color:#aaa;text-align:center;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">No spam. No obligations. Your info is never sold or shared.</p>
      </div>

      <div class="expect">
        <h3>What happens next</h3>
        <div class="expect-item"><div class="expect-num">1</div><div>Jessica may call shortly to confirm a few property details.</div></div>
        <div class="expect-item"><div class="expect-num">2</div><div>Mark's team reviews comparable sales and local market conditions.</div></div>
        <div class="expect-item"><div class="expect-num">3</div><div>You receive guidance based on real local context, not a generic estimate.</div></div>
      </div>
    </div>

    <div class="step" id="step-thanks">
      <div class="thankyou">
        <div class="thankyou-icon">✅</div>
        <h2>Your request is in, <span id="thanks-name"></span>.</h2>
        <p>Jessica from Mark's team may call shortly to confirm a few details. Mark will review recent comparable sales before providing more accurate guidance.</p>
        <div class="thankyou-meta">
          <strong>What happens next:</strong><br><br>
          📧 Watch your email for confirmation<br>
          📞 Jessica may call to confirm details<br>
          📊 Mark's team reviews comparable sales and market timing
        </div>
        <p style="margin-top:25px"><a href="/" style="color:#c8a96e;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:14px">← Return to markpires.com</a></p>
      </div>
    </div>

  </div>
</div>

<div class="social-proof">
  <div class="review-card">
    <div class="stars">★★★★★</div>
    <div class="review-text">"Mark told me what my house was actually worth — not what I wanted to hear. We priced right, got multiple offers, and closed above list."</div>
    <div class="review-author">Fairfield County Seller</div>
  </div>
  <div class="review-card">
    <div class="stars">★★★★★</div>
    <div class="review-text">"His marketing was unlike anything I'd seen. The valuation and strategy were clear from the start."</div>
    <div class="review-author">Westport Seller</div>
  </div>
</div>

<script>
const state = {
  address: '',
  name: '',
  email: '',
  phone: '',
  prop_type: '',
  bedrooms: '',
  bathrooms: '',
  timeline: '', estimated_value: '',
  renovations: ''
};

function goStep(n) {
  document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
  document.getElementById('step-' + n).classList.add('active');
  document.getElementById('progress').style.width = ({1:'33%',2:'66%',3:'95%'}[n] || '33%');
  window.scrollTo({top:0, behavior:'smooth'});
}

function checkStep1() {
  state.address = document.getElementById('address').value.trim();
  document.getElementById('btn1').disabled = state.address.length < 5;
}

function checkStep2() {
  state.name = document.getElementById('name').value.trim();
  state.email = document.getElementById('email').value.trim();
  state.phone = document.getElementById('phone').value.trim();
  const consent = document.getElementById('communication_consent');
  const validEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(state.email);
  const phoneDigits = state.phone.replace(/\D/g,'');
  document.getElementById('btn2').disabled = !(state.name && validEmail && phoneDigits.length >= 10 && consent && consent.checked);
}

function selectChoice(el, field, value) {
  el.closest('.choice-grid').querySelectorAll('.choice').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
  state[field] = value;
}

async function submitValuation() {
  const btn = document.getElementById('btn3');
  btn.disabled = true;
  btn.textContent = 'Submitting...';

  state.bedrooms = document.getElementById('bedrooms').value;
  state.bathrooms = document.getElementById('bathrooms').value;
  state.timeline = document.getElementById('timeline').value;
  state.estimated_value = document.getElementById('estimated_value').value;
  state.renovations = document.getElementById('renovations').value.trim();

  const payload = {
    type: 'valuation',
    tag: 'home_valuation_address_first',
    name: state.name,
    email: state.email,
    phone: state.phone,
    address: state.address,
    timeline: state.timeline,
    goal: state.prop_type,
    message: [
      state.prop_type && `Property type: ${state.prop_type}`,
      state.bedrooms && `Bedrooms: ${state.bedrooms}`,
      state.bathrooms && `Bathrooms: ${state.bathrooms}`,
      state.renovations && `Renovations: ${state.renovations}`,
      'Consent: User agreed to be contacted by phone, text, email, and AI assistant regarding this request.'
    ].filter(Boolean).join(' | '),
    price_range: '',
    estimated_value: state.estimated_value,
    source: 'markpires.com',
    page_url: window.location.href,
    consent: 'yes',
    consent_text: 'User agreed to be contacted by Mark Pires, Discover Connecticut, and their representatives by phone, text, email, and AI assistant regarding this request. Consent is not a condition of purchase.',
    consent_url: 'https://markpires.com/communication-consent.html'
  };

  try {
    const res = await fetch('/lead-engine/capture.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload)
    });

    const data = await res.json();

    if (!data.success) throw new Error(data.error || 'Unknown error');

    document.getElementById('thanks-name').textContent = (state.name.split(' ')[0] || 'there');
    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
    document.getElementById('step-thanks').classList.add('active');
    document.getElementById('progress').style.width = '100%';

    if (typeof gtag === 'function') {
      gtag('event', 'valuation_submitted', {
        event_category: 'Lead',
        event_label: 'home_valuation_address_first',
        value: 1
      });
    }

    window.scrollTo({top:0, behavior:'smooth'});
  } catch (err) {
    btn.disabled = false;
    btn.textContent = 'GET MY FREE VALUATION →';
    console.error('Valuation submit error:', err);
    alert('Something went wrong. Please call Mark directly at 203-247-2655 or try again.');
  }
}
</script>
</body>
</html>