document.addEventListener('DOMContentLoaded', () => {
  const listEl = document.getElementById('list');
  const form = document.getElementById('ideaForm');
  let CURRENT_USER = window.CURRENT_USER || null;

  async function fetchCurrentUser() {
    if (CURRENT_USER === null) {
      try {
        const res = await fetch('api.php?action=current_user', {credentials: 'same-origin'});
        const json = await res.json();
        if (json.success) CURRENT_USER = json.data;
      } catch (err) {
        console.error('fetchCurrentUser error', err);
      }
    }
    return CURRENT_USER;
  }

  async function fetchList() {
    try {
      const res = await fetch('api.php?action=list', {credentials: 'same-origin'});
      const json = await res.json();
      if (json && json.success) renderList(json.data);
      else console.error('fetchList failed', json);
    } catch (err) {
      console.error('fetchList error', err);
      listEl.innerHTML = '<p>Failed to load ideas (check console)</p>';
    }
  }

  function renderList(items) {
    listEl.innerHTML = '';
    if (!items.length) {
      listEl.innerHTML = '<p>No ideas yet. Be the first!</p>';
      return;
    }
    items.forEach(item => {
      const card = document.createElement('div');
      card.className = 'idea';
      card.innerHTML = `
        <h3>${escapeHtml(item.title)}</h3>
        <p class="meta">By ${escapeHtml(item.author_name || 'Anonymous')} — <strong>${escapeHtml(item.status)}</strong>
          ${item.developer_name ? ' — Dev: ' + escapeHtml(item.developer_name) : ''}
        </p>
        <p>${escapeHtml(item.description || '')}</p>
      `;
      const actions = document.createElement('div');
      actions.className = 'actions';
      if (item.status === 'open') {
        const claimBtn = document.createElement('button');
        claimBtn.textContent = 'Claim as dev';
        claimBtn.onclick = () => claim(item.id);
        actions.appendChild(claimBtn);
      }
      if (item.status === 'in_progress') {
        const completeBtn = document.createElement('button');
        completeBtn.textContent = 'Mark complete';
        completeBtn.onclick = () => complete(item.id);
        actions.appendChild(completeBtn);
      }
      card.appendChild(actions);
      listEl.appendChild(card);
    });
  }

  function escapeHtml(s) {
    if (!s) return '';
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const title = document.getElementById('title').value.trim();
    if (!title) return alert('Title is required');
    const user = await fetchCurrentUser();
    if (!user) return alert('You must be signed in to post an idea.');
    if (!['poster','both'].includes(user.role)) return alert('Your account is not allowed to post ideas.');
    const data = { title, description: document.getElementById('description').value.trim() };
    try {
      const res = await fetch('api.php?action=create', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(data)
      });
      const json = await res.json();
      if (json.success) {
        form.reset();
        fetchList();
      } else {
        alert(json.error || 'Failed');
      }
    } catch (err) {
      console.error('create error', err);
      alert('Network or server error creating idea (see console)');
    }
  });

  async function claim(id) {
    const user = await fetchCurrentUser();
    if (!user) return alert('Sign in as developer to claim');
    if (!['dev','both'].includes(user.role)) return alert('Only developers can claim ideas');
    try {
      const res = await fetch('api.php?action=claim', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type':'application/json'}, body: JSON.stringify({id})});
      const json = await res.json();
      if (json.success) fetchList(); else alert(json.error || 'Failed');
    } catch (err) {
      console.error('claim error', err);
      alert('Network or server error claiming idea (see console)');
    }
  }

  async function complete(id) {
    if (!confirm('Mark this idea as completed?')) return;
    try {
      const res = await fetch('api.php?action=complete', {method:'POST', credentials: 'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id})});
      const json = await res.json();
      if (json.success) fetchList(); else alert(json.error || 'Failed');
    } catch (err) {
      console.error('complete error', err);
      alert('Network or server error completing idea (see console)');
    }
  }

  fetchList();
});
