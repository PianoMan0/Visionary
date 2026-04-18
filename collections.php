<!-----WIP-----
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/app_layout.php';
$user = current_user();

if (!$user) {
    http_response_code(401);
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collections - Visionary</title>
    <link rel="stylesheet" href="<?=asset_url('css/style.css')?>">
    <style>
        .collections-container { max-width: 1000px; margin: 24px auto; padding: 0 16px; }
        .collection-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 16px; }
        .collection-header button { padding: 10px 16px; background: var(--accent); color: white; border: 0; border-radius: 4px; cursor: pointer; }
        .collections-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; }
        .collection-card { background: var(--card); padding: 20px; border-radius: 6px; cursor: pointer; transition: all 0.2s; border: 1px solid transparent; }
        .collection-card:hover { border-color: var(--accent); transform: translateY(-2px); }
        .collection-card h3 { margin: 0 0 8px 0; font-size: 16px; }
        .collection-card p { margin: 0; font-size: 13px; color: var(--muted); }
        .collection-meta { display: flex; gap: 12px; font-size: 12px; color: var(--muted); margin-top: 12px; }
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.open { display: flex; }
        .modal-content { background: var(--card); padding: 24px; border-radius: 6px; max-width: 400px; width: 90%; }
        .modal-content h2 { margin-top: 0; }
        .modal-content input, .modal-content textarea { width: 100%; padding: 8px 12px; margin: 8px 0; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
        body.dark .modal-content input, body.dark .modal-content textarea { border-color: rgba(255,255,255,0.1); }
        .modal-buttons { display: flex; gap: 8px; margin-top: 16px; }
        .modal-buttons button { flex: 1; padding: 10px; border: 0; border-radius: 4px; cursor: pointer; }
        .modal-buttons .btn-primary { background: var(--accent); color: white; }
        .modal-buttons .btn-secondary { background: #eee; }
        body.dark .modal-buttons .btn-secondary { background: rgba(255,255,255,0.1); }
    </style>
</head>
<body>
    <?php render_app_header('Collections', 'Organize and share your ideas', $user); ?>

    <main>
        <div class="collections-container">
            <div class="collection-header">
                <input type="text" id="searchInput" placeholder="Search collections..." style="flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 4px;" />
                <button id="newCollectionBtn">+ New Collection</button>
            </div>

            <div id="collectionsGrid" class="collections-grid">
                <p style="grid-column: 1/-1; text-align: center; color: var(--muted);">Loading collections...</p>
            </div>
        </div>
    </main>

    <div class="modal" id="createModal">
        <div class="modal-content">
            <h2>Create Collection</h2>
            <input type="text" id="collName" placeholder="Collection name" />
            <textarea id="collDesc" placeholder="Description" style="resize: vertical; min-height: 80px;"></textarea>
            <div style="font-size: 12px; color: var(--muted); margin: 8px 0;">
                You can add ideas to this collection after creating it
            </div>
            <div class="modal-buttons">
                <button id="createCollectionBtn" class="btn-primary">Create</button>
                <button id="cancelCreateBtn" class="btn-secondary">Cancel</button>
            </div>
        </div>
    </div>

    <script nonce="<?=$nonce?>">
        // Theme handled centrally by includes/app_layout.php -> render_theme_script()

        function escapeHtml(s) {
            if (s === null || s === undefined) return '';
            return String(s).replace(/[&<>"]+/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'})[c]);
        }

        function openCreateModal() {
            document.getElementById('createModal').classList.add('open');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.remove('open');
            document.getElementById('collName').value = '';
            document.getElementById('collDesc').value = '';
        }

        async function createCollection() {
            const name = document.getElementById('collName').value.trim();
            const desc = document.getElementById('collDesc').value.trim();

            if (!name) {
                alert('Please enter a collection name');
                return;
            }

            // Store in localStorage (demo version)
            const collections = JSON.parse(localStorage.getItem('visionary-collections') || '[]');
            collections.push({
                id: Date.now(),
                name,
                description: desc,
                ideas: [],
                created_at: new Date().toISOString()
            });
            localStorage.setItem('visionary-collections', JSON.stringify(collections));

            closeCreateModal();
            loadCollections();
        }

        async function loadCollections() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const collections = JSON.parse(localStorage.getItem('visionary-collections') || '[]');

            let filtered = collections;
            if (search) {
                filtered = collections.filter(c => 
                    c.name.toLowerCase().includes(search) || 
                    c.description.toLowerCase().includes(search)
                );
            }

            if (filtered.length === 0) {
                document.getElementById('collectionsGrid').innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--muted);">
                        <p style="font-size: 16px;">No collections yet</p>
                        <p id="createFirstCollection" style="font-size: 13px; cursor: pointer; color: var(--accent);">Create your first collection →</p>
                    </div>
                `;
                document.getElementById('createFirstCollection').addEventListener('click', openCreateModal);
                return;
            }

            document.getElementById('collectionsGrid').innerHTML = filtered.map(coll => `
                <div class="collection-card" data-collection-id="${coll.id}">
                    <h3>${escapeHtml(coll.name)}</h3>
                    <p>${escapeHtml(coll.description)}</p>
                    <div class="collection-meta">
                        <span>📌 ${escapeHtml(String(coll.ideas ? coll.ideas.length : 0))} ideas</span>
                        <span>✓ Created ${escapeHtml(new Date(coll.created_at).toLocaleDateString())}</span>
                    </div>
                </div>
            `).join('');
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadCollections();
            document.getElementById('searchInput').addEventListener('input', loadCollections);
            document.getElementById('newCollectionBtn').addEventListener('click', openCreateModal);
            document.getElementById('createCollectionBtn').addEventListener('click', createCollection);
            document.getElementById('cancelCreateBtn').addEventListener('click', closeCreateModal);

            document.getElementById('collectionsGrid').addEventListener('click', e => {
                const card = e.target.closest('.collection-card');
                if (card && card.dataset.collectionId) {
                    alert('Collection view coming soon!');
                }
            });
        });

        document.getElementById('createModal').addEventListener('click', e => {
            if (e.target.id === 'createModal') closeCreateModal();
        });
    </script>
    <?php render_app_footer(); ?>
    <?php render_theme_script(); ?>
</body>
</html>
--->