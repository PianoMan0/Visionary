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
    <title>Saved Ideas - Visionary</title>
    <link rel="stylesheet" href="<?=asset_url('css/style.css')?>">
    <style>
        .saved-container { max-width: 900px; margin: 24px auto; padding: 0 16px; }
        .saved-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; margin-top: 20px; }
        .idea-card { background: var(--card); padding: 20px; border-radius: 6px; cursor: pointer; transition: all 0.2s; border: 1px solid transparent; position: relative; }
        .idea-card:hover { border-color: var(--accent); transform: translateY(-2px); }
        .idea-card .unsave-btn { position: absolute; top: 12px; right: 12px; background: #fee; color: #c00; border: 0; border-radius: 3px; padding: 4px 8px; font-size: 12px; cursor: pointer; }
        .idea-card .unsave-btn:hover { background: #fcc; }
        .idea-card h3 { margin: 0 0 8px 0; font-size: 16px; }
        .idea-card p { margin: 0; font-size: 13px; color: var(--muted); line-height: 1.5; }
        .idea-meta { display: flex; gap: 12px; font-size: 12px; color: var(--muted); margin-top: 12px; }
        .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
        .empty-state-emoji { font-size: 48px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <?php render_app_header('Saved Ideas', 'Your bookmarked ideas', $user); ?>

    <main>
        <div class="saved-container">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 16px;">
                <h2 style="margin: 0;">You have <span id="savedCount">0</span> saved ideas</h2>
                <button id="clearSavedBtn" style="padding: 8px 12px; background: #fee; color: #c00; border: 0; border-radius: 4px; cursor: pointer; font-size: 12px;">Clear all</button>
            </div>

            <div id="savedIdeasContainer" class="saved-grid">
                <div class="empty-state">
                    <div class="empty-state-emoji">📭</div>
                    <p>You haven't saved any ideas yet</p>
                    <p style="font-size: 13px;"><a href="ideas.php" style="color: var(--accent);">Explore ideas →</a></p>
                </div>
            </div>
        </div>
    </main>

    <script nonce="<?=$nonce?>">
        // Theme handled centrally by includes/app_layout.php -> render_theme_script()

        function escapeHtml(s) {
            if (s === null || s === undefined) return '';
            return String(s).replace(/[&<>"]+/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'})[c]);
        }

        async function loadSavedIdeas() {
            const saved = JSON.parse(localStorage.getItem('visionary-saved-ideas') || '[]');
            
            if (saved.length === 0) {
                document.getElementById('savedIdeasContainer').innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-emoji">📭</div>
                        <p>You haven't saved any ideas yet</p>
                        <p style="font-size: 13px;"><a href="ideas.php" style="color: var(--accent);">Explore ideas →</a></p>
                    </div>
                `;
                document.getElementById('savedCount').textContent = '0';
                return;
            }

            document.getElementById('savedCount').textContent = saved.length;
            
            // Fetch full idea details
            try {
                const res = await fetch('api.php?action=list&limit=1000', {credentials: 'same-origin'});
                const json = await res.json();
                if (!json.success || !json.data) return;

                const ideas = json.data;
                const savedIdeas = ideas.filter(i => saved.includes(i.id));
                
                document.getElementById('savedIdeasContainer').innerHTML = savedIdeas.map(idea => `
                    <div class="idea-card" data-idea-id="${idea.id}">
                        <button class="unsave-btn" data-idea-id="${idea.id}">Unsave</button>
                        <div class="idea-content">
                            <h3>${escapeHtml(idea.title)}</h3>
                            <p>${escapeHtml((idea.description || '').substring(0, 100))}${(idea.description || '').length > 100 ? '...' : ''}</p>
                            <div class="idea-meta">
                                <span>👤 ${escapeHtml(idea.author_name)}</span>
                                <span>❤ ${escapeHtml(String(idea.likes_count || 0))}</span>
                                <span>💬 ${escapeHtml(String(idea.messages_count || 0))}</span>
                                <span style="text-transform: capitalize;">${escapeHtml(idea.status)}</span>
                            </div>
                        </div>
                    </div>
                `).join('');
            } catch (e) {
                console.error(e);
            }
        }

        function unsaveIdea(ideaId) {
            let saved = JSON.parse(localStorage.getItem('visionary-saved-ideas') || '[]');
            saved = saved.filter(id => id !== ideaId);
            localStorage.setItem('visionary-saved-ideas', JSON.stringify(saved));
            loadSavedIdeas();
        }

        function clearAllSaved() {
            if (confirm('Clear all saved ideas?')) {
                localStorage.setItem('visionary-saved-ideas', '[]');
                loadSavedIdeas();
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadSavedIdeas();
            document.getElementById('clearSavedBtn').addEventListener('click', clearAllSaved);
            document.getElementById('savedIdeasContainer').addEventListener('click', (event) => {
                const unSaveButton = event.target.closest('.unsave-btn');
                if (unSaveButton) {
                    const id = Number(unSaveButton.dataset.ideaId);
                    unsaveIdea(id);
                    return;
                }
                const card = event.target.closest('.idea-card');
                if (card && card.dataset.ideaId) {
                    window.location = 'idea.php?id=' + card.dataset.ideaId;
                }
            });
        });
    </script>
    <?php render_app_footer(); ?>
    <?php render_theme_script(); ?>
</body>
</html>
-->