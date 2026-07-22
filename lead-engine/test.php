<!DOCTYPE html>
<html>
<head>
  <title>Lead Engine Test</title>
</head>
<body>
  <h1>Lead Engine Test</h1>

  <form id="testForm">
    <input name="name" placeholder="Name" value="Test Lead"><br><br>
    <input name="email" placeholder="Email" value="test@example.com"><br><br>
    <input name="phone" placeholder="Phone" value="2035551212"><br><br>
    <input name="address" placeholder="Address" value="123 Main Street Fairfield CT"><br><br>
    <input name="type" value="valuation"><br><br>
    <input name="timeline" value="3-6 months"><br><br>
    <input name="estimated_value" value="750000"><br><br>
    <button type="submit">Send Test Lead</button>
  </form>

  <pre id="result"></pre>

  <script>
    document.getElementById('testForm').addEventListener('submit', async function(e) {
      e.preventDefault();

      const fd = new FormData(this);
      const payload = {};
      fd.forEach((v, k) => payload[k] = v);

      const res = await fetch('/lead-engine/capture.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
      });

      const data = await res.json();
      document.getElementById('result').textContent = JSON.stringify(data, null, 2);
    });
  </script>
</body>
</html>