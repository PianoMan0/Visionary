<!-----WIP-----
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/app_layout.php';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - Visionary</title>
    <link rel="stylesheet" href="<?=asset_url('css/style.css')?>">
    <style>
        .leaderboard-container { max-width: 900px; margin: 24px auto; padding: 0 16px; }
        .tabs { display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #eee; }
        body.dark .tabs { border-bottom-color: rgba(255,255,255,0.1); }
        .tab-btn { padding: 12px 16px; background: transparent; border: none; cursor: pointer; font-size: 14px; color: var(--muted); border-bottom: 2px solid transparent; }
        .tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }
        .leaderboard-list { background: var(--card); border-radius: 6px; }
        .leaderboard-item { padding: 16px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 16px; }
        body.dark .leaderboard-item { border-bottom-color: rgba(255,255,255,0.1); }
        .leaderboard-item:last-child { border-bottom: none; }
        .rank { font-weight: bold; font-size: 18px; min-width: 30px; text-align: center; }
        .rank.first { color: #fbbf24; font-size: 20px; }
        .rank.second { color: #a8a9ad; }
        .rank.third { color: #cd7f32; }
        .user-info { flex: 1; }
        .user-name { font-weight: bold; margin-bottom: 4px; }
        .user-stats { font-size: 12px; color: var(--muted); }
        .medal { margin-right: 8px; }
        .stat-value { font-weight: bold; font-size: 16px; color: var(--accent); min-width: 60px; text-align: right; }
        .time-filter { display: flex; gap: 8px; margin-bottom: 16px; }
        .time-filter button { padding: 6px 12px; border: 1px solid #ddd; background: transparent; border-radius: 4px; cursor: pointer; font-size: 12px; }
        body.dark .time-filter button { border-color: rgba(255,255,255,0.2); }
        .time-filter button.active { background: var(--accent); color: white; border-color: var(--accent); }
    </style>
</head>
<body>
    <?php render_app_header('Leaderboard', 'Top creators & contributors', $user); ?>

    <main>
        <div class="leaderboard-container">
            <div class="time-filter">
                <button class="time-btn active" data-period="alltime">All Time</button>
                <button class="time-btn" data-period="month">This Month</button>
                <button class="time-btn" data-period="week">This Week</button>
            </div>

            <div class="tabs">
                <button class="tab-btn active" data-tab="ideas">💡 Most Ideas</button>
                <button class="tab-btn" data-tab="engagement">❤️ Most Liked</button>
                <button class="tab-btn" data-tab="finished">✅ Most Completed</button>
                <button class="tab-btn" data-tab="active">🔥 Most Active</button>
            </div>

            <div id="leaderboardContent" class="leaderboard-list">
                <div style="padding: 40px; text-align: center; color: var(--muted);">Loading leaderboard...</div>
            </div>
        </div>
    </main>

    <script nonce="<?=$nonce?>">
        // Theme handled centrally by includes/app_layout.php -> render_theme_script()

        function escapeHtml(s) {
            if (s === null || s === undefined) return '';
            return String(s).replace(/[&<>"]+/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'})[c]);
        }

        let currentTab = 'ideas';
        let currentPeriod = 'alltime';

        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentTab = btn.dataset.tab;
                loadLeaderboard();
            });
        });

        document.querySelectorAll('.time-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.time-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentPeriod = btn.dataset.period;
                loadLeaderboard();
            });
        });

        async function loadLeaderboard() {
            try {
                const res = await fetch('api.php?action=list&limit=1000', {credentials: 'same-origin'});
                const json = await res.json();
                
                if (!json.success || !json.data) return;

                const ideas = json.data;
                const now = new Date();
                
                // Filter by period
                let filtered = ideas;
                if (currentPeriod === 'month') {
                    const oneMonthAgo = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
                    filtered = ideas.filter(i => new Date(i.created_at) > oneMonthAgo);
                } else if (currentPeriod === 'week') {
                    const oneWeekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                    filtered = ideas.filter(i => new Date(i.created_at) > oneWeekAgo);
                }

                // Group by author
                const stats = {};
                filtered.forEach(idea => {
                    if (!stats[idea.author_name]) {
                        stats[idea.author_name] = {
                            name: idea.author_name,
                            ideas: 0,
                            completed: 0,
                            engagement: 0,
                            lastActive: new Date(idea.created_at)
                        };
                    }
                    stats[idea.author_name].ideas++;
                    stats[idea.author_name].engagement += idea.likes_count || 0;
                    if (idea.status === 'completed') stats[idea.author_name].completed++;
                    if (new Date(idea.created_at) > stats[idea.author_name].lastActive) {
                        stats[idea.author_name].lastActive = new Date(idea.created_at);
                    }
                });

                // Convert to array and sort
                let ranked = Object.values(stats);

                if (currentTab === 'ideas') {
                    ranked.sort((a, b) => b.ideas - a.ideas);
                } else if (currentTab === 'engagement') {
                    ranked.sort((a, b) => b.engagement - a.engagement);
                } else if (currentTab === 'finished') {
                    ranked.sort((a, b) => b.completed - a.completed);
                } else if (currentTab === 'active') {
                    ranked.sort((a, b) => b.lastActive - a.lastActive);
                }

                // Render
                const getMedal = (rank) => {
                    if (rank === 0) return '🥇';
                    if (rank === 1) return '🥈';
                    if (rank === 2) return '🥉';
                    return `#${rank + 1}`;
                };

                const getRankClass = (rank) => {
                    if (rank === 0) return 'first';
                    if (rank === 1) return 'second';
                    if (rank === 2) return 'third';
                    return '';
                };

                document.getElementById('leaderboardContent').innerHTML = ranked.slice(0, 50).map((user, idx) => {
                    let statValue = 0;
                    let statLabel = '';
                    if (currentTab === 'ideas') {
                        statValue = user.ideas;
                        statLabel = 'ideas';
                    } else if (currentTab === 'engagement') {
                        statValue = user.engagement;
                        statLabel = 'likes';
                    } else if (currentTab === 'finished') {
                        statValue = user.completed;
                        statLabel = 'completed';
                    } else if (currentTab === 'active') {
                        statValue = user.ideas;
                        statLabel = 'active';
                    }

                    return `
                        <div class="leaderboard-item">
                            <div class="rank ${getRankClass(idx)}">${getMedal(idx)}</div>
                            <div class="user-info">
                                <div class="user-name"><a href="profile.php?username=${encodeURIComponent(user.name)}" style="color: inherit; text-decoration: none;">${escapeHtml(user.name)}</a></div>
                                <div class="user-stats">${escapeHtml(String(user.ideas))} ideas • ${escapeHtml(String(user.engagement))} likes • ${escapeHtml(String(user.completed))} completed</div>
                            </div>
                            <div class="stat-value">${escapeHtml(String(statValue))}</div>
                        </div>
                    `;
                }).join('');

                if (ranked.length === 0) {
                    document.getElementById('leaderboardContent').innerHTML = '<div style="padding: 40px; text-align: center; color: var(--muted);">No data for this period</div>';
                }
            } catch (e) {
                console.error(e);
            }
        }

        document.addEventListener('DOMContentLoaded', loadLeaderboard);
    </script>
    <?php render_app_footer(); ?>
    <?php render_theme_script(); ?>
</body>
</html>
-->