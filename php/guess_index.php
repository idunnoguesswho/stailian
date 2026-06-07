<?php
session_start();
ob_start();
require_once 'guess_db.php';
$db   = getDB();
$sets = loadSets($db);
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>⚔️ Guess Who — The Oracle's Challenge</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --bg:#0d0b12;--card:#16121e;--panel:#1d1828;--border:#3a2f50;
    --gold:#c9a84c;--gold-l:#f0c96e;--gold-d:#7a6230;
    --red:#c94040;--teal:#3fa09a;--text:#e8dfc8;--dim:#8a7f6e;
    --font:Georgia,'Times New Roman',serif;--shadow:0 4px 24px rgba(0,0,0,.6);
}
body{
    background:var(--bg);color:var(--text);font-family:var(--font);min-height:100vh;
    background-image:radial-gradient(ellipse at 20% 0%,rgba(42,107,107,.07) 0%,transparent 50%),
                     radial-gradient(ellipse at 80% 100%,rgba(139,32,32,.07) 0%,transparent 50%);
}

/* ── HEADER ── */
header{text-align:center;padding:1.4rem 1rem .8rem;border-bottom:1px solid var(--border);background:linear-gradient(180deg,rgba(201,168,76,.05) 0%,transparent 100%)}
header h1{font-size:2rem;color:var(--gold-l);letter-spacing:.1em;text-shadow:0 0 18px rgba(201,168,76,.35)}
header p{color:var(--dim);font-style:italic;font-size:.88rem;margin:.2rem 0 .4rem}
header nav a{color:var(--teal);font-size:.8rem;text-decoration:none;margin:0 .5rem}
header nav a:hover{color:var(--gold)}
.divider{color:var(--gold-d);letter-spacing:.3em;font-size:.85rem;margin:.25rem 0}

/* ── SET SELECTION SCREEN ── */
#set-screen{max-width:780px;margin:2.5rem auto;padding:0 1rem;text-align:center}
#set-screen h2{color:var(--gold);font-size:1.4rem;margin-bottom:.5rem}
#set-screen p{color:var(--dim);font-style:italic;margin-bottom:1.5rem;font-size:.9rem}
.set-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.9rem;text-align:left}
.set-card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:1.2rem 1rem;cursor:pointer;transition:all .22s}
.set-card:hover{border-color:var(--gold);box-shadow:0 0 18px rgba(201,168,76,.2);transform:translateY(-2px)}
.set-card h3{color:var(--gold-l);font-size:.95rem;margin-bottom:.35rem}
.set-card p{color:var(--dim);font-size:.75rem;line-height:1.4}
.set-card .set-count{display:inline-block;margin-top:.5rem;font-size:.72rem;color:var(--teal);border:1px solid var(--teal);padding:.1rem .4rem;border-radius:10px}
.no-sets{color:var(--dim);font-style:italic;padding:2rem;text-align:center}
.no-sets a{color:var(--teal)}

/* ── GAME LAYOUT ── */
#game-wrap{display:grid;grid-template-columns:285px 1fr;gap:1.4rem;max-width:1350px;margin:1.4rem auto;padding:0 1rem}
@media(max-width:800px){#game-wrap{grid-template-columns:1fr}}

/* ── PANELS ── */
.side{display:flex;flex-direction:column;gap:.9rem}
.panel{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:.95rem;box-shadow:var(--shadow)}
.panel h3{color:var(--gold);font-size:.78rem;text-transform:uppercase;letter-spacing:.14em;margin-bottom:.7rem;padding-bottom:.35rem;border-bottom:1px solid var(--border)}

/* ── STATS ── */
.stat-row{display:flex;justify-content:space-between;font-size:.86rem;margin:.25rem 0}
.slbl{color:var(--dim)} .sval{color:var(--gold-l);font-weight:bold}

/* ── CURRENT SET BADGE ── */
.set-badge{display:inline-block;font-size:.75rem;color:var(--teal);border:1px solid var(--teal);padding:.15rem .5rem;border-radius:10px;margin-bottom:.5rem;cursor:pointer}
.set-badge:hover{color:var(--gold);border-color:var(--gold)}

/* ── QUESTION FORM ── */
.fg{margin-bottom:.55rem}
.fg label{display:block;font-size:.7rem;color:var(--dim);text-transform:uppercase;letter-spacing:.09em;margin-bottom:.22rem}
.fg select{width:100%;background:var(--panel);border:1px solid var(--border);color:var(--text);padding:.42rem .55rem;border-radius:4px;font-family:var(--font);font-size:.82rem}
.fg select:focus{outline:none;border-color:var(--gold-d)}

/* ── ANSWER BUBBLE ── */
#answer-bubble{padding:.6rem;border-radius:5px;text-align:center;font-size:.9rem;font-weight:bold;display:none;margin-top:.45rem}
.ans-yes{background:rgba(63,160,154,.12);border:1px solid var(--teal);color:var(--teal)}
.ans-no {background:rgba(201,64,64,.12); border:1px solid var(--red); color:var(--red)}

/* ── LOG ── */
#qlog{max-height:150px;overflow-y:auto;font-size:.74rem;font-family:'Courier New',monospace;color:var(--dim)}
#qlog .le{padding:.18rem 0;border-bottom:1px solid rgba(58,47,80,.3)}
#qlog .ly{color:var(--teal)} #qlog .ln{color:var(--red)}

/* ── BUTTONS ── */
.btn{display:block;width:100%;padding:.6rem .9rem;border:1px solid;border-radius:4px;font-family:var(--font);font-size:.85rem;cursor:pointer;transition:all .18s;letter-spacing:.04em;text-align:center;text-decoration:none}
.btn+.btn{margin-top:.35rem}
.btn-gold{background:linear-gradient(135deg,#3a2f10,#1e1a0a);border-color:var(--gold);color:var(--gold-l)}
.btn-gold:hover{background:linear-gradient(135deg,#4a3f18,#2e2a12);box-shadow:0 0 12px rgba(201,168,76,.25)}
.btn-teal{background:linear-gradient(135deg,#0f2a2a,#081818);border-color:var(--teal);color:var(--teal)}
.btn-teal:hover{background:linear-gradient(135deg,#143838,#0c2020)}
.btn-red{background:linear-gradient(135deg,#2a0c0c,#180808);border-color:var(--red);color:var(--red)}
.btn-red:hover{background:linear-gradient(135deg,#3a1010,#200c0c)}
.btn-red:disabled{opacity:.4;cursor:not-allowed}

/* ── BOARD ── */
.board-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:.7rem;font-size:.8rem;color:var(--dim)}
.board-head b{color:var(--gold)}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(125px,1fr));gap:.7rem}

/* ── CHARACTER CARD ── */
.cc{background:var(--card);border:1px solid var(--border);border-radius:8px;overflow:hidden;cursor:pointer;transition:all .2s;position:relative;display:flex;flex-direction:column}
.cc:hover:not(.elim){border-color:var(--gold);box-shadow:0 0 14px rgba(201,168,76,.25);transform:translateY(-2px)}
.cc.elim{opacity:.13;filter:grayscale(1);cursor:default;pointer-events:none;border-color:#1a1428}
.cc.sel{border-color:var(--red);box-shadow:0 0 12px rgba(201,64,64,.4)}
.cc-img{width:100%;aspect-ratio:1;background:var(--panel);overflow:hidden;display:flex;align-items:center;justify-content:center}
.cc-img img{width:100%;height:100%;object-fit:cover}
.cc-img .ph{font-size:2.8rem;color:var(--dim)}
.cc-body{padding:.45rem .45rem .55rem;flex:1}
.cc-name{font-size:.76rem;color:var(--gold-l);font-weight:bold;margin-bottom:.2rem}
.cc-traits{font-size:.62rem;color:var(--dim);line-height:1.35}
.elim-x{position:absolute;top:3px;right:5px;color:var(--red);font-size:.9rem;font-weight:bold}

/* ── SELECTED ── */
#sel-display{font-size:.83rem;color:var(--gold);margin-bottom:.5rem;min-height:1.1em}

/* ── MODAL ── */
#modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.82);z-index:100;align-items:center;justify-content:center}
#modal.on{display:flex}
.modal-box{background:var(--card);border:1px solid var(--gold);border-radius:12px;padding:1.8rem;max-width:380px;width:90%;text-align:center;box-shadow:0 0 40px rgba(201,168,76,.16)}
.modal-box h2{color:var(--gold-l);font-size:1.6rem;margin-bottom:.35rem}
.mimg{width:110px;height:110px;border-radius:8px;border:2px solid var(--gold-d);object-fit:cover;margin:.7rem auto;display:block}
.mph{font-size:3rem;margin:.7rem 0}
.mname{font-size:1rem;color:var(--gold-l);font-weight:bold}
.mtraits{font-size:.76rem;color:var(--dim);margin:.35rem 0 .55rem}
.mtraits span{background:var(--panel);border:1px solid var(--border);padding:.1rem .3rem;border-radius:3px;margin:.1rem;display:inline-block}
.mresult{font-size:.95rem;font-weight:bold;margin:.35rem 0}
.res-ok{color:var(--teal)} .res-bad{color:var(--red)}

/* ── SPINNER ── */
.spin{display:inline-block;animation:rot .9s linear infinite}
@keyframes rot{to{transform:rotate(360deg)}}
</style>
</head>
<body>

<header>
    <div class="divider">⚔ ✦ ⚔</div>
    <h1>The Oracle's Challenge</h1>
    <p>Guess Who — A Game of Questions &amp; Deduction</p>
    <nav><a href="guess_admin.php">⚙️ Manage Characters &amp; Sets</a></nav>
    <div class="divider">✦ ─── ✦ ─── ✦</div>
</header>

<!-- ── SET SELECTION SCREEN ── -->
<div id="set-screen">
    <h2>🔮 Choose Your Realm</h2>
    <p>Select a character set to challenge the Oracle.</p>

    <?php if (empty($sets)): ?>
    <div class="no-sets">
        No character sets yet.<br>
        <a href="guess_admin.php">Head to the admin panel to create one!</a>
    </div>
    <?php else: ?>
    <div class="set-grid">
        <?php foreach ($sets as $s): ?>
        <div class="set-card" onclick="startSet(<?= $s['id'] ?>, <?= htmlspecialchars(json_encode($s['name'])) ?>)">
            <h3><?= htmlspecialchars($s['name']) ?></h3>
            <?php if ($s['description']): ?>
            <p><?= htmlspecialchars($s['description']) ?></p>
            <?php endif; ?>
            <span class="set-count"><?= $s['character_count'] ?> character<?= $s['character_count'] != 1 ? 's' : '' ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ── GAME WRAP (hidden until game starts) ── -->
<div id="game-wrap" style="display:none">

    <div class="side" id="side">

        <div class="panel">
            <div id="set-badge" class="set-badge" onclick="returnToSets()" title="Click to change set">📦 —</div>
            <h3>📜 The Tally</h3>
            <div class="stat-row"><span class="slbl">Remaining</span><span class="sval" id="s-rem">—</span></div>
            <div class="stat-row"><span class="slbl">Questions</span><span class="sval" id="s-q">0</span></div>
            <div class="stat-row"><span class="slbl">Eliminated</span><span class="sval" id="s-el">0</span></div>
        </div>

        <div class="panel">
            <h3>🗣 Ask the Oracle</h3>
            <div class="fg">
                <label>Trait Category</label>
                <select id="q-trait" onchange="populateValues()">
                    <option>— loading —</option>
                </select>
            </div>
            <div class="fg">
                <label>Value</label>
                <select id="q-value"><option>— select category first —</option></select>
            </div>
            <div id="answer-bubble"></div>
            <button class="btn btn-teal" id="ask-btn" onclick="askQuestion()" style="margin-top:.5rem">Ask the Oracle</button>
        </div>

        <div class="panel">
            <h3>🎯 Accusation</h3>
            <p style="font-size:.76rem;color:var(--dim);margin-bottom:.45rem;font-style:italic">Click a card to select, then accuse.</p>
            <div id="sel-display">No suspect selected</div>
            <button class="btn btn-red" id="guess-btn" onclick="makeGuess()" disabled>⚔️ Accuse This Person</button>
        </div>

        <div class="panel">
            <h3>📖 Oracle's Ledger</h3>
            <div id="qlog"><div style="color:var(--dim);font-style:italic;font-size:.77rem">No questions yet.</div></div>
        </div>

        <button class="btn btn-gold" onclick="returnToSets()">↩ Change Set</button>
        <button class="btn btn-gold" id="replay-btn" style="display:none" onclick="newGame()">🔄 Play Again (Same Set)</button>
    </div>

    <div id="board-area">
        <div class="board-head">
            <b id="board-title">The Suspects</b>
            <span>Click a card to select · Ask questions to eliminate</span>
        </div>
        <div class="grid" id="grid"></div>
    </div>

</div>

<!-- ── RESULT MODAL ── -->
<div id="modal">
    <div class="modal-box">
        <h2 id="m-title">—</h2>
        <div id="m-img-wrap"></div>
        <div class="mname" id="m-name"></div>
        <div class="mtraits" id="m-traits"></div>
        <div class="mresult" id="m-result"></div>
        <p id="m-stats" style="font-size:.8rem;color:var(--dim);margin-bottom:.9rem"></p>
        <button class="btn btn-gold" onclick="newGame()">🔄 Play Again</button>
        <button class="btn btn-teal" id="m-continue" onclick="closeModal()" style="margin-top:.4rem">Continue Guessing</button>
    </div>
</div>

<script>
// ── STATE ──────────────────────────────────────────────
let activeSetId   = null;
let activeSetName = '';
let gameActive    = false;
let characters    = [];
let traitDefs     = [];
let eliminated    = new Set();
let selectedId    = null;
let qCount        = 0;

// ── SET SELECTION ───────────────────────────────────────
function startSet(setId, setName) {
    activeSetId   = setId;
    activeSetName = setName;
    newGame();
}

function returnToSets() {
    gameActive = false;
    document.getElementById('set-screen').style.display  = 'block';
    document.getElementById('game-wrap').style.display   = 'none';
    closeModal();
}

// ── NEW GAME ────────────────────────────────────────────
async function newGame() {
    const r = await api({ action: 'new_game', set_id: activeSetId });
    if (!r.success) { alert('Error: ' + (r.error || JSON.stringify(r))); return; }

    characters = r.characters;
    eliminated = new Set();
    selectedId = null;
    qCount     = 0;
    gameActive = true;

    document.getElementById('set-screen').style.display  = 'none';
    document.getElementById('game-wrap').style.display   = 'grid';
    document.getElementById('set-badge').textContent     = '📦 ' + r.set.name;
    document.getElementById('board-title').textContent   = r.set.name + ' — Suspects';
    document.getElementById('qlog').innerHTML            = '<div style="color:var(--dim);font-style:italic;font-size:.77rem">No questions yet.</div>';
    document.getElementById('answer-bubble').style.display = 'none';
    document.getElementById('sel-display').textContent   = 'No suspect selected';
    document.getElementById('guess-btn').disabled        = true;
    document.getElementById('replay-btn').style.display  = 'block';

    await loadTraits();
    renderBoard();
    updateStats();
    closeModal();
}

// ── LOAD TRAITS ─────────────────────────────────────────
async function loadTraits() {
    const r = await api({ action: 'get_traits', set_id: activeSetId }, 'GET');
    if (!r.success) return;
    traitDefs = r.traits;
    const sel = document.getElementById('q-trait');
    sel.innerHTML = '';
    traitDefs.forEach(t => {
        const o = document.createElement('option');
        o.value = t.label; o.textContent = t.label;
        sel.appendChild(o);
    });
    populateValues();
}

function populateValues() {
    const label = document.getElementById('q-trait').value;
    const def   = traitDefs.find(t => t.label === label);
    const sel   = document.getElementById('q-value');
    sel.innerHTML = '';
    (def ? def.values : []).forEach(v => {
        const o = document.createElement('option');
        o.value = v; o.textContent = v.charAt(0).toUpperCase() + v.slice(1);
        sel.appendChild(o);
    });
}

// ── ASK QUESTION ────────────────────────────────────────
async function askQuestion() {
    if (!gameActive) return;
    const label = document.getElementById('q-trait').value;
    const value = document.getElementById('q-value').value;
    if (!label || !value) return;

    const btn = document.getElementById('ask-btn');
    btn.innerHTML = '<span class="spin">⏳</span> Consulting...';
    btn.disabled = true;

    const r = await api({ action: 'ask_question', trait_label: label, trait_value: value });
    btn.innerHTML = 'Ask the Oracle'; btn.disabled = false;
    if (!r.success) { alert(r.error); return; }

    r.eliminated.forEach(id => eliminated.add(parseInt(id)));
    qCount = r.questions_asked;

    const bubble = document.getElementById('answer-bubble');
    bubble.className = r.answer ? 'ans-yes' : 'ans-no';
    bubble.textContent = `${label} = "${value}"? → ${r.answer_text}`;
    bubble.style.display = 'block';

    const log = document.getElementById('qlog');
    if (log.querySelector('div[style]')) log.innerHTML = '';
    const e = document.createElement('div');
    e.className = 'le ' + (r.answer ? 'ly' : 'ln');
    e.textContent = (r.answer ? '✓ ' : '✗ ') + `${label} is "${value}"`;
    log.prepend(e);

    renderBoard(); updateStats();
}

// ── RENDER BOARD ────────────────────────────────────────
function renderBoard() {
    const grid = document.getElementById('grid');
    grid.innerHTML = '';
    characters.forEach(c => {
        const isElim = eliminated.has(c.id);
        const isSel  = selectedId === c.id;
        const div    = document.createElement('div');
        div.className = 'cc' + (isElim ? ' elim' : '') + (isSel ? ' sel' : '');

        const imgWrap = document.createElement('div'); imgWrap.className = 'cc-img';
        if (c.image_path) {
            const img = document.createElement('img');
            img.src = c.image_path; img.alt = c.name; img.loading = 'lazy';
            imgWrap.appendChild(img);
        } else { imgWrap.innerHTML = '<span class="ph">🎭</span>'; }
        div.appendChild(imgWrap);

        const body = document.createElement('div'); body.className = 'cc-body';
        body.innerHTML = '<div class="cc-name">' + escHtml(c.name) + '</div>';
        if (c.traits && Object.keys(c.traits).length) {
            body.innerHTML += '<div class="cc-traits">' +
                Object.entries(c.traits).map(([k,v]) => `<span style="color:var(--text)">${escHtml(v)}</span>`).join(' · ') +
                '</div>';
        }
        div.appendChild(body);

        if (isElim) { const x = document.createElement('span'); x.className='elim-x'; x.textContent='✗'; div.appendChild(x); }
        if (!isElim) div.addEventListener('click', () => selectChar(c.id, c.name));
        grid.appendChild(div);
    });
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── SELECT ──────────────────────────────────────────────
function selectChar(id, name) {
    selectedId = id;
    document.getElementById('sel-display').innerHTML = '<strong>' + escHtml(name) + '</strong>';
    document.getElementById('guess-btn').disabled = false;
    renderBoard();
}

// ── GUESS ───────────────────────────────────────────────
async function makeGuess() {
    if (!selectedId) return;
    const r = await api({ action: 'make_guess', character_id: selectedId });
    if (!r.success) { alert(r.error); return; }

    document.getElementById('m-title').textContent   = r.correct ? '🏆 Correct!' : '💀 Wrong!';
    document.getElementById('m-name').textContent    = r.secret.name;
    document.getElementById('m-result').className   = 'mresult ' + (r.correct ? 'res-ok' : 'res-bad');
    document.getElementById('m-result').textContent = r.correct
        ? `You unmasked the secret in ${r.questions} question(s)!`
        : `Wrong! The Oracle's secret was: ${r.secret.name}`;
    document.getElementById('m-stats').textContent  = `Questions asked: ${r.questions}`;
    document.getElementById('m-continue').style.display = r.correct ? 'none' : 'block';

    const wrap = document.getElementById('m-img-wrap');
    wrap.innerHTML = r.secret.image_path
        ? `<img src="${r.secret.image_path}" class="mimg" alt="${escHtml(r.secret.name)}">`
        : '<div class="mph">🎭</div>';

    const tHtml = Object.entries(r.secret.traits || {}).map(([k,v]) => `<span>${escHtml(k)}: ${escHtml(v)}</span>`).join('');
    document.getElementById('m-traits').innerHTML = tHtml;

    document.getElementById('modal').classList.add('on');
    if (r.correct) gameActive = false;
}

function closeModal() { document.getElementById('modal').classList.remove('on'); }

// ── STATS ───────────────────────────────────────────────
function updateStats() {
    document.getElementById('s-rem').textContent = characters.length - eliminated.size;
    document.getElementById('s-q').textContent   = qCount;
    document.getElementById('s-el').textContent  = eliminated.size;
}

// ── API ─────────────────────────────────────────────────
async function api(params, method = 'POST') {
    try {
        let r;
        if (method === 'GET') {
            r = await fetch('guess_game.php?' + new URLSearchParams(params));
        } else {
            r = await fetch('guess_game.php', { method: 'POST', body: new URLSearchParams(params) });
        }
        const text = await r.text();
        try { return JSON.parse(text); }
        catch (e) {
            console.error('Non-JSON from server:', text);
            return { error: 'Server error. Check console. Raw: ' + text.substring(0, 200) };
        }
    } catch (e) {
        return { error: 'Network error: ' + e.message };
    }
}
</script>
</body>
</html>
