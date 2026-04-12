<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'db.php';
require 'layout.php';

pageHeader('Welcome', 'index.php');
?>

<h1 class="page-title">⚜ Welcome to Stailian</h1>
<p class="page-sub">A world of elemental power, exploration, and craftsmanship</p>

<div class="grid-2">

  <!-- LEFT COLUMN -->
  <div>

    <!-- THE WORLD -->
    <div class="card">
      <div class="card-title">🗺 The World</div>
      <p style="font-size:.95rem; color:var(--text); line-height:1.8; margin-bottom:.75rem;">
        Stailian is a 10×10 tile map of elemental terrain. Each tile belongs to one of
        twelve colour families — Volcano, Tundra, Forest, River, and more — each tied to
        a core element: <span class="badge badge-fire">🔥 Fire</span>
        <span class="badge badge-ice">❄ Ice</span>
        <span class="badge badge-water">💧 Water</span>
        <span class="badge badge-ground">🌍 Ground</span>
        <span class="badge badge-dark">🌑 Dark</span>.
      </p>
      <p style="font-size:.95rem; color:var(--text); line-height:1.8;">
        The map is reshuffled at any time using the Randomize button, keeping every game
        fresh. Magic ground tiles — where X equals Y on the map — are special treasure
        squares marked ✨.
      </p>
    </div>

    <!-- WALKING -->
    <div class="card">
      <div class="card-title">🎲 Walking & Moving</div>
      <p style="font-size:.95rem; color:var(--text); line-height:1.8; margin-bottom:.75rem;">
        On the <strong style="color:var(--gold)">Walk</strong> screen, select your user
        and roll two 12-sided dice. Die 1 sets your X coordinate, Die 2 sets your Y.
        You land on the nearest tile to those coordinates.
      </p>
      <div style="background:var(--surface); border-radius:var(--radius); padding:.75rem 1rem;
           border-left:3px solid var(--gold); margin-bottom:.75rem;">
        <div style="font-family:'Cinzel',serif; font-size:.75rem; letter-spacing:.08em;
             color:var(--gold); margin-bottom:.4rem;">EVERY ROLL</div>
        <ul style="font-size:.88rem; color:var(--muted); line-height:2; list-style:none; padding:0;">
          <li>🌸 You gather one flower from the tile's colour</li>
          <li>📚 You pick up one scroll of that colour (max 20 total)</li>
          <li>🧙 All characters roam to random tiles</li>
        </ul>
      </div>
      <div style="background:var(--surface); border-radius:var(--radius); padding:.75rem 1rem;
           border-left:3px solid var(--gold);">
        <div style="font-family:'Cinzel',serif; font-size:.75rem; letter-spacing:.08em;
             color:var(--gold); margin-bottom:.4rem;">MAGIC GROUND ✨ (X = Y)</div>
        <p style="font-size:.88rem; color:var(--muted); line-height:1.8; margin:0;">
          When both dice show the same number you land on a magic ground tile.
          A bowtie weapon matching the tile's element appears in your inventory!
          You can carry a maximum of 4 bowties. If you exceed this, sell one
          on the Wardrobe screen for 🪙 10 coins.
        </p>
      </div>
    </div>

    <!-- BATTLE -->
    <div class="card">
      <div class="card-title">⚔ Battle</div>
      <p style="font-size:.95rem; color:var(--text); line-height:1.8; margin-bottom:.75rem;">
        If you land on a tile occupied by a character, battle begins automatically.
        Each element is compared individually — ice vs ice, fire vs fire, and so on.
        The side that wins the most elements wins the battle.
      </p>
      <div style="background:var(--surface); border-radius:var(--radius); padding:.75rem 1rem;
           border-left:3px solid var(--fire); margin-bottom:.75rem;">
        <div style="font-family:'Cinzel',serif; font-size:.75rem; letter-spacing:.08em;
             color:var(--fire); margin-bottom:.4rem;">TILE MULTIPLIERS</div>
        <p style="font-size:.88rem; color:var(--muted); line-height:1.8; margin:0;">
          The terrain you fight on amplifies certain elements. Landing on a Volcano tile
          doubles 🔥 Fire scores for both sides. Landing on Tundra doubles ❄ Ice.
          Use the terrain to your advantage!
        </p>
      </div>
      <div style="background:var(--surface); border-radius:var(--radius); padding:.75rem 1rem;
           border-left:3px solid var(--ice);">
        <div style="font-family:'Cinzel',serif; font-size:.75rem; letter-spacing:.08em;
             color:var(--ice); margin-bottom:.4rem;">YOUR POWER COMES FROM</div>
        <ul style="font-size:.88rem; color:var(--muted); line-height:2; list-style:none; padding:0;">
          <li>✨ Skills assigned to your user (via Attributes)</li>
          <li>🗡 Weapons in your inventory and their skill bonuses</li>
          <li>👕 Clothing dye bonuses from coloured dyes</li>
        </ul>
      </div>
    </div>

    <!-- BATTLE ARENA -->
    <div class="card">
      <div class="card-title">🏟 Battle Arena</div>
      <p style="font-size:.95rem; color:var(--text); line-height:1.8;">
        Visit the <strong style="color:var(--gold)">Battle</strong> screen to pit any
        user directly against any character outside of the walk system. Compare elemental
        scores head to head, see split bars per element, and view an overall winner.
        Tile multipliers are not applied here — it's a pure power comparison.
      </p>
    </div>

  </div>

  <!-- RIGHT COLUMN -->
  <div>

    <!-- SCROLLS FLOWERS DYES -->
    <div class="card">
      <div class="card-title">📚 Scrolls, Flowers & Dyes</div>
      <div style="display:flex; flex-direction:column; gap:.6rem;">
        <?php foreach ([
          ['🌸','FLOWERS',  'Gathered automatically from every tile you land on. Max 120 flowers total. Each flower colour matches the tile type you landed on. 15 flowers of one colour convert into 1 scroll of that colour.'],
          ['📚','SCROLLS',    'Also gathered on every tile. Max 20 scrolls total. Sell excess scrolls for 🪙 1 coin each on the Scrolls &amp; Flowers tab. Collect 5 scrolls of the same colour to craft a dye.'],
          ['⚗', 'DYES',    'Craft dyes on the Wardrobe → Craft tab. The shade level is determined by how many flowers of that colour you have — more flowers means a darker shade and a higher skill bonus. Shade 1 gives +2, shade 10 gives +20.'],
        ] as [$icon, $label, $text]): ?>
        <div style="background:var(--surface); border-radius:var(--radius); padding:.7rem .9rem;">
          <div style="font-family:'Cinzel',serif; font-size:.78rem; color:var(--gold); margin-bottom:.3rem;">
            <?= $icon ?> <?= $label ?>
          </div>
          <p style="font-size:.88rem; color:var(--muted); line-height:1.7; margin:0;">
            <?= $text ?>
          </p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- WARDROBE -->
    <div class="card">
      <div class="card-title">👕 Wardrobe</div>
      <p style="font-size:.95rem; color:var(--text); line-height:1.8; margin-bottom:.75rem;">
        Every user has three clothing slots — shirt, pants, and socks. Buy clothing
        from the Shop with coins, then dye it using crafted dyes to add elemental
        skill bonuses. Equip one item per slot to activate its effects in battle.
      </p>
      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:.5rem; margin-bottom:.75rem;">
        <?php foreach ([
          ['👕','Shirt','Strongest shield — up to -15'],
          ['👖','Pants','Medium shield — up to -12'],
          ['🧦','Socks', 'Light shield — up to -8'],
        ] as [$icon, $name, $desc]): ?>
        <div style="background:var(--surface); border-radius:var(--radius); padding:.6rem;
             text-align:center; border:1px solid var(--border);">
          <div style="font-size:1.6rem; margin-bottom:.25rem;"><?= $icon ?></div>
          <div style="font-family:'Cinzel',serif; font-size:.72rem; color:var(--text);"><?= $name ?></div>
          <div style="font-size:.68rem; color:var(--muted); margin-top:.15rem;"><?= $desc ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <p style="font-size:.88rem; color:var(--muted); line-height:1.7;">
        Higher tier clothing (Elder series) costs more coins but provides much
        stronger elemental shields — reducing incoming damage from that element in battle.
      </p>
    </div>

    <!-- COINS -->
    <div class="card">
      <div class="card-title">🪙 Coins</div>
      <div style="display:flex; flex-direction:column; gap:.5rem;">
        <?php foreach ([
          ['Sell a bowtie',     '🪙 10 coins', 'gold'],
          ['Sell a scroll',       '🪙 1 coin',   'gold'],
          ['Buy basic clothing','🪙 0–30 coins','muted'],
          ['Buy elder clothing','🪙 40–80 coins','muted'],
        ] as [$action, $reward, $col]): ?>
        <div style="display:flex; justify-content:space-between; align-items:center;
             padding:.5rem .75rem; background:var(--surface); border-radius:var(--radius);
             border:1px solid var(--border);">
          <span style="font-size:.88rem; color:var(--text);"><?= $action ?></span>
          <span style="font-family:'Cinzel',serif; font-size:.82rem; color:var(--<?= $col ?>);">
            <?= $reward ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- QUICK REFERENCE -->
    <div class="card">
      <div class="card-title">⚡ Quick Reference</div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:.4rem; font-size:.82rem;">
        <?php foreach ([
          ['Max scrolls','20'],       ['Max flowers','120'],
          ['Max bowties','4'],      ['Scrolls for dye','5'],
          ['Flowers for scroll','15'],['Bowtie sell','🪙 10'],
          ['Scroll sell','🪙 1'],    ['Shade levels','1–10'],
          ['Dice','2× d12'],        ['Magic ground','X = Y'],
          ['Map size','10×10'],     ['Elements','5'],
        ] as [$label, $val]): ?>
        <div style="display:flex; justify-content:space-between; padding:.35rem .6rem;
             background:var(--surface); border-radius:var(--radius); border:1px solid var(--border);">
          <span style="color:var(--muted);"><?= $label ?></span>
          <span style="font-family:'Cinzel',serif; color:var(--gold);"><?= $val ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- GET STARTED -->
    <div class="card" style="border-color:var(--gold); text-align:center; padding:1.5rem;">
      <div style="font-size:2rem; margin-bottom:.5rem;">⚜</div>
      <div style="font-family:'Cinzel',serif; font-size:1.1rem; color:var(--gold);
           margin-bottom:.75rem; letter-spacing:.05em;">Ready to Play?</div>
      <div style="display:flex; gap:.75rem; justify-content:center; flex-wrap:wrap;">
        <?php if (!empty($_SESSION['userid'])): ?>
          <a class="btn btn-primary"  href="walk.php">🎲 Start Walking</a>
          <a class="btn btn-outline"  href="wardrobe.php">👕 Wardrobe</a>
          <a class="btn btn-outline"  href="battle.php">⚔ Battle Arena</a>
          <a class="btn btn-outline"  href="map.php">🗺 View Map</a>
        <?php else: ?>
          <a class="btn btn-primary"  href="login.php">Sign In →</a>
          <a class="btn btn-outline"  href="register.php">⚜ Create Account</a>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?php pageFooter(); ?>
