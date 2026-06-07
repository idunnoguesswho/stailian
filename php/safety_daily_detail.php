<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'db.php';
require 'layout.php';

pageHeader('Safety — Daily Detail', 'safety_daily_detail.php');

$days = ['May 16 Sat','May 17 Sun','May 18 Mon','May 19 Tue','May 20 Wed','May 21 Thu','May 22 Fri','May 23 Sat','May 24 Sun','May 25 Mon','May 26 Tue'];

// null = not on site
$sites = [
  [
    'name' => 'Lebanon', 'pct' => '67%',
    'workers' => [
      ['name'=>'Justin Coppens',   'pct'=>'100%','days'=>['✓',null,'✓','✓','✓',null,null,null,null,null,null]],
      ['name'=>'Shane Weibelzahl', 'pct'=>'78%', 'days'=>['✓',null,'✓','✓','✓','✓','✓','✓',null,'✗','✗']],
      ['name'=>'Aarron Collins',   'pct'=>'67%', 'days'=>['✓',null,'✓','✓','✓','✓','✗','✓',null,'✗','✗']],
      ['name'=>'Jared Markham',    'pct'=>'67%', 'days'=>['✗',null,'✓','✓','✓','✓','✓','✓',null,'✗','✗']],
      ['name'=>'Shaun Shipalesky', 'pct'=>'67%', 'days'=>['✗',null,'✓','✓','✓','✓','✓','✓',null,'✗','✗']],
      ['name'=>'Ramiro Rangel',    'pct'=>'50%', 'days'=>[null,null,null,null,'✗','✓','✓','✓',null,'✗','✗']],
      ['name'=>'Tyler Keel',       'pct'=>'50%', 'days'=>[null,null,null,null,'✗','✓','✓','✓',null,'✗','✗']],
    ],
  ],
  [
    'name' => 'Woolfolk', 'pct' => '64%',
    'workers' => [
      ['name'=>'Codey Carter',    'pct'=>'71%','days'=>['✓',null,'✓','✓','✓','✓','✗',null,null,null,'✗']],
      ['name'=>'Jose Quintana',   'pct'=>'67%','days'=>['✓',null,'✓','✓','✓','✓','✓','✗',null,'✗','✗']],
      ['name'=>'Roy Zarraga',     'pct'=>'62%','days'=>[null,null,'✓','✓','✓','✓','✓','✗',null,'✗','✗']],
      ['name'=>'Gerard Scranton', 'pct'=>'56%','days'=>['✗',null,'✓','✓','✗','✓','✓','✓',null,'✗','✗']],
    ],
  ],
  [
    'name' => 'Crystal Falls', 'pct' => '56%',
    'workers' => [
      ['name'=>'Conor Donaghy','pct'=>'56%','days'=>['✓',null,'✓','✗','✓','✓','✗','✗',null,'✗','✓']],
    ],
  ],
  [
    'name' => 'Mount Bracey', 'pct' => '53%',
    'workers' => [
      ['name'=>'James Desjardins','pct'=>'80%','days'=>['✓','✓','✓','✓','✓','✓','✓','✓','✗','✗',null]],
      ['name'=>'Peter Bennett',   'pct'=>'80%','days'=>['✓','✓','✓','✓','✓','✓','✓','✓','✗','✗',null]],
      ['name'=>'Julia Campbell',  'pct'=>'57%','days'=>[null,null,null,'✗','✓','✓','✓','✓','✗','✗',null]],
      ['name'=>'Sean Brower',     'pct'=>'50%','days'=>['✓','✓','✓','✗','✗','✓','✗','✓','✗','✗',null]],
      ['name'=>'Kirk Pilsworth',  'pct'=>'40%','days'=>[null,null,null,null,null,null,'✓','✓','✗','✗','✗']],
      ['name'=>'Todd Zelman',     'pct'=>'40%','days'=>['✗','✗','✓','✗','✓','✗','✓','✓','✗','✗',null]],
      ['name'=>'Greg Banack',     'pct'=>'20%','days'=>['✗','✗','✗','✗','✗','✓','✓','✗','✗','✗',null]],
    ],
  ],
  [
    'name' => 'Holmes', 'pct' => '25%',
    'workers' => [
      ['name'=>'Benjamin Ball','pct'=>'25%','days'=>[null,null,'✗','✓','✓','✗','✗','✗',null,'✗','✗']],
    ],
  ],
  [
    'name' => 'Deer River', 'pct' => '0%',
    'workers' => [
      ['name'=>'Kevin Knopp','pct'=>'0%','days'=>[null,null,'✗','✗','✗','✗','✗','✗',null,'✗','✗']],
    ],
  ],
  [
    'name' => 'Naubinway', 'pct' => '0%',
    'workers' => [
      ['name'=>'Spencer Macpherson','pct'=>'0%','days'=>['✗',null,'✗','✗','✗','✗','✗','✗',null,'✗','✗']],
    ],
  ],
  [
    'name' => 'Unassigned', 'pct' => '3%',
    'workers' => [
      ['name'=>'Brian Durand',      'pct'=>'20%','days'=>[null,null,null,null,null,null,'✗','✓','✗','✗','✗']],
      ['name'=>'Mike McCarthy',     'pct'=>'20%','days'=>[null,null,null,null,null,null,'✗','✓','✗','✗','✗']],
      ['name'=>'Bradley Stach',     'pct'=>'0%', 'days'=>[null,null,'✗','✗','✗','✗','✗',null,null,null,null]],
      ['name'=>'Dale Rogan',        'pct'=>'0%', 'days'=>[null,null,null,null,null,null,'✗','✗','✗','✗','✗']],
      ['name'=>'Jeffrey Cholowski', 'pct'=>'0%', 'days'=>['✗',null,'✗','✗','✗','✗','✗','✗',null,'✗','✗']],
      ['name'=>'Jim Thompson',      'pct'=>'0%', 'days'=>['✗',null,'✗','✗','✗','✗','✗','✗',null,'✗','✗']],
      ['name'=>'Kevin ter Kuile',   'pct'=>'0%', 'days'=>[null,null,null,null,null,null,null,null,null,'✗','✗']],
      ['name'=>'Lyle Roache',       'pct'=>'0%', 'days'=>['✗',null,null,null,null,null,null,null,null,null,null]],
      ['name'=>'Marco Garza',       'pct'=>'0%', 'days'=>[null,null,'✗','✗','✗','✗','✗',null,null,null,null]],
      ['name'=>'Rob Cooper',        'pct'=>'0%', 'days'=>['✗',null,'✗','✗','✗','✗','✗','✗',null,'✗','✗']],
      ['name'=>'Rodolfo Pineda',    'pct'=>'0%', 'days'=>['✗',null,null,null,null,null,null,null,null,null,null]],
      ['name'=>'Sergio Garza',      'pct'=>'0%', 'days'=>['✗',null,'✗','✗','✗','✗','✗','✗',null,'✗','✗']],
      ['name'=>'Wayne Jamieson',    'pct'=>'0%', 'days'=>[null,null,null,null,null,null,null,null,null,null,'✗']],
    ],
  ],
];
?>

<h1 class="page-title">📅 Daily JSA Detail</h1>
<p class="page-sub">
  <span style="color:#27ae60;">✓ Signed</span> &nbsp;|&nbsp;
  <span style="color:#c0392b;">✗ Missed</span> &nbsp;|&nbsp;
  <span style="color:var(--muted);">blank = not on site</span>
</p>

<div style="margin-bottom:1rem;display:flex;gap:.75rem;flex-wrap:wrap;">
  <a href="safety_dashboard.php" class="btn">← Dashboard</a>
  <a href="safety_site_detail.php" class="btn">📊 Site Detail</a>
</div>

<?php foreach ($sites as $site): ?>
<div class="card" style="margin-bottom:1.25rem;">
  <div class="card-title">
    <?= htmlspecialchars($site['name']) ?>
    <span style="font-size:.8rem;font-weight:400;color:var(--muted);margin-left:.5rem;">— JSA <?= $site['pct'] ?></span>
  </div>
  <div style="overflow-x:auto;margin-top:.75rem;">
    <table style="width:100%;border-collapse:collapse;font-size:.82rem;min-width:680px;">
      <thead>
        <tr style="font-family:'Cinzel',serif;font-size:.65rem;letter-spacing:.05em;color:var(--muted);border-bottom:1px solid var(--border);">
          <th style="text-align:left;padding:.4rem .5rem;white-space:nowrap;">Worker</th>
          <th style="text-align:center;padding:.4rem .3rem;">JSA %</th>
          <?php foreach ($days as $d): ?>
          <th style="text-align:center;padding:.4rem .25rem;white-space:nowrap;"><?= str_replace(' ',"\n",$d) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($site['workers'] as $w):
            $pct = (int)$w['pct'];
            $pc  = $pct >= 80 ? '#27ae60' : ($pct >= 50 ? '#e8a020' : '#c0392b');
        ?>
        <tr style="border-bottom:1px solid rgba(255,255,255,.04);">
          <td style="padding:.4rem .5rem;color:var(--text);white-space:nowrap;"><?= htmlspecialchars($w['name']) ?></td>
          <td style="text-align:center;padding:.4rem .3rem;font-weight:700;color:<?= $pc ?>;"><?= $w['pct'] ?></td>
          <?php foreach ($w['days'] as $cell):
              if ($cell === '✓') {
                  $style = 'color:#27ae60;font-size:1rem;';
              } elseif ($cell === '✗') {
                  $style = 'color:#c0392b;font-size:1rem;';
              } else {
                  $style = 'color:transparent;';
              }
          ?>
          <td style="text-align:center;padding:.4rem .25rem;<?= $style ?>"><?= $cell ?? '·' ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>

<?php pageFooter(); ?>
