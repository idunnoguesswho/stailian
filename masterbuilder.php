<?php
require 'auth.php';
require 'db.php';
require 'layout.php';

$db     = getDB();
$userid = $SESSION_USERID;

$phase    = $_SESSION['mb_phase']    ?? 'setup';
$setId    = $_SESSION['mb_set']      ?? null;
$placed   = $_SESSION['mb_placed']   ?? [];
$won      = $_SESSION['mb_won']      ?? false;
$buildSet = null;

if ($setId) {
    $stmt = $db->prepare("SELECT * FROM builder_sets WHERE id=?");
    $stmt->execute([$setId]);
    $buildSet = $stmt->fetch();
    if ($buildSet) {
        $buildSet['pieces_arr'] = json_decode($buildSet['pieces'], true);
    }
}

// Start game
if (isset($_POST['start_set'])) {
    $setId = (int)$_POST['set_id'];
    $_SESSION['mb_set']    = $setId;
    $_SESSION['mb_phase']  = 'building';
    $_SESSION['mb_placed'] = [];
    $_SESSION['mb_won']    = false;
    header('Location: masterbuilder.php');
    exit();
}

// Place piece
if (isset($_POST['place_piece']) && $phase === 'building') {
    $piece = (int)$_POST['piece_index'];
    if (!in_array($piece, $placed)) {
        $placed[] = $piece;
        $_SESSION['mb_placed'] = $placed;

        // Check if all placed
        if ($buildSet && count($placed) >= count($buildSet['pieces_arr'])) {
            $_SESSION['mb_phase'] = 'won';
            $_SESSION['mb_won']   = true;
            $db->prepare("INSERT INTO side_game_log (userid,game_type,result) VALUES (?,?,?)")
               ->execute([$userid,'masterbuilder','win']);

            // Award rewards
            $scrollType = rand(0,1) ? 'climbing' : 'sliding';
            $db->prepare("INSERT INTO tower_scrolls (userid,scroll_type,quantity) VALUES (?,?,1)
                          ON DUPLICATE KEY UPDATE quantity=quantity+1")
               ->execute([$userid,$scrollType]);
            $colour = $db->query("SELECT id FROM colours ORDER BY RAND() LIMIT 1")->fetchColumn();
            if ($colour) {
                $db->prepare("INSERT INTO user_scrolls (userid,colour_id,quantity) VALUES (?,?,1)
                              ON DUPLICATE KEY UPDATE quantity=quantity+1")
                   ->execute([$userid,$colour]);
            }
            $_SESSION['game_rewards'] = [
                ($scrollType==='climbing'?'🧗':'🎿').' '.ucfirst($scrollType).' scroll',
                '📜 Coloured scroll',
            ];
        }
    }
    header('Location: masterbuilder.php');
    exit();
}

// Reset
if (isset($_GET['reset'])) {
    unset($_SESSION['mb_phase'],$_SESSION['mb_set'],
          $_SESSION['mb_placed'],$_SESSION['mb_won']);
    header('Location: masterbuilder.php');
    exit();
}

// Fetch all sets
$sets = $db->query("SELECT * FROM builder_sets ORDER BY difficulty, name")->fetchAll();
foreach ($sets as &$s) $s['pieces_arr'] = json_decode($s['pieces'], true);

pageHeader('Master Builder', 'masterbuilder.php');
?>

<h1 class="page-title">🧱 Master Builder</h1>
<p class="page-sub">Place all pieces to complete the build and win rewards</p>

<?php if ($phase === 'setup'): ?>
<!-- SET SELECTION -->
<div class="grid-3">
  <?php foreach ($sets as $set): ?>
  <div class="card">
    <div style="text-align:center; font-size:3rem; margin-bottom:.5rem;">
      <?= $set['emoji'] ?>
    </div>
    <div style="font-family:'Cinzel',serif; font-size:.95rem; color:var(--gold);
         text-align:center; margin-bottom:.25rem;">
      <?= htmlspecialchars($set['name']) ?>
    </div>
    <div style="text-align:center; margin-bottom:.5rem;">
      <span class="badge badge-<?= $set['difficulty']==='easy'?'ground':($set['difficulty']==='medium'?'fire':'dark') ?>">
        <?= ucfirst($set['difficulty']) ?>
      </span>
      <span style="font-size:.75rem; color:var(--muted); margin-left:.4rem;">
        <?= $set['piece_count'] ?> pieces
      </span>
    </div>
    <div style="font-size:.75rem; color:var(--muted); margin-bottom:.75rem; line-height:1.5;">
      <?= implode(', ', array_slice($set['pieces_arr'], 0, 4)) ?>
      <?= count($set['pieces_arr']) > 4 ? '...' : '' ?>
    </div>
    <form method="post">
      <input type="hidden" name="set_id" value="<?= $set['id'] ?>">
      <button class="btn btn-outline" type="submit" name="start_set"
              style="width:100%;">Build This →</button>
    </form>
  </div>
  <?php endforeach; ?>
</div>

<?php elseif ($_SESSION['mb_phase'] === 'won'): ?>
<!-- WIN -->
<div style="max-width:480px; margin:0 auto;">
  <div class="card" style="border-color:var(--gold); text-align:center; padding:2rem;">
    <div style="font-size:4rem; margin-bottom:.5rem;"><?= $buildSet['emoji'] ?></div>
    <div style="font-family:'Cinzel',serif; font-size:1.3rem; color:var(--gold);
         margin-bottom:.25rem;">Build Complete!</div>
    <div style="font-size:.9rem; color:var(--muted); margin-bottom:1rem;">
      <?= htmlspecialchars($buildSet['name']) ?> — all <?= $buildSet['piece_count'] ?> pieces placed
    </div>
    <?php if (!empty($_SESSION['game_rewards'])): ?>
    <div style="background:var(--surface); border-radius:var(--radius); padding:.75rem;
         margin-bottom:1rem; text-align:left;">
      <div style="font-family:'Cinzel',serif; font-size:.7rem; color:var(--gold);
           margin-bottom:.3rem;">🎁 REWARDS</div>
      <?php foreach ($_SESSION['game_rewards'] as $r): ?>
        <div style="font-size:.85rem; color:var(--text);">✓ <?= $r ?></div>
      <?php endforeach; ?>
    </div>
    <?php unset($_SESSION['game_rewards']); endif; ?>
    <a href="masterbuilder.php?reset=1" class="btn btn-primary">Build Again</a>
    <a href="walk.php" class="btn btn-outline" style="margin-left:.5rem;">← Walk</a>
  </div>
</div>

<?php else: // BUILDING ?>
<div class="grid-2">

  <!-- BUILD AREA -->
  <div>
    <div class="card">
      <div class="card-title">
        <?= $buildSet['emoji'] ?> <?= htmlspecialchars($buildSet['name']) ?>
        — <?= count($placed) ?>/<?= count($buildSet['pieces_arr']) ?> pieces placed
      </div>

      <!-- PROGRESS BAR -->
      <?php $pct = round((count($placed)/count($buildSet['pieces_arr']))*100); ?>
      <div style="background:var(--border); border-radius:10px; height:8px;
           overflow:hidden; margin-bottom:1rem;">
        <div style="background:var(--gold); width:<?= $pct ?>%; height:100%;
             border-radius:10px; transition:width .4s;"></div>
      </div>

      <!-- PIECE GRID -->
      <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:.5rem;">
        <?php foreach ($buildSet['pieces_arr'] as $i=>$piece): ?>
        <?php $isPlaced = in_array($i, $placed); ?>
        <form method="post">
          <input type="hidden" name="piece_index" value="<?= $i ?>">
          <button type="submit" name="place_piece"
                  <?= $isPlaced ? 'disabled' : '' ?>
                  style="width:100%; padding:.5rem .75rem; text-align:left;
                         background:<?= $isPlaced ? 'rgba(39,174,96,.15)' : 'var(--surface)' ?>;
                         border:1px solid <?= $isPlaced ? 'var(--success)' : 'var(--border)' ?>;
                         border-radius:var(--radius); font-size:.8rem; cursor:pointer;
                         color:<?= $isPlaced ? 'var(--success)' : 'var(--text)' ?>;
                         transition:all .15s;"
                  onmouseenter="if(!this.disabled)this.style.borderColor='var(--gold-dim)'"
                  onmouseleave="if(!this.disabled)this.style.borderColor='var(--border)'">
            <?= $isPlaced ? '✓ ' : '○ ' ?>
            <?= htmlspecialchars($piece) ?>
          </button>
        </form>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- PREVIEW -->
  <div>
    <div class="card" style="text-align:center; padding:2rem;">
      <div style="font-size:6rem; margin-bottom:1rem;"><?= $buildSet['emoji'] ?></div>
      <div style="font-family:'Cinzel',serif; font-size:.85rem; color:var(--muted);">
        <?= htmlspecialchars($buildSet['name']) ?>
      </div>
      <div style="margin-top:1rem; font-size:.78rem; color:var(--muted); line-height:1.8;">
        Click each piece in order to place it on the build.<br>
        Complete all <?= count($buildSet['pieces_arr']) ?> pieces to win!
      </div>
      <div style="margin-top:1rem; padding:.65rem; background:var(--surface);
           border-radius:var(--radius); text-align:left; font-size:.75rem; color:var(--muted);">
        <strong style="color:var(--gold); font-family:'Cinzel',serif; font-size:.68rem;">
          DIFFICULTY:
        </strong>
        <?= ucfirst($buildSet['difficulty']) ?> · <?= $buildSet['piece_count'] ?> pieces
      </div>
    </div>

    <div style="text-align:center; margin-top:.75rem;">
      <a href="masterbuilder.php?reset=1" class="btn btn-danger btn-sm">
        Abandon Build
      </a>
      <a href="walk.php" class="btn btn-outline btn-sm" style="margin-left:.5rem;">
        ← Walk
      </a>
    </div>
  </div>

</div>
<?php endif; ?>

<?php pageFooter(); ?>