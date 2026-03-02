// ============================================================
// DRTS Vehicle Registration System - Frontend JS
// University of Botswana | Theo T Kentsheng | 202201306
// ============================================================

const API = '../api';
let currentUser  = null;
let currentToken = null;

// ── Auth helpers ──────────────────────────────────────────────
const Auth = {
  get()     { return JSON.parse(localStorage.getItem('drts_session') || 'null'); },
  set(d)    { localStorage.setItem('drts_session', JSON.stringify(d)); },
  clear()   { localStorage.removeItem('drts_session'); },
  headers() { const s = this.get(); return s ? { Authorization: `Bearer ${s.token}`, 'Content-Type': 'application/json' } : { 'Content-Type': 'application/json' }; },
};

// ── API helper ────────────────────────────────────────────────
async function api(endpoint, options = {}) {
  const res = await fetch(`${API}/${endpoint}`, {
    headers: Auth.headers(),
    ...options,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
  return data;
}

// ── UI helpers ────────────────────────────────────────────────
function showPage(id) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const page = document.getElementById(id);
  if (page) { page.classList.add('active'); window.scrollTo(0,0); }
}

function showAlert(container, msg, type = 'error') {
  const el = document.querySelector(container);
  if (!el) return;
  el.innerHTML = `<div class="alert alert-${type}"><span>${type === 'error' ? '⚠️' : '✅'}</span><span>${msg}</span></div>`;
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function clearAlert(container) {
  const el = document.querySelector(container);
  if (el) el.innerHTML = '';
}

function badge(status) {
  return `<span class="badge badge-${status}">${status.replace('_', ' ')}</span>`;
}

function setLoading(btn, loading) {
  if (loading) {
    btn.dataset.orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner"></span> Please wait…';
    btn.disabled = true;
  } else {
    btn.innerHTML = btn.dataset.orig || btn.innerHTML;
    btn.disabled = false;
  }
}

function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
}

// ── Navbar ────────────────────────────────────────────────────
function updateNav() {
  const session = Auth.get();
  const loggedIn = !!session;
  document.getElementById('nav-login').classList.toggle('hidden', loggedIn);
  document.getElementById('nav-register').classList.toggle('hidden', loggedIn);
  document.getElementById('nav-dashboard').classList.toggle('hidden', !loggedIn);
  document.getElementById('nav-apply').classList.toggle('hidden', !loggedIn);
  document.getElementById('nav-logout').classList.toggle('hidden', !loggedIn);
  const adminNav = document.getElementById('nav-admin');
  if (adminNav) adminNav.classList.toggle('hidden', !(loggedIn && ['officer','admin'].includes(session.user?.role)));
  if (loggedIn) {
    document.getElementById('nav-username').textContent = session.user?.full_name?.split(' ')[0] || 'User';
  }
}

// ── Login ─────────────────────────────────────────────────────
document.getElementById('login-form')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = e.target.querySelector('button[type=submit]');
  clearAlert('#login-alert');
  setLoading(btn, true);
  try {
    const data = await api('login.php', {
      method: 'POST',
      body: JSON.stringify({
        email:    e.target.email.value,
        password: e.target.password.value,
      }),
    });
    Auth.set({ token: data.token, user: data.user });
    updateNav();
    if (['officer','admin'].includes(data.user.role)) {
      showPage('page-admin');
      loadAdminDashboard();
    } else {
      showPage('page-dashboard');
      loadDashboard();
    }
  } catch (err) {
    showAlert('#login-alert', err.message);
  } finally {
    setLoading(btn, false);
  }
});

// ── Register ──────────────────────────────────────────────────
document.getElementById('register-form')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = e.target.querySelector('button[type=submit]');
  clearAlert('#register-alert');

  const pwd = e.target.password.value;
  if (pwd !== e.target.confirm_password.value) {
    showAlert('#register-alert', 'Passwords do not match'); return;
  }

  setLoading(btn, true);
  try {
    await api('register.php', {
      method: 'POST',
      body: JSON.stringify({
        full_name:   e.target.full_name.value,
        email:       e.target.email.value,
        password:    pwd,
        phone:       e.target.phone.value,
        national_id: e.target.national_id.value,
        address:     e.target.address.value,
      }),
    });
    showAlert('#register-alert', 'Account created! Please log in.', 'success');
    setTimeout(() => showPage('page-login'), 1800);
  } catch (err) {
    showAlert('#register-alert', err.message);
  } finally {
    setLoading(btn, false);
  }
});

// ── User Dashboard ────────────────────────────────────────────
async function loadDashboard() {
  const container = document.getElementById('dashboard-applications');
  if (!container) return;
  container.innerHTML = '<div class="text-center mt-2"><span class="spinner"></span></div>';
  try {
    const data = await api('my_applications.php');
    if (data.applications.length === 0) {
      container.innerHTML = `
        <div class="text-center" style="padding:3rem 1rem;color:var(--muted)">
          <div style="font-size:3rem">🚗</div>
          <p style="margin-top:.75rem">No applications yet.</p>
          <button class="btn btn-primary mt-2" onclick="showPage('page-apply')">Apply Now</button>
        </div>`;
      return;
    }
    container.innerHTML = `
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th>Reference</th><th>Vehicle</th><th>Type</th><th>Status</th>
            <th>Fee (BWP)</th><th>Payment</th><th>Submitted</th><th>Action</th>
          </tr></thead>
          <tbody>
            ${data.applications.map(a => `
              <tr>
                <td><code>${a.reference_number}</code></td>
                <td>${a.year} ${a.make} ${a.model}</td>
                <td><span style="text-transform:capitalize">${a.application_type}</span></td>
                <td>${badge(a.status)}</td>
                <td>P ${parseFloat(a.fee).toFixed(2)}</td>
                <td>${badge(a.payment_status || 'pending')}</td>
                <td>${formatDate(a.submitted_at)}</td>
                <td>
                  ${a.status === 'approved' && a.payment_status === 'pending'
                    ? `<button class="btn btn-accent btn-sm" onclick="openPayment(${a.id}, ${a.fee})">Pay Now</button>`
                    : `<button class="btn btn-outline btn-sm" onclick="viewStatus('${a.reference_number}')">Details</button>`}
                </td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>`;
  } catch (err) {
    container.innerHTML = `<div class="alert alert-error">⚠️ ${err.message}</div>`;
  }
}

// ── Status Check ──────────────────────────────────────────────
async function viewStatus(ref) {
  try {
    const data = await api(`check_status.php?ref=${encodeURIComponent(ref)}`);
    const a = data.application;
    document.getElementById('status-modal-body').innerHTML = `
      <div style="display:grid;gap:.75rem">
        <div class="d-flex justify-between"><span class="text-muted">Reference:</span> <strong>${a.reference_number}</strong></div>
        <div class="d-flex justify-between"><span class="text-muted">Vehicle:</span> <strong>${a.year} ${a.make} ${a.model}</strong></div>
        <div class="d-flex justify-between"><span class="text-muted">Type:</span> <span style="text-transform:capitalize">${a.application_type}</span></div>
        <div class="d-flex justify-between"><span class="text-muted">Status:</span> ${badge(a.status)}</div>
        <div class="d-flex justify-between"><span class="text-muted">Fee:</span> P ${parseFloat(a.fee).toFixed(2)}</div>
        ${a.plate_number ? `<div class="d-flex justify-between"><span class="text-muted">Plate Number:</span> <strong style="color:var(--success)">${a.plate_number}</strong></div>` : ''}
        ${a.officer_notes ? `<div class="alert alert-info" style="margin-top:.5rem">📋 Officer notes: ${a.officer_notes}</div>` : ''}
        <div class="d-flex justify-between"><span class="text-muted">Submitted:</span> ${formatDate(a.submitted_at)}</div>
        ${a.approved_at ? `<div class="d-flex justify-between"><span class="text-muted">Approved:</span> ${formatDate(a.approved_at)}</div>` : ''}
      </div>`;
    document.getElementById('status-modal').classList.remove('hidden');
  } catch (err) {
    alert('Error: ' + err.message);
  }
}

// ── Public Status Lookup ──────────────────────────────────────
document.getElementById('status-lookup-btn')?.addEventListener('click', async () => {
  const ref = document.getElementById('ref-input').value.trim();
  if (!ref) return;
  const container = document.getElementById('status-result');
  container.innerHTML = '<span class="spinner"></span>';
  try {
    const data = await api(`check_status.php?ref=${encodeURIComponent(ref)}`);
    const a = data.application;
    container.innerHTML = `
      <div class="card mt-2">
        <div class="card-header">
          <span class="card-title">${a.reference_number}</span>
          ${badge(a.status)}
        </div>
        <div style="display:grid;gap:.6rem;font-size:.9rem">
          <div><span class="text-muted">Vehicle:</span> ${a.year} ${a.make} ${a.model}</div>
          <div><span class="text-muted">Application Type:</span> ${a.application_type}</div>
          <div><span class="text-muted">Fee:</span> P ${parseFloat(a.fee).toFixed(2)} | ${badge(a.payment_status || 'pending')}</div>
          ${a.plate_number ? `<div><span class="text-muted">Plate Number:</span> <strong style="color:var(--success)">${a.plate_number}</strong></div>` : ''}
          ${a.officer_notes ? `<div class="alert alert-info">📋 ${a.officer_notes}</div>` : ''}
          <div><span class="text-muted">Submitted:</span> ${formatDate(a.submitted_at)}</div>
        </div>
      </div>`;
  } catch (err) {
    container.innerHTML = `<div class="alert alert-error">⚠️ ${err.message}</div>`;
  }
});

// ── Multi-step Application Form ───────────────────────────────
let appStep = 1;

function goToStep(n) {
  appStep = n;
  document.querySelectorAll('.app-step-panel').forEach((p, i) => {
    p.classList.toggle('active', i + 1 === n);
  });
  document.querySelectorAll('.step').forEach((s, i) => {
    s.classList.toggle('active', i + 1 === n);
    s.classList.toggle('done', i + 1 < n);
  });
}

document.getElementById('app-next-1')?.addEventListener('click', () => {
  const form = document.getElementById('apply-form');
  const required = ['vehicle_type_id','make','model','year','color','chassis_number','fuel_type','application_type'];
  for (const f of required) {
    if (!form[f]?.value) {
      showAlert('#apply-alert', `Please fill in: ${f.replace('_',' ')}`); return;
    }
  }
  clearAlert('#apply-alert');
  goToStep(2);
});

document.getElementById('app-back-2')?.addEventListener('click', () => goToStep(1));
document.getElementById('app-next-2')?.addEventListener('click', () => goToStep(3));
document.getElementById('app-back-3')?.addEventListener('click', () => goToStep(2));

document.getElementById('apply-form')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('app-submit-btn');
  clearAlert('#apply-alert');
  setLoading(btn, true);
  try {
    const form  = e.target;
    const data  = await api('submit_application.php', {
      method: 'POST',
      body: JSON.stringify({
        vehicle_type_id:  form.vehicle_type_id.value,
        make:             form.make.value,
        model:            form.model.value,
        year:             form.year.value,
        color:            form.color.value,
        chassis_number:   form.chassis_number.value,
        engine_number:    form.engine_number?.value,
        fuel_type:        form.fuel_type.value,
        application_type: form.application_type.value,
      }),
    });
    // Step 3: show success
    document.getElementById('app-ref-number').textContent = data.reference_number;
    document.getElementById('app-fee-amount').textContent  = `P ${data.fee_amount.toFixed(2)}`;
    document.getElementById('app-vehicle-id').value = data.application_id;
    goToStep(3);
  } catch (err) {
    showAlert('#apply-alert', err.message);
    goToStep(1);
  } finally {
    setLoading(btn, false);
  }
});

// ── Document Upload ───────────────────────────────────────────
const docUploadArea = document.getElementById('doc-upload-area');
const docFileInput  = document.getElementById('doc-file-input');

docUploadArea?.addEventListener('click', () => docFileInput?.click());
docFileInput?.addEventListener('change', () => handleDocUpload());

async function handleDocUpload() {
  const file    = docFileInput.files[0];
  const appId   = document.getElementById('app-vehicle-id').value;
  const docType = document.getElementById('doc-type-select').value;
  if (!file || !appId) return;

  const formData = new FormData();
  formData.append('application_id', appId);
  formData.append('document_type', docType);
  formData.append('document', file);

  const btn = document.getElementById('doc-upload-btn');
  setLoading(btn, true);
  try {
    await fetch(`${API}/upload_document.php`, {
      method:  'POST',
      headers: { Authorization: Auth.headers().Authorization },
      body:    formData,
    }).then(r => r.json());
    const list = document.getElementById('uploaded-files-list');
    list.innerHTML += `<div class="uploaded-file">📎 ${file.name} <span style="color:var(--success)">✓</span></div>`;
    docFileInput.value = '';
  } catch (err) {
    showAlert('#apply-alert', err.message);
  } finally {
    setLoading(btn, false);
  }
}

// ── Payment ───────────────────────────────────────────────────
let payingApplicationId = null;

function openPayment(applicationId, amount) {
  payingApplicationId = applicationId;
  document.getElementById('pay-amount').textContent = `P ${parseFloat(amount).toFixed(2)}`;
  document.getElementById('payment-modal').classList.remove('hidden');
}

document.getElementById('pay-confirm-btn')?.addEventListener('click', async () => {
  const btn = document.getElementById('pay-confirm-btn');
  setLoading(btn, true);
  clearAlert('#pay-alert');
  try {
    // Step 1: Create payment intent
    const payData = await api('create_payment.php', {
      method: 'POST',
      body: JSON.stringify({ application_id: payingApplicationId }),
    });

    // Step 2: In a real app, Stripe.js would handle card entry
    // For this demo, we simulate confirmation
    await api('confirm_payment.php', {
      method: 'POST',
      body: JSON.stringify({
        application_id:   payingApplicationId,
        payment_intent_id: payData.payment_intent_id,
      }),
    });

    document.getElementById('payment-modal').classList.add('hidden');
    showAlert('#dashboard-alert', '🎉 Payment confirmed! Your vehicle is now registered.', 'success');
    loadDashboard();
  } catch (err) {
    showAlert('#pay-alert', err.message);
  } finally {
    setLoading(btn, false);
  }
});

// ── Admin Dashboard ───────────────────────────────────────────
async function loadAdminDashboard(status = '', search = '') {
  const container = document.getElementById('admin-table-body');
  const stats     = document.getElementById('admin-stats');
  if (!container) return;
  container.innerHTML = '<tr><td colspan="8" class="text-center"><span class="spinner"></span></td></tr>';
  try {
    let url = 'all_applications.php?limit=200';
    if (status) url += `&status=${status}`;
    if (search) url += `&search=${encodeURIComponent(search)}`;

    const data = await api(url);

    // Stats
    const s = data.stats;
    if (stats) stats.innerHTML = `
      <div class="stat-card"><div class="stat-number">${s.total}</div><div class="stat-label">Total</div></div>
      <div class="stat-card"><div class="stat-number" style="color:var(--warning)">${s.pending}</div><div class="stat-label">Pending</div></div>
      <div class="stat-card"><div class="stat-number" style="color:#1e40af">${s.under_review}</div><div class="stat-label">Under Review</div></div>
      <div class="stat-card"><div class="stat-number" style="color:var(--success)">${s.approved}</div><div class="stat-label">Approved</div></div>
      <div class="stat-card"><div class="stat-number" style="color:var(--danger)">${s.rejected}</div><div class="stat-label">Rejected</div></div>
      <div class="stat-card"><div class="stat-number" style="color:var(--success)">${s.paid}</div><div class="stat-label">Paid</div></div>`;

    if (data.applications.length === 0) {
      container.innerHTML = '<tr><td colspan="8" class="text-center text-muted" style="padding:2rem">No applications found</td></tr>';
      return;
    }

    container.innerHTML = data.applications.map(a => `
      <tr>
        <td><code style="font-size:.78rem">${a.reference_number}</code></td>
        <td>${a.owner_name}<br><small class="text-muted">${a.owner_email}</small></td>
        <td>${a.year} ${a.make} ${a.model}<br><small class="text-muted">${a.chassis_number}</small></td>
        <td><span style="text-transform:capitalize">${a.application_type}</span></td>
        <td>${badge(a.status)}</td>
        <td>${badge(a.payment_status || 'pending')}</td>
        <td>${formatDate(a.submitted_at)}</td>
        <td>
          ${a.status !== 'paid' ? `
            <div class="d-flex gap-1">
              ${a.status !== 'approved' ? `<button class="btn btn-success btn-sm" onclick="adminUpdateStatus(${a.id},'approved')">✓ Approve</button>` : ''}
              ${a.status !== 'rejected' ? `<button class="btn btn-danger btn-sm" onclick="adminUpdateStatus(${a.id},'rejected')">✗ Reject</button>` : ''}
              ${a.status !== 'under_review' ? `<button class="btn btn-outline btn-sm" onclick="adminUpdateStatus(${a.id},'under_review')">🔍 Review</button>` : ''}
            </div>` : '<span class="text-muted" style="font-size:.8rem">Complete</span>'}
        </td>
      </tr>`).join('');
  } catch (err) {
    container.innerHTML = `<tr><td colspan="8"><div class="alert alert-error">⚠️ ${err.message}</div></td></tr>`;
  }
}

async function adminUpdateStatus(applicationId, status) {
  const notes = status === 'rejected' ? prompt('Reason for rejection (optional):') : null;
  try {
    await api('update_status.php', {
      method: 'POST',
      body: JSON.stringify({ application_id: applicationId, status, notes: notes || '' }),
    });
    loadAdminDashboard();
  } catch (err) {
    alert('Error: ' + err.message);
  }
}

document.getElementById('admin-search-btn')?.addEventListener('click', () => {
  const q = document.getElementById('admin-search').value;
  const s = document.getElementById('admin-status-filter').value;
  loadAdminDashboard(s, q);
});

document.getElementById('admin-status-filter')?.addEventListener('change', (e) => {
  loadAdminDashboard(e.target.value, document.getElementById('admin-search').value);
});

// ── Logout ────────────────────────────────────────────────────
document.getElementById('nav-logout')?.addEventListener('click', () => {
  Auth.clear();
  updateNav();
  showPage('page-home');
});

// ── Init ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  updateNav();
  const session = Auth.get();
  if (session) {
    if (['officer','admin'].includes(session.user?.role)) {
      showPage('page-admin');
      loadAdminDashboard();
    } else {
      showPage('page-dashboard');
      loadDashboard();
    }
  } else {
    showPage('page-home');
  }
});

// ── Close modals ──────────────────────────────────────────────
document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
  el.addEventListener('click', (e) => {
    if (e.target === el) {
      document.querySelectorAll('.modal-overlay').forEach(m => m.classList.add('hidden'));
    }
  });
});
