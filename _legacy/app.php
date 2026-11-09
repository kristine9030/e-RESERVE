<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Data Clean Mode</title>
  <style>
    :root {
      --bg: #f9fafb;
      --card: #ffffff;
      --text: #111827;
      --muted: #6b7280;
      --border: #e5e7eb;
      --primary: #6366f1;
      --primary-dark: #4f46e5;
      --success: #10b981;
      --warning: #f59e0b;
      --danger: #ef4444;
      --shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
    }

    body.dark-mode {
      --bg: #1f2433;
      --card: #2d2d3d;
      --text: #e0e0e0;
      --muted: #a8b0c0;
      --border: #3d4354;
      --primary: #818cf8;
      --primary-dark: #6366f1;
      --success: #34d399;
      --warning: #fbbf24;
      --danger: #f87171;
      --shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
    }

    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      background: var(--bg);
      color: var(--text);
      transition: background 0.2s ease, color 0.2s ease;
    }

    button, select, input { font: inherit; }

    .data-clean-mode {
      min-height: 100vh;
      padding: 24px;
      background: linear-gradient(135deg, rgba(99,102,241,0.08), rgba(16,185,129,0.06));
    }

    .clean-ribbon {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
      justify-content: space-between;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 16px 18px;
      box-shadow: var(--shadow);
      margin-bottom: 16px;
    }

    .ribbon-left, .ribbon-right { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

    .action-btn {
      border: 1px solid var(--border);
      background: var(--card);
      color: var(--text);
      padding: 8px 12px;
      border-radius: 10px;
      cursor: pointer;
      transition: 0.2s ease;
    }

    .action-btn:hover { transform: translateY(-1px); border-color: var(--primary); }
    .action-btn.primary { background: var(--primary); color: white; border-color: var(--primary); }
    .action-btn.danger { background: var(--danger); color: white; border-color: var(--danger); }

    .pipeline-stepper {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 16px;
    }

    .stepper-item {
      padding: 10px 14px;
      border-radius: 999px;
      background: var(--card);
      border: 1px solid var(--border);
      color: var(--muted);
      font-size: 0.95rem;
      display: flex;
      align-items: center;
      gap: 8px;
      min-width: 120px;
      justify-content: center;
    }

    .stepper-item.active {
      color: white;
      background: linear-gradient(135deg, var(--primary), #8b5cf6);
      border-color: transparent;
    }

    .stepper-item.completed {
      color: var(--success);
      border-color: rgba(16,185,129,0.3);
    }

    .clean-content {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 320px;
      gap: 16px;
      align-items: start;
    }

    .clean-content-main, .clean-sidebar {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 18px;
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .clean-content-main { padding: 18px; }
    .clean-sidebar { padding: 0; }

    .quality-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 12px;
      margin-bottom: 16px;
    }

    .quality-metric {
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 14px;
      background: rgba(99,102,241,0.05);
    }

    .metric-label { color: var(--muted); font-size: 0.9rem; }
    .metric-value { font-size: 1.3rem; font-weight: 700; margin-top: 6px; }
    .metric-value.success { color: var(--success); }
    .metric-value.warning { color: var(--warning); }
    .metric-value.danger { color: var(--danger); }

    .table-wrap { overflow: auto; border: 1px solid var(--border); border-radius: 12px; }
    table { width: 100%; border-collapse: collapse; min-width: 640px; }
    th, td { padding: 10px 12px; border-bottom: 1px solid var(--border); text-align: left; }
    th { position: sticky; top: 0; background: var(--card); z-index: 1; }
    tr:hover { background: rgba(99,102,241,0.05); }
    .cell-null { color: var(--danger); font-weight: 600; }

    .clean-panel { border-bottom: 1px solid var(--border); }
    .clean-panel:last-child { border-bottom: none; }
    .panel-header {
      display: flex; justify-content: space-between; align-items: center; width: 100%; padding: 14px 16px;
      background: transparent; border: 0; color: var(--text); cursor: pointer; text-align: left;
    }
    .panel-header:hover { background: rgba(99,102,241,0.05); }
    .panel-body { padding: 0 16px 14px; color: var(--muted); }
    .clean-panel.collapsed .panel-body { display: none; }

    .pill {
      display: inline-block; font-size: 0.82rem; padding: 5px 8px; border-radius: 999px; background: rgba(99,102,241,0.12); color: var(--primary); margin-top: 8px;
    }

    .history-list { display: grid; gap: 8px; }
    .history-item { border: 1px solid var(--border); border-radius: 10px; padding: 8px 10px; }
    .toast {
      position: fixed; right: 24px; bottom: 24px; background: var(--card); color: var(--text); border: 1px solid var(--border); border-left: 4px solid var(--primary); padding: 12px 14px; border-radius: 12px; box-shadow: var(--shadow); opacity: 0; pointer-events: none; transform: translateY(8px); transition: all 0.25s ease;
    }
    .toast.show { opacity: 1; transform: translateY(0); }

    @media (max-width: 1000px) {
      .clean-content { grid-template-columns: 1fr; }
      .quality-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 640px) {
      .data-clean-mode { padding: 12px; }
      .quality-grid { grid-template-columns: 1fr; }
      .clean-ribbon { flex-direction: column; align-items: stretch; }
    }
  </style>
</head>
<body>
  <div class="data-clean-mode">
    <div class="clean-ribbon">
      <div class="ribbon-left">
        <button class="action-btn primary">Detect Issues</button>
        <button class="action-btn">Missing Values</button>
        <button class="action-btn">Remove Duplicates</button>
        <button class="action-btn">Outlier Detection</button>
      </div>
      <div class="ribbon-right">
        <button class="action-btn">Auto Clean</button>
        <button class="action-btn">Save Workflow</button>
        <button class="action-btn danger">Validate Data</button>
        <button class="action-btn" onclick="toggleTheme()">Toggle Theme</button>
      </div>
    </div>

    <div class="pipeline-stepper">
      <div class="stepper-item completed">✓ Import</div>
      <div class="stepper-item active">● Detect Issues</div>
      <div class="stepper-item">Clean</div>
      <div class="stepper-item">Validate</div>
      <div class="stepper-item">Export</div>
    </div>

    <div class="clean-content">
      <section class="clean-content-main">
        <div class="quality-grid">
          <div class="quality-metric">
            <div class="metric-label">Total Rows</div>
            <div class="metric-value">1,248</div>
          </div>
          <div class="quality-metric">
            <div class="metric-label">Completeness</div>
            <div class="metric-value success">92.4%</div>
          </div>
          <div class="quality-metric">
            <div class="metric-label">Missing Cells</div>
            <div class="metric-value danger">37</div>
          </div>
          <div class="quality-metric">
            <div class="metric-label">Duplicates</div>
            <div class="metric-value warning">14</div>
          </div>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Customer</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Region</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr><td>Ana Flores</td><td>ana@example.com</td><td>+1 555 0100</td><td>North</td><td>Active</td></tr>
              <tr><td>Ben Carter</td><td class="cell-null">—</td><td>+1 555 0101</td><td>South</td><td>Pending</td></tr>
              <tr><td>Clara Ng</td><td>clara@example.com</td><td class="cell-null">—</td><td>West</td><td>Active</td></tr>
              <tr><td>Diego Ortiz</td><td>diego@example.com</td><td>+1 555 0103</td><td>East</td><td>Active</td></tr>
              <tr><td>Elias Reed</td><td>elias@example.com</td><td>+1 555 0104</td><td>North</td><td>Archived</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <aside class="clean-sidebar">
        <div class="clean-panel">
          <button class="panel-header" type="button" onclick="togglePanel(this)">
            <span>Column Profile</span><span>▾</span>
          </button>
          <div class="panel-body">
            <label for="columnSelect">Column</label>
            <select id="columnSelect" style="width:100%; margin-top:6px; padding:8px; border-radius:8px; border:1px solid var(--border);">
              <option>Email</option>
              <option>Phone</option>
              <option>Region</option>
            </select>
            <div class="pill">Type: text</div>
            <div class="pill">Null Count: 1</div>
            <div class="pill">Unique Values: 4</div>
          </div>
        </div>

        <div class="clean-panel">
          <button class="panel-header" type="button" onclick="togglePanel(this)">
            <span>Cleaning Actions</span><span>▾</span>
          </button>
          <div class="panel-body">
            <button class="action-btn" style="margin-bottom:8px; width:100%;">Fill Missing</button>
            <button class="action-btn" style="margin-bottom:8px; width:100%;">Remove Duplicates</button>
            <button class="action-btn" style="width:100%;">Change Data Type</button>
          </div>
        </div>

        <div class="clean-panel">
          <button class="panel-header" type="button" onclick="togglePanel(this)">
            <span>Validation</span><span>▾</span>
          </button>
          <div class="panel-body">
            <label><input type="checkbox" checked /> Email format</label><br />
            <label><input type="checkbox" checked /> Required fields</label><br />
            <label><input type="checkbox" /> URL pattern</label><br />
            <button class="action-btn primary" style="margin-top:8px;">Validate Now</button>
          </div>
        </div>

        <div class="clean-panel">
          <button class="panel-header" type="button" onclick="togglePanel(this)">
            <span>History</span><span>▾</span>
          </button>
          <div class="panel-body">
            <div class="history-list">
              <div class="history-item">Filled 2 missing email values</div>
              <div class="history-item">Removed duplicate customer record</div>
              <div class="history-item">Normalized region names</div>
            </div>
          </div>
        </div>
      </aside>
    </div>
  </div>

  <div id="toast" class="toast"></div>

  <script>
    function toggleTheme() {
      document.body.classList.toggle('dark-mode');
      showToast(document.body.classList.contains('dark-mode') ? 'Dark mode enabled' : 'Light mode enabled');
    }

    function togglePanel(button) {
      const panel = button.closest('.clean-panel');
      panel.classList.toggle('collapsed');
      const icon = button.querySelector('span:last-child');
      icon.textContent = panel.classList.contains('collapsed') ? '▸' : '▾';
    }

    function showToast(message) {
      const toast = document.getElementById('toast');
      toast.textContent = message;
      toast.classList.add('show');
      clearTimeout(showToast.timer);
      showToast.timer = setTimeout(() => toast.classList.remove('show'), 1800);
    }

    document.querySelectorAll('.action-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        if (btn.textContent.trim()) {
          showToast(btn.textContent.trim() + ' clicked');
        }
      });
    });
  </script>
</body>
</html>
