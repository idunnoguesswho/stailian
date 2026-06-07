<?php
require 'auth.php';
require 'db.php';
require 'layout.php';

$db     = getDB();
$userid = $SESSION_USERID;

// Init board
if (!isset($_SESSION['ttt_board'])) {
    $_SESSION['ttt_board']  = array_fill(0, 9, '');
    $_SESSION['ttt_turn']   = 'X'; // X = player, O = computer
    $_SESSION['ttt_over']   = false;
    $_SESSION['ttt_winner'] = null;
}

$board  = $_SESSION['ttt_board'];
$turn   = $_SESSION['ttt_turn'];
$over   = $_SESSION['ttt_over'];
$winner = $_SESSION['ttt_winner'];

function checkWinner(array $b): ?string {
    $lines = [
        [0,1,2],[3,4,5],[6,7,8], // rows
        [0,3,6],[1,4,7],[2,5,8], // cols
        [0,4,8],[2,4,6],         // diags
    ];
    foreach ($lines as [$a,$b2,$c]) {
        if ($b[$a] && $b[$a]===$b[$b2] && $b[$a]===$b[$c]) return $b[$a];
    }
    if (!in_array('', $b)) return 'draw';
    return null;
}

function computerMove(array $b): int {
    // Try to win
    $lines = [[0,1,2],[3,4,5],[6,7,8],[0,3,6],[1,4,7],[2,5,8],[0,4,8],[2,4,6]];
    foreach ($lines as [$a,$c,$d]) {
        if ($b[$a]==='O' && $b[$c]==='O' && $b[$d]==='') return $d;
        if ($b[$a]==='O' && $b[$d]==='O' && $b[$c]==='') return $c;
        if ($b[$c]==='O' && $b[$d]==='O' && $b[$a]==='') return $a;
    }
    // Block player
    foreach ($lines as [$a,$c,$d]) {
        if ($b[$a]==='X' && $b[$c]==='X' && $b[$d]==='') return $d;
        if ($b[$a]==='X' && $b[$d]==='X' && $b[$c]==='') return $c;
        if ($b[$c]==='X' && $b[$d]==='X' && $b[$a]==='') return $a;
    }
    // Take centre
    if ($b[4]==='') return 4;
    // Take corner
    foreach ([0,2,6,8] as $i) if ($b[$i]==='') return $i;
    // Take any
    foreach ($b as $i=>$v) if ($v==='') return $i;
    return -1;
}

// Player move
if (isset($_POST['cell']) && !$over && $turn==='X') {
    $cell = (int)$_POST['cell'];
    if ($board[$cell] === '') {
        $board[$cell] = 'X';
        $w = checkWinner($board);
        if ($w) {
            $over = true; $winner = $w;
        } else {
            // Computer move
            $cm = computerMove($board);
            if ($cm >= 0) {
                $board[$cm] = 'O';
                $w = checkWinner($board);
                if ($w) { $over = true; $winner = $w; }
            }
        }
        $_SESSION['ttt_board']  = $board;
        $_SESSION['ttt_over']   = $over;
        $_SESSION['ttt_winner'] = $winner;

        if ($over) {
            $result = $winner==='X' ? 'win' : ($winner==='draw' ? 'draw' : 'lose');
            $db->prepare("INSERT INTO side_game_log (userid,game_type,result) VALUES (?,?,?)")
               ->execute([$userid,'tictactoe',$result]);
            if ($result === 'win') {
                // Award rewards using same function
                require_once 'guesswho.php'; // contains awardGameReward — include carefully
            }
        }
    }
    header('Location: tictactoe.php');
    exit();
}

// Reset
if (isset($_GET['reset'])) {
    unset($_SESSION['ttt_board'],$_SESSION['ttt_turn'],
          $_SESSION['ttt_over'],$_SESSION['ttt_winner']);
    header('Location: tictactoe.php');
    exit();
}

pageHeader('Tic Tac Toe', 'tictactoe.php');
?>

<h1 class="page-title">⭕ Tic Tac Toe</h1>
<p class="page-sub">Beat the computer to win tower scrolls and bonus rewards</p>

<div style="max-width:400px; margin:0 auto;">

  <?php if ($over): ?>
  <div class="card" style="border-color:<?= $winner==='X'?'var(--gold)':($winner==='draw'?'var(--muted)':'var(--danger)') ?>;
       text-align:center; padding:2rem; margin-bottom:1rem;">
    <div style="font-size:3rem; margin-bottom:.5rem;">
      <?= $winner==='X'?'🏆':($winner==='draw'?'🤝':'💀') ?>
    </div>
    <div style="font-family:'Cinzel',serif; font-size:1.2rem;
         color:<?= $winner==='X'?'var(--gold)':($winner==='draw'?'var(--muted)':'var(--danger)') ?>;">
      <?= $winner==='X'?'You Win!':($winner==='draw'?'Draw!':'Computer Wins!') ?>
    </div>
    <?php if ($winner==='X' && !empty($_SESSION['game_rewards'])): ?>
    <div style="background:var(--surface); border-radius:var(--radius); padding:.75rem;
         margin:.75rem 0; text-align:left;">
      <div style="font-family:'Cinzel',serif; font-size:.7rem; color:var(--gold);
           margin-bottom:.3rem;">🎁 REWARDS</div>
      <?php foreach ($_SESSION['game_rewards'] as $r): ?>
        <div style="font-size:.85rem; color:var(--text);">✓ <?= $r ?></div>
      <?php endforeach; ?>
    </div>
    <?php unset($_SESSION['game_rewards']); endif; ?>
    <a href="tictactoe.php?reset=1" class="btn btn-primary">Play Again</a>
    <a href="walk.php" class="btn btn-outline" style="margin-left:.5rem;">← Walk</a>
  </div>
  <?php endif; ?>

  <!-- BOARD -->
  <div class="card">
    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:.5rem; margin-bottom:1rem;">
      <?php foreach ($board as $i=>$cell): ?>
      <form method="post" action="tictactoe.php">
        <input type="hidden" name="cell" value="<?= $i ?>">
        <button type="submit"
                <?= ($cell !== '' || $over) ? 'disabled' : '' ?>
                style="width:100%; aspect-ratio:1; font-size:2rem; cursor:pointer;
                       background:var(--surface); border:2px solid var(--border);
                       border-radius:var(--radius); color:<?= $cell==='X'?'var(--gold)':'var(--ice)' ?>;
                       transition:border-color .15s;"
                onmouseenter="if(!this.disabled)this.style.borderColor='var(--gold-dim)'"
                onmouseleave="if(!this.disabled)this.style.borderColor='var(--border)'">
          <?= $cell === 'X' ? '✕' : ($cell === 'O' ? '○' : '') ?>
        </button>
      </form>
      <?php endforeach; ?>
    </div>

    <?php if (!$over): ?>
    <div style="text-align:center; font-size:.82rem; color:var(--muted);">
      <?= $turn==='X' ? 'Your turn (✕)' : "Computer's turn (○)" ?>
    </div>
    <?php endif; ?>
  </div>

  <div style="text-align:center; margin-top:.75rem;">
    <a href="tictactoe.php?reset=1" class="btn btn-outline btn-sm">New Game</a>
    <a href="walk.php" class="btn btn-outline btn-sm" style="margin-left:.5rem;">← Walk</a>
  </div>

</div>

<?php pageFooter(); ?>