<?php
require 'auth.php';
require 'db.php';
require 'layout.php';

$db      = getDB();
$userid  = $SESSION_USERID;

$phase        = $_SESSION['mb_phase']        ?? 'setup';
$setId        = (int)($_SESSION['mb_set']     ?? 0);
$placed       = $_SESSION['mb_placed']        ?? [];
$guessCorrect = $_SESSION['mb_guess_correct'] ?? null;
$buildSet     = null;

if ($setId) {
    $stmt = $db->prepare("SELECT * FROM builder_sets WHERE id=?");
    $stmt->execute([$setId]);
    $buildSet = $stmt->fetch();
    if ($buildSet) $buildSet['pieces_arr'] = json_decode($buildSet['pieces'], true) ?? [];
}

// ── POST HANDLERS ─────────────────────────────────────────────────────────────

if (isset($_POST['start_set'])) {
    $_SESSION['mb_set']    = (int)$_POST['set_id'];
    $_SESSION['mb_phase']  = 'guess';
    $_SESSION['mb_placed'] = [];
    unset($_SESSION['mb_won'], $_SESSION['mb_guess_correct'], $_SESSION['game_rewards']);
    header('Location: masterbuilder.php'); exit();
}

if (isset($_POST['submit_guess']) && $phase === 'guess') {
    $_SESSION['mb_guess_correct'] = ((int)$_POST['guessed_set'] === $setId);
    $_SESSION['mb_phase'] = 'building';
    header('Location: masterbuilder.php'); exit();
}

if (isset($_POST['place_piece']) && $phase === 'building') {
    $piece = (int)$_POST['piece_index'];
    if (!in_array($piece, $placed, true)) {
        $placed[] = $piece;
        $_SESSION['mb_placed'] = $placed;

        if ($buildSet && count($placed) >= count($buildSet['pieces_arr'])) {
            $_SESSION['mb_phase'] = 'won';
            $db->prepare("INSERT INTO side_game_log (userid,game_type,result) VALUES (?,?,?)")
               ->execute([$userid, 'masterbuilder', 'win']);

            $scrollType = rand(0, 1) ? 'climbing' : 'sliding';
            $db->prepare("INSERT INTO tower_scrolls (userid,scroll_type,quantity) VALUES (?,?,1)
                          ON DUPLICATE KEY UPDATE quantity=quantity+1")
               ->execute([$userid, $scrollType]);
            $colour = $db->query("SELECT id FROM colours ORDER BY RAND() LIMIT 1")->fetchColumn();
            if ($colour) {
                $db->prepare("INSERT INTO user_scrolls (userid,colour_id,quantity) VALUES (?,?,1)
                              ON DUPLICATE KEY UPDATE quantity=quantity+1")
                   ->execute([$userid, $colour]);
            }
            $rewards = [
                ($scrollType === 'climbing' ? '🧗' : '🎿') . ' ' . ucfirst($scrollType) . ' scroll',
                '📜 Coloured scroll',
            ];
            if ($guessCorrect) {
                $db->prepare("INSERT INTO user_coins (userid,coins) VALUES (?,10)
                              ON DUPLICATE KEY UPDATE coins=coins+10")
                   ->execute([$userid]);
                $rewards[] = '🪙 +10 coins (correct guess bonus)';
            }
            $_SESSION['game_rewards'] = $rewards;
        }
    }
    header('Location: masterbuilder.php'); exit();
}

if (isset($_GET['reset'])) {
    unset($_SESSION['mb_phase'], $_SESSION['mb_set'], $_SESSION['mb_placed'],
          $_SESSION['mb_won'], $_SESSION['mb_guess_correct'], $_SESSION['game_rewards']);
    header('Location: masterbuilder.php'); exit();
}

// ── DATA ──────────────────────────────────────────────────────────────────────

$sets = $db->query("SELECT * FROM builder_sets ORDER BY difficulty, name")->fetchAll();
foreach ($sets as &$s) $s['pieces_arr'] = json_decode($s['pieces'], true) ?? [];
unset($s);

// Guess options: 3 decoys + the correct set, shuffled
$guessOptions = [];
if ($phase === 'guess' && $buildSet) {
    $wrong = array_values(array_filter($sets, fn($s) => $s['id'] !== $setId));
    shuffle($wrong);
    $guessOptions   = array_slice($wrong, 0, 3);
    $guessOptions[] = $buildSet;
    shuffle($guessOptions);
}

$pieceColors = ['c8993a','7ecfed','e8622a','4a9fd4','b87333','8855cc','27ae60','e74c3c'];

pageHeader('Master Builder', 'masterbuilder.php');
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
<style>
#mb-canvas-wrap {
  position: relative;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  height: 400px;
}
#mb-canvas { display: block; width: 100%; height: 100%; }
.piece-btn {
  display: flex; align-items: center; gap: .5rem;
  width: 100%; padding: .45rem .75rem;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  font-size: .8rem; cursor: pointer;
  color: var(--text); text-align: left;
  transition: all .15s;
}
.piece-btn:disabled {
  cursor: default; color: var(--success);
  background: rgba(39,174,96,.1); border-color: var(--success);
}
.piece-btn:not(:disabled):hover { border-color: var(--gold-dim); background: rgba(200,153,58,.05); }
.piece-dot { width: 12px; height: 12px; border-radius: 3px; flex-shrink: 0; }
.guess-opt {
  display: flex; align-items: center; gap: .75rem;
  padding: .8rem 1rem;
  background: var(--surface);
  border: 2px solid var(--border);
  border-radius: var(--radius);
  cursor: pointer; transition: all .2s;
  font-family: 'Cinzel', serif; font-size: .82rem; color: var(--text);
  width: 100%; text-align: left; margin-bottom: .5rem;
}
.guess-opt:hover { border-color: var(--gold-dim); background: rgba(200,153,58,.06); }
.guess-opt.selected { border-color: var(--gold); background: rgba(200,153,58,.12); color: var(--gold); }
.canvas-hint {
  font-size: .68rem; color: var(--muted); margin-top: .35rem; text-align: center;
}
</style>

<h1 class="page-title">🧱 Master Builder</h1>
<p class="page-sub">Assemble the 3D build — and guess what you're making before you start</p>

<?php if ($phase === 'setup'): ?>
<!-- ── SET SELECTION ──────────────────────────────────────────────────────── -->
<div class="grid-3">
  <?php foreach ($sets as $set): ?>
  <div class="card">
    <div style="text-align:center; font-size:3rem; margin-bottom:.5rem;"><?= $set['emoji'] ?></div>
    <div style="font-family:'Cinzel',serif; font-size:.95rem; color:var(--gold);
         text-align:center; margin-bottom:.35rem;">
      <?= htmlspecialchars($set['name']) ?>
    </div>
    <div style="text-align:center; margin-bottom:.6rem;">
      <span class="badge badge-<?= $set['difficulty']==='easy'?'ground':($set['difficulty']==='medium'?'fire':'dark') ?>">
        <?= ucfirst($set['difficulty']) ?>
      </span>
      <span style="font-size:.75rem; color:var(--muted); margin-left:.4rem;">
        <?= $set['piece_count'] ?> pieces
      </span>
    </div>
    <div style="font-size:.75rem; color:var(--muted); margin-bottom:.75rem; line-height:1.5;">
      Place all pieces in the 3D arena. Guess the build correctly for a coin bonus!
    </div>
    <form method="post">
      <input type="hidden" name="set_id" value="<?= $set['id'] ?>">
      <button class="btn btn-outline" type="submit" name="start_set" style="width:100%;">
        Build This →
      </button>
    </form>
  </div>
  <?php endforeach; ?>
</div>

<?php elseif ($phase === 'guess'): ?>
<!-- ── GUESS PHASE ────────────────────────────────────────────────────────── -->
<div class="grid-2">

  <div>
    <div class="card">
      <div class="card-title">🔍 What is being built?</div>
      <p style="font-size:.88rem; color:var(--muted); line-height:1.7; margin-bottom:1.25rem;">
        The first <strong style="color:var(--text);">3 blocks</strong> are shown in the 3D preview.
        Study the shape and guess the final build.
        A correct guess earns a <strong style="color:var(--gold);">+10 coin bonus</strong> on completion.
      </p>
      <form method="post" id="guessForm">
        <?php foreach ($guessOptions as $opt): ?>
        <button type="button" class="guess-opt"
                onclick="selectGuess(this, <?= (int)$opt['id'] ?>)">
          <span style="font-size:1.4rem; flex-shrink:0;"><?= $opt['emoji'] ?></span>
          <span><?= htmlspecialchars($opt['name']) ?></span>
        </button>
        <?php endforeach; ?>
        <input type="hidden" name="guessed_set" id="guessedSetId" value="0">
        <button class="btn btn-primary" type="submit" name="submit_guess"
                id="guessSubmit" disabled style="width:100%; margin-top:.5rem;">
          Lock In Guess →
        </button>
      </form>
      <div style="text-align:center; margin-top:.85rem;">
        <a href="masterbuilder.php?reset=1"
           style="font-size:.78rem; color:var(--muted); text-decoration:none;">
          ← Choose a different set
        </a>
      </div>
    </div>
  </div>

  <div>
    <div style="font-family:'Cinzel',serif; font-size:.72rem; letter-spacing:.08em;
         color:var(--muted); margin-bottom:.4rem;">
      3D PREVIEW — FIRST 3 BLOCKS
    </div>
    <div id="mb-canvas-wrap"><canvas id="mb-canvas"></canvas></div>
    <div class="canvas-hint">Drag to rotate · Scroll to zoom</div>
  </div>

</div>

<?php elseif ($phase === 'won'): ?>
<!-- ── WIN ────────────────────────────────────────────────────────────────── -->
<div style="max-width:520px; margin:0 auto;">
  <div class="card" style="border-color:var(--gold); text-align:center; padding:2.5rem;">
    <div style="font-size:4rem; margin-bottom:.5rem;"><?= $buildSet['emoji'] ?></div>
    <div style="font-family:'Cinzel',serif; font-size:1.4rem; color:var(--gold); margin-bottom:.25rem;">
      Build Complete!
    </div>
    <div style="font-size:.9rem; color:var(--muted); margin-bottom:1.25rem;">
      <?= htmlspecialchars($buildSet['name']) ?> —
      all <?= count($buildSet['pieces_arr']) ?> pieces placed
    </div>
    <?php if ($guessCorrect === true): ?>
      <div style="font-size:.85rem; color:var(--success); margin-bottom:.75rem;">
        ✓ You guessed correctly!
      </div>
    <?php elseif ($guessCorrect === false): ?>
      <div style="font-size:.85rem; color:var(--muted); margin-bottom:.75rem;">
        You guessed incorrectly — better luck next build!
      </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['game_rewards'])): ?>
    <div style="background:var(--surface); border-radius:var(--radius); padding:.85rem;
         margin-bottom:1.25rem; text-align:left;">
      <div style="font-family:'Cinzel',serif; font-size:.68rem; letter-spacing:.1em;
           color:var(--gold); margin-bottom:.5rem;">🎁 REWARDS</div>
      <?php foreach ($_SESSION['game_rewards'] as $r): ?>
        <div style="font-size:.85rem; color:var(--text); margin-bottom:.2rem;">
          ✓ <?= htmlspecialchars($r) ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php unset($_SESSION['game_rewards']); endif; ?>
    <div style="display:flex; gap:.5rem; justify-content:center; flex-wrap:wrap;">
      <a href="masterbuilder.php?reset=1" class="btn btn-primary">Build Again</a>
      <a href="walk.php" class="btn btn-outline">← Walk</a>
    </div>
  </div>
  <div style="margin-top:.75rem;">
    <div style="font-family:'Cinzel',serif; font-size:.72rem; letter-spacing:.08em;
         color:var(--muted); margin-bottom:.4rem;">COMPLETED BUILD</div>
    <div id="mb-canvas-wrap" style="height:320px;"><canvas id="mb-canvas"></canvas></div>
    <div class="canvas-hint">Drag to rotate · Scroll to zoom</div>
  </div>
</div>

<?php else: // BUILDING ────────────────────────────────────────────────────── ?>
<?php
  $totalPieces = count($buildSet['pieces_arr']);
  $pct = $totalPieces > 0 ? round((count($placed) / $totalPieces) * 100) : 0;
?>

<?php if ($guessCorrect !== null): ?>
<div class="alert <?= $guessCorrect ? 'alert-success' : 'alert-error' ?>"
     style="margin-bottom:1rem;">
  <?= $guessCorrect
      ? '✓ Correct guess! Complete the build to earn your +10 coin bonus.'
      : '✗ Incorrect guess — you can still complete the build and earn your scrolls.' ?>
</div>
<?php endif; ?>

<div class="grid-2">

  <!-- Piece list -->
  <div>
    <div class="card">
      <div class="card-title">
        <?= $buildSet['emoji'] ?> <?= htmlspecialchars($buildSet['name']) ?>
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center;
           font-size:.72rem; font-family:'Cinzel',serif; color:var(--muted);
           margin-bottom:.3rem;">
        <span>PROGRESS</span>
        <span><?= count($placed) ?>/<?= $totalPieces ?></span>
      </div>
      <div style="background:var(--border); border-radius:10px; height:8px;
           overflow:hidden; margin-bottom:1.25rem;">
        <div style="background:var(--gold); width:<?= $pct ?>%; height:100%;
             border-radius:10px; transition:width .4s;"></div>
      </div>

      <div style="display:flex; flex-direction:column; gap:.4rem;">
        <?php foreach ($buildSet['pieces_arr'] as $i => $piece):
          $isPlaced = in_array($i, $placed, true);
          $col = $pieceColors[$i % count($pieceColors)]; ?>
        <form method="post">
          <input type="hidden" name="piece_index" value="<?= $i ?>">
          <button type="submit" name="place_piece"
                  class="piece-btn" <?= $isPlaced ? 'disabled' : '' ?>
                  onmouseenter="highlightPiece(<?= $i ?>)"
                  onmouseleave="clearHighlight()">
            <span class="piece-dot" style="background:#<?= $col ?>;"></span>
            <span><?= $isPlaced ? '✓ ' : '' ?><?= htmlspecialchars($piece) ?></span>
          </button>
        </form>
        <?php endforeach; ?>
      </div>
    </div>

    <div style="display:flex; gap:.5rem; margin-top:-.5rem;">
      <a href="masterbuilder.php?reset=1" class="btn btn-danger btn-sm">Abandon</a>
      <a href="walk.php" class="btn btn-outline btn-sm">← Walk</a>
    </div>
  </div>

  <!-- 3D view -->
  <div>
    <div style="display:flex; justify-content:space-between; align-items:center;
         font-family:'Cinzel',serif; font-size:.72rem; letter-spacing:.08em;
         color:var(--muted); margin-bottom:.4rem;">
      <span>3D BUILD VIEW</span>
      <span style="font-size:.65rem;">Hover a piece · Drag to rotate</span>
    </div>
    <div id="mb-canvas-wrap"><canvas id="mb-canvas"></canvas></div>
    <div class="canvas-hint" style="display:flex; gap:1rem; justify-content:center;">
      <span style="display:inline-flex; align-items:center; gap:.3rem;">
        <span style="width:10px;height:10px;border-radius:2px;background:var(--gold);display:inline-block;"></span>
        Placed
      </span>
      <span style="display:inline-flex; align-items:center; gap:.3rem;">
        <span style="width:10px;height:10px;border-radius:2px;border:1px solid var(--border);display:inline-block;"></span>
        Remaining
      </span>
    </div>
  </div>

</div>
<?php endif; ?>

<script>
// ── PHP → JS ──────────────────────────────────────────────────────────────────
const PHASE  = <?= json_encode($phase) ?>;
const PLACED = new Set(<?= json_encode(array_values($placed)) ?>);
const TOTAL  = <?= ($buildSet ? count($buildSet['pieces_arr']) : 0) ?>;
const COLORS = [0xc8993a,0x7ecfed,0xe8622a,0x4a9fd4,0xb87333,0x8855cc,0x27ae60,0xe74c3c];

// ── LAYOUT GENERATOR ──────────────────────────────────────────────────────────
// Returns an array of {x,y,z} positions for each piece index.
// Builds a compact shape that layers upward.
function generateLayout(n) {
  if (n === 0) return [];
  const positions = [];

  if (n <= 4) {
    // Single row
    for (let i = 0; i < n; i++) positions.push({x: i, y: 0, z: 0});

  } else if (n <= 9) {
    // 2×2 base, then stack
    const grid = [{x:0,y:0,z:0},{x:1,y:0,z:0},{x:0,y:0,z:1},{x:1,y:0,z:1}];
    for (let i = 0; i < n; i++) {
      const layer = Math.floor(i / 4);
      const cell  = i % 4;
      positions.push({x: grid[cell].x, y: layer, z: grid[cell].z});
    }

  } else if (n <= 18) {
    // 3×3 base, 2×2 mid, 1×1 top (pyramid-ish)
    const layers = [
      [{x:0,z:0},{x:1,z:0},{x:2,z:0},{x:0,z:1},{x:1,z:1},{x:2,z:1},{x:0,z:2},{x:1,z:2},{x:2,z:2}],
      [{x:0,z:0},{x:1,z:0},{x:0,z:1},{x:1,z:1}],
      [{x:0,z:0}],
    ];
    for (const [yi, row] of layers.entries()) {
      for (const cell of row) {
        if (positions.length < n)
          positions.push({x: cell.x, y: yi, z: cell.z});
      }
    }

  } else {
    // Staircase: each 4 pieces steps up and out
    for (let i = 0; i < n; i++) {
      const step  = Math.floor(i / 2);
      const side  = i % 2;
      positions.push({x: side, y: Math.floor(step / 2), z: step % 2 + Math.floor(step / 2)});
    }
  }
  return positions;
}

// ── THREE.JS ──────────────────────────────────────────────────────────────────
(function () {
  const canvas = document.getElementById('mb-canvas');
  if (!canvas || TOTAL === 0) return;
  const wrap = document.getElementById('mb-canvas-wrap');

  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
  renderer.setPixelRatio(window.devicePixelRatio);
  renderer.setClearColor(0x12141c, 1);
  renderer.shadowMap.enabled = true;

  const scene  = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(40, 1, 0.1, 200);

  // Lights
  scene.add(new THREE.AmbientLight(0xffffff, 0.55));
  const sun = new THREE.DirectionalLight(0xffffff, 0.85);
  sun.position.set(6, 12, 8);
  sun.castShadow = true;
  scene.add(sun);

  // Orbit controls
  const controls = new THREE.OrbitControls(camera, renderer.domElement);
  controls.enableDamping  = true;
  controls.dampingFactor  = 0.08;
  controls.minDistance    = 2;
  controls.maxDistance    = 30;
  controls.enablePan      = false;

  const layout = generateLayout(TOTAL);

  // Find centroid to centre the model
  let cx = 0, cy = 0, cz = 0;
  layout.forEach(p => { cx += p.x; cy += p.y; cz += p.z; });
  cx /= layout.length; cy /= layout.length; cz /= layout.length;

  // Bounding size for camera placement
  let span = 2;
  layout.forEach(p => {
    span = Math.max(span,
      Math.abs(p.x - cx) * 2 + 1,
      Math.abs(p.y - cy) * 2 + 1,
      Math.abs(p.z - cz) * 2 + 1
    );
  });

  camera.position.set(cx + span * 1.5, cy + span * 1.2, cz + span * 1.5);
  camera.lookAt(cx, cy, cz);
  controls.target.set(cx, cy, cz);

  // Grid floor
  const gridSize = Math.max(4, Math.ceil(span) + 2);
  const grid = new THREE.GridHelper(gridSize, gridSize, 0x2a2d3e, 0x2a2d3e);
  grid.position.set(cx, -0.51, cz);
  scene.add(grid);

  // Shared geometry
  const boxGeo   = new THREE.BoxGeometry(0.9, 0.9, 0.9);
  const edgesGeo = new THREE.EdgesGeometry(new THREE.BoxGeometry(0.9, 0.9, 0.9));

  const cubeMeshes = [];  // indexed by piece index

  layout.forEach((pos, i) => {
    const color    = COLORS[i % COLORS.length];
    const px       = pos.x - cx;
    const py       = pos.y;        // don't centre Y — let it sit on the floor
    const pz       = pos.z - cz;
    const isPlaced = PLACED.has(i);
    const isHint   = (PHASE === 'guess' && i < 3);

    if (PHASE === 'guess') {
      if (isHint) {
        // Solid preview block
        const mat  = new THREE.MeshLambertMaterial({ color });
        const mesh = new THREE.Mesh(boxGeo, mat);
        mesh.castShadow    = true;
        mesh.receiveShadow = true;
        mesh.position.set(px, py, pz);
        scene.add(mesh);
        cubeMeshes[i] = mesh;
      } else {
        // Faint ghost of remaining shape
        const mat  = new THREE.MeshLambertMaterial({
          color: color, transparent: true, opacity: 0.12
        });
        const mesh = new THREE.Mesh(boxGeo, mat);
        mesh.position.set(px, py, pz);
        scene.add(mesh);
        // Dim wireframe
        const wMat  = new THREE.LineBasicMaterial({ color: 0x3a3d50 });
        const lines = new THREE.LineSegments(edgesGeo.clone(), wMat);
        lines.position.set(px, py, pz);
        scene.add(lines);
        cubeMeshes[i] = mesh;
      }

    } else if (isPlaced) {
      // Solid placed cube with stud on top
      const mat  = new THREE.MeshLambertMaterial({ color });
      const mesh = new THREE.Mesh(boxGeo, mat);
      mesh.castShadow    = true;
      mesh.receiveShadow = true;
      mesh.position.set(px, py, pz);
      scene.add(mesh);

      // Stud (small cylinder on top)
      const studGeo = new THREE.CylinderGeometry(0.18, 0.18, 0.14, 12);
      const stud    = new THREE.Mesh(studGeo, new THREE.MeshLambertMaterial({
        color: new THREE.Color(color).multiplyScalar(0.85)
      }));
      stud.position.set(px, py + 0.52, pz);
      scene.add(stud);
      cubeMeshes[i] = mesh;

    } else {
      // Ghost wireframe outline for unplaced pieces
      const wMat  = new THREE.LineBasicMaterial({ color: 0x3a3d50 });
      const lines = new THREE.LineSegments(edgesGeo.clone(), wMat);
      lines.position.set(px, py, pz);
      scene.add(lines);
      cubeMeshes[i] = lines;
    }
  });

  // Resize handler
  function resize() {
    const w = wrap.clientWidth;
    const h = wrap.clientHeight;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
  }
  resize();
  window.addEventListener('resize', resize);

  // Auto-rotate slowly when not interacting (guess phase only)
  let autoRotate = (PHASE === 'guess' || PHASE === 'won');
  renderer.domElement.addEventListener('pointerdown', () => { autoRotate = false; });

  // Render loop
  function animate() {
    requestAnimationFrame(animate);
    if (autoRotate) controls.object.position.applyAxisAngle(
      new THREE.Vector3(0, 1, 0), 0.004
    );
    controls.update();
    renderer.render(scene, camera);
  }
  animate();

  // Piece hover highlight
  window.highlightPiece = function (i) {
    const m = cubeMeshes[i];
    if (!m || !m.material || !m.material.color) return;
    if (m.material._saved === undefined)
      m.material._saved = m.material.color.getHex();
    m.material.color.setHex(0xffffff);
    m.material.opacity = 1;
    m.material.transparent = false;
  };
  window.clearHighlight = function () {
    cubeMeshes.forEach(m => {
      if (!m || !m.material || m.material._saved === undefined) return;
      m.material.color.setHex(m.material._saved);
      delete m.material._saved;
    });
  };
}());

// ── GUESS SELECTION ───────────────────────────────────────────────────────────
function selectGuess(btn, setId) {
  document.querySelectorAll('.guess-opt').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  document.getElementById('guessedSetId').value = setId;
  document.getElementById('guessSubmit').disabled = false;
}
</script>

<?php pageFooter(); ?>
