<?php
require_once '../includes/auth.php';
require_once '../modules/queue_module.php';

requireRole(['admin', 'receptionist', 'doctor']);

$refreshSeconds = 8;
$lanes = getQueueDisplayData($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Display - Cryptalis Clinic</title>
    <meta http-equiv="refresh" content="<?= $refreshSeconds ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f3f5fa;
            --card: #ffffff;
            --text: #1f2a44;
            --muted: #7c8aa5;
            --brand: #e33b2f;
            --border: #e3e8f2;
            --shadow: 0 14px 30px rgba(24, 39, 75, 0.08);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, sans-serif;
            background: radial-gradient(circle at 10% 10%, #fff8f8 0%, var(--bg) 55%);
            color: var(--text);
        }
        .wrap { padding: 28px; }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 12px;
        }
        .title { font-size: 2rem; font-weight: 800; margin: 0; }
        .meta { color: var(--muted); font-weight: 600; }
        .controls { display:flex; gap:8px; align-items:center; }
        .controls button {
            border:1px solid var(--border);
            background:#fff;
            color:var(--text);
            border-radius:999px;
            padding:8px 14px;
            font-weight:700;
            cursor:pointer;
        }
        .controls button.primary {
            background:#ffebe8;
            border-color:#ffd6d1;
            color:#b42318;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 18px;
        }
        .lane {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .lane-head {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .doctor { font-size: 1.1rem; font-weight: 700; }
        .badge {
            padding: 6px 10px;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 700;
            background: #ffebe8;
            color: #c42c22;
        }
        .lane-body { padding: 16px; display: grid; gap: 14px; }
        .panel {
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px;
            background: #fcfdff;
        }
        .panel h4 {
            margin: 0 0 10px;
            font-size: .82rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
        }
        .token {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--brand);
            line-height: 1;
        }
        .name { margin-top: 6px; font-size: 1rem; font-weight: 600; }
        .waiting-list {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 8px;
        }
        .waiting-list li {
            display: flex;
            justify-content: space-between;
            padding: 9px 10px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            font-size: .92rem;
        }
        .empty {
            border: 1px dashed var(--border);
            border-radius: 12px;
            padding: 18px;
            text-align: center;
            color: var(--muted);
            font-weight: 600;
            background: rgba(255,255,255,.6);
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <h1 class="title">Cryptalis Clinic Queue Display</h1>
            <div class="controls">
                <div class="meta"><?= date('l, F j, Y h:i A') ?> &middot; Auto refresh <?= $refreshSeconds ?>s</div>
                <button id="soundToggle" class="primary" type="button">Sound: Off</button>
                <button id="fullscreenToggle" type="button">Fullscreen</button>
            </div>
        </div>

        <?php if (empty($lanes)): ?>
            <div class="empty">No doctor lanes available for today yet.</div>
        <?php else: ?>
        <div class="grid">
            <?php foreach ($lanes as $lane): ?>
            <section class="lane">
                <div class="lane-head">
                    <div class="doctor">Dr. <?= htmlspecialchars($lane['doctor_name']) ?></div>
                    <span class="badge">Live</span>
                </div>
                <div class="lane-body">
                    <div class="panel">
                        <h4>Now Serving</h4>
                        <?php if (!empty($lane['now_serving'])): ?>
                            <div class="token"><?= htmlspecialchars(formatQueueToken((int)($lane['now_serving']['token_no'] ?? 0))) ?></div>
                            <div class="name"><?= htmlspecialchars($lane['now_serving']['patient_name']) ?></div>
                        <?php else: ?>
                            <div class="empty">Waiting for call</div>
                        <?php endif; ?>
                    </div>

                    <div class="panel">
                        <h4>Next Up</h4>
                        <?php if (!empty($lane['next_up'])): ?>
                            <div class="token"><?= htmlspecialchars(formatQueueToken((int)($lane['next_up']['token_no'] ?? 0))) ?></div>
                            <div class="name"><?= htmlspecialchars($lane['next_up']['patient_name']) ?></div>
                        <?php else: ?>
                            <div class="empty">No one waiting</div>
                        <?php endif; ?>
                    </div>

                    <div class="panel">
                        <h4>Waiting Queue</h4>
                        <?php if (!empty($lane['waiting'])): ?>
                        <ul class="waiting-list">
                            <?php foreach (array_slice($lane['waiting'], 0, 6) as $w): ?>
                            <li>
                                <span><?= htmlspecialchars(formatQueueToken((int)($w['token_no'] ?? 0))) ?> - <?= htmlspecialchars($w['patient_name']) ?></span>
                                <span><?= date('h:i A', strtotime($w['appointment_datetime'])) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <div class="empty">Queue is clear</div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <script>
        (function () {
            const soundToggle = document.getElementById('soundToggle');
            const fullscreenToggle = document.getElementById('fullscreenToggle');

            const stateKey = 'queue_display_now_serving_v1';
            const soundKey = 'queue_display_sound_enabled_v1';
            let soundEnabled = localStorage.getItem(soundKey) === '1';

            function updateSoundBtn() {
                soundToggle.textContent = 'Sound: ' + (soundEnabled ? 'On' : 'Off');
                soundToggle.classList.toggle('primary', !soundEnabled);
            }

            function beep() {
                if (!soundEnabled) return;
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, ctx.currentTime);
                    gain.gain.setValueAtTime(0.0001, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.20, ctx.currentTime + 0.03);
                    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.35);
                } catch (e) {
                    // ignore audio errors in browsers that block autoplay
                }
            }

            function currentSignature() {
                const panels = Array.from(document.querySelectorAll('.lane'));
                return panels.map(function (lane) {
                    const doctor = lane.querySelector('.doctor')?.textContent?.trim() || '';
                    const token = lane.querySelector('.panel .token')?.textContent?.trim() || '--';
                    const name = lane.querySelector('.panel .name')?.textContent?.trim() || '';
                    return doctor + '|' + token + '|' + name;
                }).join('||');
            }

            const prev = localStorage.getItem(stateKey) || '';
            const now = currentSignature();
            if (prev && now && prev !== now) {
                beep();
            }
            localStorage.setItem(stateKey, now);

            soundToggle.addEventListener('click', function () {
                soundEnabled = !soundEnabled;
                localStorage.setItem(soundKey, soundEnabled ? '1' : '0');
                updateSoundBtn();
                if (soundEnabled) beep();
            });
            updateSoundBtn();

            fullscreenToggle.addEventListener('click', async function () {
                try {
                    if (!document.fullscreenElement) {
                        await document.documentElement.requestFullscreen();
                    } else {
                        await document.exitFullscreen();
                    }
                } catch (e) {
                    // ignore
                }
            });
        })();
    </script>
</body>
</html>
