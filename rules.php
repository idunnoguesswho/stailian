<?php
require 'auth.php';
require 'db.php';
require 'health.php';
require 'layout.php';

$db     = getDB();
$userid = $SESSION_USERID;
$health = getHealth($db, $userid);

pageHeader('How to Play', 'rules.php');
?>

<h1 class="page-title">⚜ Welcome to Stailian</h1>
<p class="page-sub">A world of elemental power, exploration, craftsmanship and survival</p>

<div class="grid-2">

  <!-- LEFT COLUMN -->
  <div>

    <!-- THE WORLD -->
    <div class="card">
      <div class="card-title">🗺 The World</div>
      <p style="font-size:.95rem; color:var(--text); line-height:1.8; margin-bottom:.75rem;">
        Stailian is a 10×10 tile map of elemental terrain. Each tile belongs to one of
        twelve colour families — Volcano, Tundra, Forest, River, and more — each tied to
        a core element:
        <span class="badge badge-fire">🔥 Fire</span>
        <span class="badge badge-ice">❄ Ice</span>
        <span class="badge badge-water">💧 Water</span>
        <span class="badge badge-ground">🌍 Ground</span>
        <span class="badge badge-dark">🌑 Dark</span>.
      </p>
      <p style="font-size:.95rem; color:var(--text); line-height:1.8;">
        The map reshuffles at any time using the Randomize button. Magic ground tiles —
        where X equals Y — are special treasure squares marked ✨ where tile-matched
        bowties can be found.
      </p>
    </div>

    <!-- WALKING -->
    <div class="card">
      <div class="card-title">🎲 Walking & Moving</div>
      <p style="font-size:.95rem; color:var(--text); line-height:1.8; margin-bottom:.75rem;">
        Select your user on the <strong style="color:var(--gold)">Walk</strong> screen
        and roll two 12-sided dice. Die 1 sets your X coordinate, Die 2 sets your Y.
        You land on the nearest tile to those coordinates.
      </p>
      <div style="background:var(--surface); border-radius:var(--radius);
           padding:.75rem 1rem; border-left:3px solid var(--gold); margin-bottom:.75rem;">
        <div style="font-family:'Cinzel',serif; font-size:.72rem; letter-spacing:.08em;
             color:var(--gold); margin-bottom:.4rem;">EVERY ROLL</div>
        <ul style="font-size:.88rem; color:var(--muted); line-height:2.2;
             list-style:none; padding:0; margin:0;">
          <li>🌸 You gather one flower from the tile's colour</li>
          <li>📜 You pick up one scroll of that colour (max 30)</li>
          <li>⚙ You gather one resource from the tile</li>
          <li>🧙 Your character roams to a random tile</li>
        </ul>
      </div>
      <div style="background:var(--surface); border-radius:var(--radius);
           padding:.75rem 1rem; border-left:3px solid var(--gold);">
        <div style="font-family:'Cinzel',serif; font-size:.72rem; letter-spacing:.08em;
             color:var(--gold); margin-bottom:.4rem;">MAGIC GROUND ✨ (X = Y)</div>
        <p style="font-size:.88rem; color:var(--muted); line-height:1.8; margin:0;">
          When both dice show the same number you land on a magic ground tile.
          A tile-matched bowtie weapon appears in your inventory! Max 4 bowties —
          sell extras for 🪙 10 coins each.
        </p>
      </div>
    </div>
<!-- THREE WORLDS -->
<div class="card" style="border-color:var(--ice);">
  <div class="card-title" style="color:var(--ice);">🌐 Three World Layers</div>
  <div style="display:flex; flex-direction:column; gap:.6rem; margin-bottom:.75rem;">
    <?php foreach ([
      ['🌤','Sky',        'ice',   'var(--ice)',   'Ice amplified. Reached by climbing the tower at (5,5).'],
      ['🌍','Surface',    'ground','var(--ground)', 'The starting world. Balanced terrain.'],
      ['🔥','Underworld', 'dark',  'var(--fire)',   'Fire and Dark amplified. Reached by sliding down the tower.'],
    ] as [$icon,$name,$el,$col,$desc]): ?>
    <div style="padding:.65rem .85rem; background:var(--surface); border-radius:var(--radius);
         border-left:3px solid <?= $col ?>;">
      <div style="font-family:'Cinzel',serif; font-size:.78rem; color:<?= $col ?>;
           margin-bottom:.2rem;"><?= $icon ?> <?= $name ?></div>
      <div style="font-size:.82rem; color:var(--muted);"><?= $desc ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <div style="background:var(--surface); border-radius:var(--radius); padding:.65rem .85rem;
       font-size:.82rem; color:var(--muted); line-height:1.8;">
    🗼 The <strong style="color:var(--gold)">Tower at (5,5)</strong> connects all three worlds.<br>
    🧗 Climbing costs <strong>50 HP</strong> or a <strong>climbing scroll</strong>.<br>
    🎿 Sliding costs <strong>50 HP</strong> or a <strong>sliding scroll</strong>.<br>
    📜 Tower scrolls are won from side games.
  </div>
</div>

<!-- TIME OF DAY -->
<div class="card">
  <div class="card-title">🕐 Time of Day</div>
  <p style="font-size:.88rem; color:var(--muted); line-height:1.7; margin-bottom:.75rem;">
    The game clock advances 30 minutes per roll. Time of day affects elemental
    multipliers in battle — fight at the right time for maximum power!
  </p>
  <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:.4rem;">
    <?php foreach ([
      ['🌅','Dawn',      '5–8am',    'Ice boosted'],
      ['☀️','Morning',   '8am–12pm', 'Ground/Ice up'],
      ['🌤','Afternoon', '12–5pm',   'Water boosted'],
      ['🌇','Dusk',      '5–8pm',    'Fire boosted'],
      ['🌙','Night',     '8pm–12am', 'Dark boosted'],
      ['🌑','Midnight',  '12–5am',   'Dark/Ice/Fire max'],
    ] as [$icon,$name,$time,$effect]): ?>
    <div style="padding:.5rem; background:var(--surface); border-radius:var(--radius);
         border:1px solid var(--border); text-align:center; font-size:.72rem;">
      <div style="font-size:1.2rem;"><?= $icon ?></div>
      <div style="font-family:'Cinzel',serif; color:var(--text);"><?= $name ?></div>
      <div style="color:var(--muted); font-size:.65rem;"><?= $time ?></div>
      <div style="color:var(--gold); font-size:.65rem; margin-top:.15rem;"><?= $effect ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <div style="margin-top:.75rem; padding:.6rem .8rem; background:var(--surface);
       border-radius:var(--radius); font-size:.82rem; color:var(--muted);">
    😴 <strong style="color:var(--gold)">Sleep</strong> skips 8 hours and
    <strong style="color:var(--success)">fully restores health</strong>.
    No more auto-healing — rest is your only recovery (or a health pack).
  </div>
</div>

<!-- SIDE GAMES -->
<div class="card">
  <div class="card-title">🎮 Side Games</div>
  <p style="font-size:.88rem; color:var(--muted); line-height:1.7; margin-bottom:.75rem;">
    Play side games at any time or when prompted every 20 turns.
    Winning rewards you with tower scrolls, bowties, coloured scrolls and clothing.
  </p>
  <div style="display:flex; flex-direction:column; gap:.5rem;">
    <?php foreach ([
      ['🎭','Guess Who',     'Ask 10 yes/no questions to identify a hidden character. 3 guesses allowed.'],
      ['⭕','Tic Tac Toe',   'Beat the computer at tic tac toe. Simple but satisfying.'],
      ['🧱','Master Builder', 'Place all pieces of a small build in order to complete it. Up to 20 pieces.'],
    ] as [$icon,$name,$desc]): ?>
    <div style="padding:.65rem .85rem; background:var(--surface); border-radius:var(--radius);
         border:1px solid var(--border);">
      <div style="font-family:'Cinzel',serif; font-size:.82rem; color:var(--gold); margin-bottom:.2rem;">
        <?= $icon ?> <?= $name ?>
      </div>
      <div style="font-size:.8rem; color:var(--muted);"><?= $desc ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <div style="margin-top:.75rem; padding:.6rem .85rem; background:var(--surface);
       border-radius:var(--radius); font-size:.8rem; color:var(--muted);">
    🎁 Win rewards: 🧗/🎿 Tower scroll · 🎀 Bowtie · 📜 Coloured scroll · 👕 Clothing (50% chance)
  </div>
</div>
    <!-- HEALTH -->
    <div class="card" style="border-color:var(--danger);">
      <div class="card-title" style="color:var(--danger);">❤ Health System</div>
      <p style="font-size:.95rem; color:var(--text); line-height:1.8; margin-bottom:.75rem;">
        Every player has a health bar from 0 to 100. Losing a battle costs you HP
        based on how powerful your character was. Stay alive or you will be unable
        to fight effectively.
      </p>

      <!-- CURRENT HEALTH -->
      <div style="margin-bottom:1rem;">
        <?= healthBarHtml($health) ?>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:.75rem; margin-bottom:.75rem;">
        <div style="background:var(--surface); border-radius:var(--radius);
             padding:.65rem; border:1px solid var(--border); text-align:center;">
          <div style="font-size:1.5rem; margin-bottom:.25rem;">💔</div>
          <div style="font-family:'Cinzel',serif; font-size:.75rem; color:var(--danger);">
            Battle Damage
          </div>
          <div style="font-size:.78rem; color:var(--muted); margin-top:.2rem; line-height:1.6;">
            Lose 5 HP per<br>10 power the<br>character had
          </div>
        </div>
        <div style="background:var(--surface); border-radius:var(--radius);
             padding:.65rem; border:1px solid var(--border); text-align:center;">
          <div style="font-size:1.5rem; margin-bottom:.25rem;">💚</div>
          <div style="font-family:'Cinzel',serif; font-size:.75rem; color:var(--success);">
            Auto Heal
          </div>
          <div style="font-size:.78rem; color:var(--muted); margin-top:.2rem; line-height:1.6;">
            +10 HP every<br>minute<br>automatically
          </div>
        </div>
      </div>

      <div style="background:rgba(39,174,96,.08); border-radius:var(--radius);
           padding:.65rem 1rem; border-left:3px solid var(--success);">
        <div style="font-family:'Cinzel',serif; font-size:.72rem; color:var(--success);
             margin-bottom:.3rem;">🧪 HEALTH PACK</div>
        <p style="font-size:.85rem; color:var(--muted); margin:0; line-height:1.7;">
          Buy a Health Pack from the
          <a href="wardrobe.php?tab=shop" style="color:var(--gold)">Shop</a>
          for <strong style="color:var(--gold)">🪙 50 coins</strong>
          to instantly restore <strong style="color:var(--success)">+50 HP</strong>.
        </p>
      </div>
    </div>

    <!-- BATTLE -->
    <div class="card">
      <div class="card-title">⚔ Battle</div>
      <p style="font-size:.95rem; color:var(--text); line-height:1.8; margin-bottom:.75rem;">
        If you land on a tile occupied by your character, battle begins automatically.
        Each element is compared — the side winning the most elements wins.
        The loser is redirected to the nearest empty tile to (1,1).
      </p>
      <div style="background:var(--surface); border-radius:var(--radius);
           padding:.75rem 1rem; border-left:3px solid var(--fire); margin-bottom:.75rem;">
        <div style="font-family:'Cinzel',serif; font-size:.72rem; color:var(--fire);
             margin-bottom:.3rem;">TILE MULTIPLIERS</div>
        <p style="font-size:.88rem; color:var(--muted); line-height:1.8; margin:0;">
          Terrain amplifies certain elements in battle. Landing on a Volcano doubles
          🔥 Fire scores. Tundra doubles ❄ Ice. Use terrain to your advantage!
        </p>
      </div>
      <div style="background:var(--surface); border-radius:var(--radius);
           padding:.75rem 1rem; border-left:3px solid var(--ice);">
        <div style="font-family:'Cinzel',serif; font-size:.72rem; color:var(--ice);
             margin-bottom:.3rem;">YOUR POWER COMES FROM</div>
        <ul style="font-size:.88rem; color:var(--muted); line-height:2.2;
             list-style:none; padding:0; margin:0;">
          <li>✨ Skills assigned to your user (Attributes)</li>
          <li>🗡 Weapons and their elemental bonuses</li>
          <li>👕 Clothing dye bonuses</li>
          <li>🛡 Clothing shields reduce incoming damage</li>
        </ul>
      </div>
    </div>

  </div>

  <!-- RIGHT COLUMN -->
  <div>

    <!-- SCROLLS FLOWERS DYES -->
    <div class="card">
      <div class="card-title">📜 Scrolls, Flowers & Dyes</div>
      <div style="display:flex; flex-direction:column; gap:.6rem;">
        <?php foreach ([
          ['🌸','FLOWERS','#e8622a',
           'Gathered from every tile you land on. Max 120 total. 15 flowers of one colour convert into 1 scroll of that colour. More flowers = darker dye shade = higher skill bonus.'],
          ['📜','SCROLLS','var(--gold)',
           'Collected from every tile. Max 30 total. Sell excess for 🪙 1 coin each. Collect 5 scrolls of the same colour to craft a dye.'],
          ['⚗','DYES','var(--ice)',
           'Craft dyes on the Wardrobe Craft tab. Shade level is set by your flower count — Shade 1 gives +2, Shade 10 gives +20 skill bonus. Apply to clothing then the dye is consumed.'],
        ] as [$icon,$title,$col,$desc]): ?>
        <div style="background:var(--surface); border-radius:var(--radius);
             padding:.7rem .9rem; border-left:3px solid <?= $col ?>;">
          <div style="font-family:'Cinzel',serif; font-size:.72rem;
               color:<?= $col ?>; margin-bottom:.3rem;">
            <?= $icon ?> <?= $title ?>
          </div>
          <p style="font-size:.85rem; color:var(--muted); line-height:1.7; margin:0;">
            <?= $desc ?>
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
        from the Shop with coins, dye it with crafted dyes to add elemental skill bonuses,
        and equip one item per slot to activate its effects in battle.
        Clothing can also be found scattered across the map!
      </p>
      <div style="display:grid; grid-template-columns:1fr 1fr 1fr;
           gap:.5rem; margin-bottom:.75rem;">
        <?php foreach ([
          ['👕','Shirt','Shield up to -15'],
          ['👖','Pants','Shield up to -12'],
          ['🧦','Socks', 'Shield up to -8'],
        ] as [$icon,$name,$desc]): ?>
        <div style="background:var(--surface); border-radius:var(--radius);
             padding:.6rem; text-align:center; border:1px solid var(--border);">
          <div style="font-size:1.6rem; margin-bottom:.25rem;"><?= $icon ?></div>
          <div style="font-family:'Cinzel',serif; font-size:.72rem; color:var(--text);">
            <?= $name ?>
          </div>
          <div style="font-size:.68rem; color:var(--muted); margin-top:.15rem;">
            <?= $desc ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- WEAPON CRAFTING -->
    <div class="card">
      <div class="card-title">⚒ Weapon Crafting</div>
      <p style="font-size:.95rem; color:var(--text); line-height:1.8; margin-bottom:.75rem;">
        Weapon scrolls are hidden across the map. Find one to learn how to craft
        a powerful weapon using up to 2 tile resources. Once crafted, the scroll
        drops back to the tile you're standing on for another player to find.
      </p>
      <div style="background:var(--surface); border-radius:var(--radius);
           padding:.75rem; border:1px solid var(--border); font-size:.82rem;
           color:var(--muted); line-height:2;">
        📜 Find scroll → ⚙ Gather resources → ⚒ Craft weapon → 🗡 Gain power
      </div>
    </div>

    <!-- COINS -->
    <div class="card">
      <div class="card-title">🪙 Coins</div>
      <div style="display:flex; flex-direction:column; gap:.4rem;">
        <?php foreach ([
          ['Sell a bowtie',      '🪙 10 coins', 'gold'],
          ['Sell a scroll',      '🪙 1 coin',   'gold'],
          ['Buy health pack',    '🪙 50 coins', 'muted'],
          ['Buy basic clothing', '🪙 0–30',     'muted'],
          ['Buy elder clothing', '🪙 40–80',    'muted'],
        ] as [$action,$reward,$col]): ?>
        <div style="display:flex; justify-content:space-between; align-items:center;
             padding:.45rem .75rem; background:var(--surface);
             border-radius:var(--radius); border:1px solid var(--border);">
          <span style="font-size:.88rem; color:var(--text);"><?= $action ?></span>
          <span style="font-family:'Cinzel',serif; font-size:.82rem;
               color:var(--<?= $col ?>);"><?= $reward ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- QUICK REFERENCE -->
    <div class="card">
      <div class="card-title">⚡ Quick Reference</div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:.35rem; font-size:.82rem;">
        <?php foreach ([
          ['Max scrolls',     '30'],
          ['Max flowers',     '120'],
          ['Max bowties',     '4'],
          ['Scrolls for dye', '5'],
          ['Flowers→scroll',  '15'],
          ['Bowtie sell',     '🪙 10'],
          ['Scroll sell',     '🪙 1'],
          ['Health pack',     '🪙 50'],
          ['Pack heals',      '+50 HP'],
          ['Auto heal',       '+10/min'],
          ['Battle damage',   '5HP/10pw'],
          ['Max health',      '100 HP'],
          ['Shade levels',    '1–10'],
          ['Dice',            '2× d12'],
          ['Magic ground',    'X = Y'],
          ['Map size',        '10×10'],
        ] as [$label,$val]): ?>
        <div style="display:flex; justify-content:space-between; padding:.3rem .55rem;
             background:var(--surface); border-radius:var(--radius);
             border:1px solid var(--border);">
          <span style="color:var(--muted); font-size:.75rem;"><?= $label ?></span>
          <span style="font-family:'Cinzel',serif; color:var(--gold);
               font-size:.75rem;"><?= $val ?></span>
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
        <a class="btn btn-primary"  href="walk.php">🎲 Start Walking</a>
        <a class="btn btn-outline"  href="wardrobe.php">👕 Wardrobe</a>
        <a class="btn btn-outline"  href="scorecard.php">🃏 Scorecard</a>
        <a class="btn btn-outline"  href="map.php">🗺 View Map</a>
      </div>
    </div>

  </div>
</div>

<?php pageFooter(); ?>