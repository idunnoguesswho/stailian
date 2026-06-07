<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'db.php';
require 'layout.php';

pageHeader('Safety — Site Detail', 'safety_site_detail.php');

function compliance_color(string $pct): string {
    if ($pct === 'N/A') return 'var(--muted)';
    $n = (int)$pct;
    if ($n >= 80) return '#27ae60';
    if ($n >= 50) return '#e8a020';
    return '#c0392b';
}

function compliance_badge(string $pct): string {
    $color = compliance_color($pct);
    return '<span style="display:inline-block;padding:.1rem .4rem;border-radius:4px;
            font-weight:700;font-size:.82rem;background:' . $color . '22;color:' . $color . ';">'
            . htmlspecialchars($pct) . '</span>';
}

$sites = [
  [
    'name' => 'Lebanon',
    'jsa_pct' => '67%', 'share_pct' => '43%', 'workers_count' => 7,
    'workers' => [
      ['name'=>'Justin Coppens',    'd'=>4, 'js'=>4,'jm'=>0,'jp'=>'100%','sr'=>1,'sd'=>0,'sp'=>'0%', 'vi'=>0,'dr'=>3],
      ['name'=>'Shane Weibelzahl', 'd'=>9, 'js'=>7,'jm'=>2,'jp'=>'78%', 'sr'=>1,'sd'=>0,'sp'=>'0%', 'vi'=>0,'dr'=>0],
      ['name'=>'Aarron Collins',   'd'=>9, 'js'=>6,'jm'=>3,'jp'=>'67%', 'sr'=>1,'sd'=>0,'sp'=>'0%', 'vi'=>0,'dr'=>0],
      ['name'=>'Jared Markham',    'd'=>9, 'js'=>6,'jm'=>3,'jp'=>'67%', 'sr'=>1,'sd'=>1,'sp'=>'100%','vi'=>0,'dr'=>0],
      ['name'=>'Shaun Shipalesky', 'd'=>9, 'js'=>6,'jm'=>3,'jp'=>'67%', 'sr'=>1,'sd'=>1,'sp'=>'100%','vi'=>0,'dr'=>0],
      ['name'=>'Ramiro Rangel',    'd'=>6, 'js'=>3,'jm'=>3,'jp'=>'50%', 'sr'=>1,'sd'=>0,'sp'=>'0%', 'vi'=>0,'dr'=>0],
      ['name'=>'Tyler Keel',       'd'=>6, 'js'=>3,'jm'=>3,'jp'=>'50%', 'sr'=>1,'sd'=>1,'sp'=>'100%','vi'=>0,'dr'=>2],
    ],
  ],
  [
    'name' => 'Woolfolk',
    'jsa_pct' => '64%', 'share_pct' => '0%', 'workers_count' => 4,
    'workers' => [
      ['name'=>'Codey Carter',    'd'=>7,'js'=>5,'jm'=>2,'jp'=>'71%','sr'=>1,'sd'=>0,'sp'=>'0%','vi'=>1,'dr'=>4],
      ['name'=>'Jose Quintana',   'd'=>9,'js'=>6,'jm'=>3,'jp'=>'67%','sr'=>1,'sd'=>0,'sp'=>'0%','vi'=>0,'dr'=>0],
      ['name'=>'Roy Zarraga',     'd'=>8,'js'=>5,'jm'=>3,'jp'=>'62%','sr'=>1,'sd'=>0,'sp'=>'0%','vi'=>0,'dr'=>0],
      ['name'=>'Gerard Scranton', 'd'=>9,'js'=>5,'jm'=>4,'jp'=>'56%','sr'=>1,'sd'=>0,'sp'=>'0%','vi'=>0,'dr'=>3],
    ],
  ],
  [
    'name' => 'Crystal Falls',
    'jsa_pct' => '56%', 'share_pct' => '0%', 'workers_count' => 1,
    'workers' => [
      ['name'=>'Conor Donaghy','d'=>9,'js'=>5,'jm'=>4,'jp'=>'56%','sr'=>1,'sd'=>0,'sp'=>'0%','vi'=>2,'dr'=>0],
    ],
  ],
  [
    'name' => 'Mount Bracey',
    'jsa_pct' => '53%', 'share_pct' => '43%', 'workers_count' => 7,
    'workers' => [
      ['name'=>'James Desjardins','d'=>10,'js'=>8,'jm'=>2,'jp'=>'80%','sr'=>1,'sd'=>1,'sp'=>'100%','vi'=>0,'dr'=>0],
      ['name'=>'Peter Bennett',   'd'=>10,'js'=>8,'jm'=>2,'jp'=>'80%','sr'=>1,'sd'=>1,'sp'=>'100%','vi'=>0,'dr'=>0],
      ['name'=>'Julia Campbell',  'd'=>7, 'js'=>4,'jm'=>3,'jp'=>'57%','sr'=>1,'sd'=>0,'sp'=>'0%', 'vi'=>0,'dr'=>0],
      ['name'=>'Sean Brower',     'd'=>10,'js'=>5,'jm'=>5,'jp'=>'50%','sr'=>1,'sd'=>0,'sp'=>'0%', 'vi'=>1,'dr'=>0],
      ['name'=>'Kirk Pilsworth',  'd'=>5, 'js'=>2,'jm'=>3,'jp'=>'40%','sr'=>1,'sd'=>0,'sp'=>'0%', 'vi'=>0,'dr'=>0],
      ['name'=>'Todd Zelman',     'd'=>10,'js'=>4,'jm'=>6,'jp'=>'40%','sr'=>1,'sd'=>1,'sp'=>'100%','vi'=>0,'dr'=>0],
      ['name'=>'Greg Banack',     'd'=>10,'js'=>2,'jm'=>8,'jp'=>'20%','sr'=>1,'sd'=>0,'sp'=>'0%', 'vi'=>0,'dr'=>0],
    ],
  ],
  [
    'name' => 'Holmes',
    'jsa_pct' => '25%', 'share_pct' => '0%', 'workers_count' => 1,
    'workers' => [
      ['name'=>'Benjamin Ball','d'=>8,'js'=>2,'jm'=>6,'jp'=>'25%','sr'=>1,'sd'=>0,'sp'=>'0%','vi'=>0,'dr'=>0],
    ],
  ],
  [
    'name' => 'Deer River',
    'jsa_pct' => '0%', 'share_pct' => '0%', 'workers_count' => 1,
    'workers' => [
      ['name'=>'Kevin Knopp','d'=>8,'js'=>0,'jm'=>8,'jp'=>'0%','sr'=>1,'sd'=>0,'sp'=>'0%','vi'=>0,'dr'=>0],
    ],
  ],
  [
    'name' => 'Naubinway',
    'jsa_pct' => '0%', 'share_pct' => '0%', 'workers_count' => 1,
    'workers' => [
      ['name'=>'Spencer Macpherson','d'=>9,'js'=>0,'jm'=>9,'jp'=>'0%','sr'=>1,'sd'=>0,'sp'=>'0%','vi'=>0,'dr'=>0],
    ],
  ],
  [
    'name' => '⚠ Unassigned',
    'jsa_pct' => '3%', 'share_pct' => '0%', 'workers_count' => 13,
    'workers' => [
      ['name'=>'Brian Durand',      'd'=>5,'js'=>1,'jm'=>4,'jp'=>'20%','sr'=>1,'sd'=>0,'sp'=>'0%', 'vi'=>0,'dr'=>0],
      ['name'=>'Mike McCarthy',     'd'=>5,'js'=>1,'jm'=>4,'jp'=>'20%','sr'=>1,'sd'=>0,'sp'=>'0%', 'vi'=>0,'dr'=>0],
      ['name'=>'Bradley Stach',     'd'=>5,'js'=>0,'jm'=>5,'jp'=>'0%', 'sr'=>1,'sd'=>0,'sp'=>'0%', 'vi'=>0,'dr'=>0],
      ['name'=>'Dale Rogan',        'd'=>5,'js'=>0,'jm'=>5,'jp'=>'0%', 'sr'=>1,'sd'=>0,'sp'=>'0%', 'vi'=>0,'dr'=>0],
      ['name'=>'Jeffrey Cholowski', 'd'=>9,'js'=>0,'jm'=>9,'jp'=>'0%', 'sr'=>1,'sd'=>0,'sp'=>'0%', 'vi'=>0,'dr'=>0],
      ['name'=>'Jim Thompson',      'd'=>9,'js'=>0,'jm'=>9,'jp'=>'0%', 'sr'=>1,'sd'=>0,'sp'=>'0%', 'vi'=>0,'dr'=>0],
      ['name'=>'Kevin ter Kuile',   'd'=>2,'js'=>0,'jm'=>2,'jp'=>'0%', 'sr'=>0,'sd'=>0,'sp'=>'N/A','vi'=>0,'dr'=>0],
      ['name'=>'Lyle Roache',       'd'=>1,'js'=>0,'jm'=>1,'jp'=>'0%', 'sr'=>0,'sd'=>0,'sp'=>'N/A','vi'=>0,'dr'=>0],
      ['name'=>'Marco Garza',       'd'=>5,'js'=>0,'jm'=>5,'jp'=>'0%', 'sr'=>1,'sd'=>0,'sp'=>'0%', 'vi'=>0,'dr'=>0],
      ['name'=>'Rob Cooper',        'd'=>9,'js'=>0,'jm'=>9,'jp'=>'0%', 'sr'=>1,'sd'=>0,'sp'=>'0%', 'vi'=>0,'dr'=>0],
      ['name'=>'Rodolfo Pineda',    'd'=>1,'js'=>0,'jm'=>1,'jp'=>'0%', 'sr'=>0,'sd'=>0,'sp'=>'N/A','vi'=>0,'dr'=>0],
      ['name'=>'Sergio Garza',      'd'=>9,'js'=>0,'jm'=>9,'jp'=>'0%', 'sr'=>1,'sd'=>0,'sp'=>'0%', 'vi'=>0,'dr'=>0],
      ['name'=>'Wayne Jamieson',    'd'=>1,'js'=>0,'jm'=>1,'jp'=>'0%', 'sr'=>0,'sd'=>0,'sp'=>'N/A','vi'=>0,'dr'=>0],
    ],
  ],
];
?>

<h1 class="page-title">📊 JSA &amp; Share Card Compliance by Site</h1>
<p class="page-sub">Period: May 16 – May 26, 2026 &nbsp;|&nbsp; Share card week: May 18–23
  &nbsp;|&nbsp; <span style="color:#27ae60;">■</span> Green ≥ 80%
  &nbsp;<span style="color:#e8a020;">■</span> Amber 50–79%
  &nbsp;<span style="color:#c0392b;">■</span> Red &lt; 50%</p>

<div style="margin-bottom:1rem;display:flex;gap:.75rem;flex-wrap:wrap;">
  <a href="safety_dashboard.php" class="btn">← Dashboard</a>
  <a href="safety_action_required.php" class="btn" style="background:var(--fire);border-color:var(--fire);">⚠ Action Required</a>
</div>

<?php foreach ($sites as $site): ?>
<div class="card" style="margin-bottom:1.25rem;">
  <div class="card-title" style="display:flex;align-items:baseline;gap:.75rem;flex-wrap:wrap;">
    <?= htmlspecialchars($site['name']) ?>
    <span style="font-size:.8rem;font-weight:400;color:var(--muted);">
      JSA: <?= compliance_badge($site['jsa_pct']) ?>
      &nbsp;|&nbsp; SHARE: <?= compliance_badge($site['share_pct']) ?>
      &nbsp;|&nbsp; <?= $site['workers_count'] ?> worker<?= $site['workers_count'] !== 1 ? 's' : '' ?>
    </span>
  </div>
  <div style="overflow-x:auto;margin-top:.75rem;">
    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
      <thead>
        <tr style="font-family:'Cinzel',serif;font-size:.68rem;letter-spacing:.06em;color:var(--muted);border-bottom:1px solid var(--border);">
          <th style="text-align:left;padding:.4rem .5rem;">Worker</th>
          <th style="text-align:center;padding:.4rem .35rem;">Days<br>On Site</th>
          <th style="text-align:center;padding:.4rem .35rem;">JSA<br>Signed</th>
          <th style="text-align:center;padding:.4rem .35rem;">JSA<br>Missed</th>
          <th style="text-align:center;padding:.4rem .35rem;">JSA %</th>
          <th style="text-align:center;padding:.4rem .35rem;">SHARE<br>Req.</th>
          <th style="text-align:center;padding:.4rem .35rem;">SHARE<br>Done</th>
          <th style="text-align:center;padding:.4rem .35rem;">SHARE %</th>
          <th style="text-align:center;padding:.4rem .35rem;">Vehicle<br>Insp.</th>
          <th style="text-align:center;padding:.4rem .35rem;">Daily<br>Report</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($site['workers'] as $w): ?>
        <tr style="border-bottom:1px solid rgba(255,255,255,.04);">
          <td style="padding:.45rem .5rem;color:var(--text);"><?= htmlspecialchars($w['name']) ?></td>
          <td style="text-align:center;padding:.45rem .35rem;color:var(--muted);"><?= $w['d'] ?></td>
          <td style="text-align:center;padding:.45rem .35rem;color:var(--muted);"><?= $w['js'] ?></td>
          <td style="text-align:center;padding:.45rem .35rem;color:var(--muted);"><?= $w['jm'] ?></td>
          <td style="text-align:center;padding:.45rem .35rem;"><?= compliance_badge($w['jp']) ?></td>
          <td style="text-align:center;padding:.45rem .35rem;color:var(--muted);"><?= $w['sr'] ?></td>
          <td style="text-align:center;padding:.45rem .35rem;color:var(--muted);"><?= $w['sd'] ?></td>
          <td style="text-align:center;padding:.45rem .35rem;"><?= compliance_badge($w['sp']) ?></td>
          <td style="text-align:center;padding:.45rem .35rem;color:var(--muted);"><?= $w['vi'] ?></td>
          <td style="text-align:center;padding:.45rem .35rem;color:var(--muted);"><?= $w['dr'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>

<?php pageFooter(); ?>
