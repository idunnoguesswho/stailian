<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'db.php';
require 'layout.php';

pageHeader('Safety — Action Required', 'safety_action_required.php');
?>

<h1 class="page-title">⚠ Action Required</h1>
<p class="page-sub">
  JSA action (&lt;50%): <strong style="color:#c0392b;">19</strong>
  &nbsp;|&nbsp; JSA monitor (50–79%): <strong style="color:#e8a020;">13</strong>
  &nbsp;|&nbsp; Missing SHARE card: <strong style="color:#c0392b;">25</strong>
</p>

<div style="margin-bottom:1rem;display:flex;gap:.75rem;flex-wrap:wrap;">
  <a href="safety_dashboard.php" class="btn">← Dashboard</a>
  <a href="safety_site_detail.php" class="btn">📊 Site Detail</a>
</div>

<!-- ── JSA ACTION — BELOW 50% ── -->
<div class="card" style="margin-bottom:1.25rem;border-left:3px solid #c0392b;">
  <div class="card-title" style="color:#c0392b;">🚨 JSA Action — Below 50% <small style="font-weight:400;font-size:.8rem;color:var(--muted);">(19 workers)</small></div>
  <div style="overflow-x:auto;margin-top:.75rem;">
    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
      <thead>
        <tr style="font-family:'Cinzel',serif;font-size:.68rem;letter-spacing:.06em;color:var(--muted);border-bottom:1px solid var(--border);">
          <th style="text-align:left;padding:.45rem .5rem;">Worker</th>
          <th style="text-align:left;padding:.45rem .4rem;">Site</th>
          <th style="text-align:center;padding:.45rem .35rem;">Days<br>On Site</th>
          <th style="text-align:center;padding:.45rem .35rem;">JSA<br>Signed</th>
          <th style="text-align:center;padding:.45rem .35rem;">JSA<br>Missed</th>
          <th style="text-align:center;padding:.45rem .35rem;">JSA %</th>
          <th style="text-align:center;padding:.45rem .35rem;">SHARE<br>Req.</th>
          <th style="text-align:left;padding:.45rem .5rem;">Missed Dates</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $action = [
          ['name'=>'Todd Zelman',     'site'=>'Mount Bracey','d'=>10,'js'=>4,'jm'=>6,'jp'=>'40%','sr'=>'100%','dates'=>'May 16, May 17, May 19, May 21, May 24, May 25'],
          ['name'=>'Kirk Pilsworth',  'site'=>'Mount Bracey','d'=>5, 'js'=>2,'jm'=>3,'jp'=>'40%','sr'=>'0%',  'dates'=>'May 24, May 25, May 26'],
          ['name'=>'Benjamin Ball',   'site'=>'Holmes',      'd'=>8, 'js'=>2,'jm'=>6,'jp'=>'25%','sr'=>'0%',  'dates'=>'May 18, May 21, May 22, May 23, May 25, May 26'],
          ['name'=>'Greg Banack',     'site'=>'Mount Bracey','d'=>10,'js'=>2,'jm'=>8,'jp'=>'20%','sr'=>'0%',  'dates'=>'May 16, May 17, May 18, May 19, May 20, May 23, May 24, May 25'],
          ['name'=>'Brian Durand',    'site'=>'Unassigned',  'd'=>5, 'js'=>1,'jm'=>4,'jp'=>'20%','sr'=>'0%',  'dates'=>'May 22, May 24, May 25, May 26'],
          ['name'=>'Mike McCarthy',   'site'=>'Unassigned',  'd'=>5, 'js'=>1,'jm'=>4,'jp'=>'20%','sr'=>'0%',  'dates'=>'May 22, May 24, May 25, May 26'],
          ['name'=>'Jeffrey Cholowski','site'=>'Unassigned', 'd'=>9, 'js'=>0,'jm'=>9,'jp'=>'0%', 'sr'=>'0%',  'dates'=>'May 16, May 18, May 19, May 20, May 21, May 22, May 23, May 25, May 26'],
          ['name'=>'Jim Thompson',    'site'=>'Unassigned',  'd'=>9, 'js'=>0,'jm'=>9,'jp'=>'0%', 'sr'=>'0%',  'dates'=>'May 16, May 18, May 19, May 20, May 21, May 22, May 23, May 25, May 26'],
          ['name'=>'Rob Cooper',      'site'=>'Unassigned',  'd'=>9, 'js'=>0,'jm'=>9,'jp'=>'0%', 'sr'=>'0%',  'dates'=>'May 16, May 18, May 19, May 20, May 21, May 22, May 23, May 25, May 26'],
          ['name'=>'Sergio Garza',    'site'=>'Unassigned',  'd'=>9, 'js'=>0,'jm'=>9,'jp'=>'0%', 'sr'=>'0%',  'dates'=>'May 16, May 18, May 19, May 20, May 21, May 22, May 23, May 25, May 26'],
          ['name'=>'Spencer Macpherson','site'=>'Naubinway', 'd'=>9, 'js'=>0,'jm'=>9,'jp'=>'0%', 'sr'=>'0%',  'dates'=>'May 16, May 18, May 19, May 20, May 21, May 22, May 23, May 25, May 26'],
          ['name'=>'Kevin Knopp',     'site'=>'Deer River',  'd'=>8, 'js'=>0,'jm'=>8,'jp'=>'0%', 'sr'=>'0%',  'dates'=>'May 18, May 19, May 20, May 21, May 22, May 23, May 25, May 26'],
          ['name'=>'Bradley Stach',   'site'=>'Unassigned',  'd'=>5, 'js'=>0,'jm'=>5,'jp'=>'0%', 'sr'=>'0%',  'dates'=>'May 18, May 19, May 20, May 21, May 22'],
          ['name'=>'Dale Rogan',      'site'=>'Unassigned',  'd'=>5, 'js'=>0,'jm'=>5,'jp'=>'0%', 'sr'=>'0%',  'dates'=>'May 22, May 23, May 24, May 25, May 26'],
          ['name'=>'Marco Garza',     'site'=>'Unassigned',  'd'=>5, 'js'=>0,'jm'=>5,'jp'=>'0%', 'sr'=>'0%',  'dates'=>'May 18, May 19, May 20, May 21, May 22'],
          ['name'=>'Kevin ter Kuile', 'site'=>'Unassigned',  'd'=>2, 'js'=>0,'jm'=>2,'jp'=>'0%', 'sr'=>'N/A', 'dates'=>'May 25, May 26'],
          ['name'=>'Lyle Roache',     'site'=>'Unassigned',  'd'=>1, 'js'=>0,'jm'=>1,'jp'=>'0%', 'sr'=>'N/A', 'dates'=>'May 16'],
          ['name'=>'Rodolfo Pineda',  'site'=>'Unassigned',  'd'=>1, 'js'=>0,'jm'=>1,'jp'=>'0%', 'sr'=>'N/A', 'dates'=>'May 16'],
          ['name'=>'Wayne Jamieson',  'site'=>'Unassigned',  'd'=>1, 'js'=>0,'jm'=>1,'jp'=>'0%', 'sr'=>'N/A', 'dates'=>'May 26'],
        ];
        foreach ($action as $r):
            $pct = (int)$r['jp'];
            $pc  = $pct >= 80 ? '#27ae60' : ($pct >= 50 ? '#e8a020' : '#c0392b');
        ?>
        <tr style="border-bottom:1px solid rgba(255,255,255,.04);">
          <td style="padding:.45rem .5rem;color:var(--text);font-weight:500;"><?= htmlspecialchars($r['name']) ?></td>
          <td style="padding:.45rem .4rem;color:var(--muted);"><?= htmlspecialchars($r['site']) ?></td>
          <td style="text-align:center;padding:.45rem .35rem;color:var(--muted);"><?= $r['d'] ?></td>
          <td style="text-align:center;padding:.45rem .35rem;color:var(--muted);"><?= $r['js'] ?></td>
          <td style="text-align:center;padding:.45rem .35rem;color:#c0392b;font-weight:600;"><?= $r['jm'] ?></td>
          <td style="text-align:center;padding:.45rem .35rem;font-weight:700;color:<?= $pc ?>;"><?= $r['jp'] ?></td>
          <td style="text-align:center;padding:.45rem .35rem;color:var(--muted);"><?= $r['sr'] ?></td>
          <td style="padding:.45rem .5rem;color:var(--muted);font-size:.8rem;"><?= htmlspecialchars($r['dates']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── JSA MONITOR — 50–79% ── -->
<div class="card" style="margin-bottom:1.25rem;border-left:3px solid #e8a020;">
  <div class="card-title" style="color:#e8a020;">👁 JSA Monitor — 50–79% <small style="font-weight:400;font-size:.8rem;color:var(--muted);">(13 workers)</small></div>
  <div style="overflow-x:auto;margin-top:.75rem;">
    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
      <thead>
        <tr style="font-family:'Cinzel',serif;font-size:.68rem;letter-spacing:.06em;color:var(--muted);border-bottom:1px solid var(--border);">
          <th style="text-align:left;padding:.45rem .5rem;">Worker</th>
          <th style="text-align:left;padding:.45rem .4rem;">Site</th>
          <th style="text-align:center;padding:.45rem .35rem;">Days<br>On Site</th>
          <th style="text-align:center;padding:.45rem .35rem;">JSA<br>Signed</th>
          <th style="text-align:center;padding:.45rem .35rem;">JSA<br>Missed</th>
          <th style="text-align:center;padding:.45rem .35rem;">JSA %</th>
          <th style="text-align:center;padding:.45rem .35rem;">SHARE<br>Req.</th>
          <th style="text-align:left;padding:.45rem .5rem;">Missed Dates</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $monitor = [
          ['name'=>'Shane Weibelzahl', 'site'=>'Lebanon',      'd'=>9, 'js'=>7,'jm'=>2,'jp'=>'78%','sr'=>'0%',  'dates'=>'May 25, May 26'],
          ['name'=>'Codey Carter',     'site'=>'Woolfolk',     'd'=>7, 'js'=>5,'jm'=>2,'jp'=>'71%','sr'=>'0%',  'dates'=>'May 22, May 26'],
          ['name'=>'Aarron Collins',   'site'=>'Lebanon',      'd'=>9, 'js'=>6,'jm'=>3,'jp'=>'67%','sr'=>'0%',  'dates'=>'May 22, May 25, May 26'],
          ['name'=>'Jared Markham',    'site'=>'Lebanon',      'd'=>9, 'js'=>6,'jm'=>3,'jp'=>'67%','sr'=>'100%','dates'=>'May 16, May 25, May 26'],
          ['name'=>'Jose Quintana',    'site'=>'Woolfolk',     'd'=>9, 'js'=>6,'jm'=>3,'jp'=>'67%','sr'=>'0%',  'dates'=>'May 23, May 25, May 26'],
          ['name'=>'Shaun Shipalesky', 'site'=>'Lebanon',      'd'=>9, 'js'=>6,'jm'=>3,'jp'=>'67%','sr'=>'100%','dates'=>'May 16, May 25, May 26'],
          ['name'=>'Roy Zarraga',      'site'=>'Woolfolk',     'd'=>8, 'js'=>5,'jm'=>3,'jp'=>'62%','sr'=>'0%',  'dates'=>'May 23, May 25, May 26'],
          ['name'=>'Julia Campbell',   'site'=>'Mount Bracey', 'd'=>7, 'js'=>4,'jm'=>3,'jp'=>'57%','sr'=>'0%',  'dates'=>'May 19, May 24, May 25'],
          ['name'=>'Conor Donaghy',    'site'=>'Crystal Falls','d'=>9, 'js'=>5,'jm'=>4,'jp'=>'56%','sr'=>'0%',  'dates'=>'May 19, May 22, May 23, May 25'],
          ['name'=>'Gerard Scranton',  'site'=>'Woolfolk',     'd'=>9, 'js'=>5,'jm'=>4,'jp'=>'56%','sr'=>'0%',  'dates'=>'May 16, May 20, May 25, May 26'],
          ['name'=>'Sean Brower',      'site'=>'Mount Bracey', 'd'=>10,'js'=>5,'jm'=>5,'jp'=>'50%','sr'=>'0%',  'dates'=>'May 19, May 20, May 22, May 24, May 25'],
          ['name'=>'Ramiro Rangel',    'site'=>'Lebanon',      'd'=>6, 'js'=>3,'jm'=>3,'jp'=>'50%','sr'=>'0%',  'dates'=>'May 20, May 25, May 26'],
          ['name'=>'Tyler Keel',       'site'=>'Lebanon',      'd'=>6, 'js'=>3,'jm'=>3,'jp'=>'50%','sr'=>'100%','dates'=>'May 20, May 25, May 26'],
        ];
        foreach ($monitor as $r): ?>
        <tr style="border-bottom:1px solid rgba(255,255,255,.04);">
          <td style="padding:.45rem .5rem;color:var(--text);font-weight:500;"><?= htmlspecialchars($r['name']) ?></td>
          <td style="padding:.45rem .4rem;color:var(--muted);"><?= htmlspecialchars($r['site']) ?></td>
          <td style="text-align:center;padding:.45rem .35rem;color:var(--muted);"><?= $r['d'] ?></td>
          <td style="text-align:center;padding:.45rem .35rem;color:var(--muted);"><?= $r['js'] ?></td>
          <td style="text-align:center;padding:.45rem .35rem;color:#e8a020;font-weight:600;"><?= $r['jm'] ?></td>
          <td style="text-align:center;padding:.45rem .35rem;font-weight:700;color:#e8a020;"><?= $r['jp'] ?></td>
          <td style="text-align:center;padding:.45rem .35rem;color:var(--muted);"><?= $r['sr'] ?></td>
          <td style="padding:.45rem .5rem;color:var(--muted);font-size:.8rem;"><?= htmlspecialchars($r['dates']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── MISSING SHARE CARD ── -->
<div class="card" style="border-left:3px solid var(--fire);">
  <div class="card-title" style="color:var(--fire);">📤 Missing Share Card — Week of May 18–23 <small style="font-weight:400;font-size:.8rem;color:var(--muted);">(25 workers)</small></div>
  <div style="overflow-x:auto;margin-top:.75rem;">
    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
      <thead>
        <tr style="font-family:'Cinzel',serif;font-size:.68rem;letter-spacing:.06em;color:var(--muted);border-bottom:1px solid var(--border);">
          <th style="text-align:left;padding:.45rem .5rem;">Worker</th>
          <th style="text-align:left;padding:.45rem .4rem;">Site</th>
          <th style="text-align:center;padding:.45rem .35rem;">Days<br>On Site</th>
          <th style="text-align:center;padding:.45rem .35rem;">SHARE<br>Required</th>
          <th style="text-align:center;padding:.45rem .35rem;">SHARE<br>Done</th>
          <th style="text-align:center;padding:.45rem .35rem;">SHARE %</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $missing_share = [
          ['name'=>'Justin Coppens',   'site'=>'Lebanon',      'd'=>4, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Shane Weibelzahl', 'site'=>'Lebanon',      'd'=>9, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Codey Carter',     'site'=>'Woolfolk',     'd'=>7, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Aarron Collins',   'site'=>'Lebanon',      'd'=>9, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Jose Quintana',    'site'=>'Woolfolk',     'd'=>9, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Roy Zarraga',      'site'=>'Woolfolk',     'd'=>8, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Julia Campbell',   'site'=>'Mount Bracey', 'd'=>7, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Conor Donaghy',    'site'=>'Crystal Falls','d'=>9, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Gerard Scranton',  'site'=>'Woolfolk',     'd'=>9, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Sean Brower',      'site'=>'Mount Bracey', 'd'=>10,'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Ramiro Rangel',    'site'=>'Lebanon',      'd'=>6, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Kirk Pilsworth',   'site'=>'Mount Bracey', 'd'=>5, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Benjamin Ball',    'site'=>'Holmes',       'd'=>8, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Greg Banack',      'site'=>'Mount Bracey', 'd'=>10,'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Brian Durand',     'site'=>'Unassigned',   'd'=>5, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Mike McCarthy',    'site'=>'Unassigned',   'd'=>5, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Jeffrey Cholowski','site'=>'Unassigned',   'd'=>9, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Jim Thompson',     'site'=>'Unassigned',   'd'=>9, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Rob Cooper',       'site'=>'Unassigned',   'd'=>9, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Sergio Garza',     'site'=>'Unassigned',   'd'=>9, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Spencer Macpherson','site'=>'Naubinway',   'd'=>9, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Kevin Knopp',      'site'=>'Deer River',   'd'=>8, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Bradley Stach',    'site'=>'Unassigned',   'd'=>5, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Dale Rogan',       'site'=>'Unassigned',   'd'=>5, 'req'=>1,'done'=>0,'pct'=>'0%'],
          ['name'=>'Marco Garza',      'site'=>'Unassigned',   'd'=>5, 'req'=>1,'done'=>0,'pct'=>'0%'],
        ];
        foreach ($missing_share as $r): ?>
        <tr style="border-bottom:1px solid rgba(255,255,255,.04);">
          <td style="padding:.45rem .5rem;color:var(--text);font-weight:500;"><?= htmlspecialchars($r['name']) ?></td>
          <td style="padding:.45rem .4rem;color:var(--muted);"><?= htmlspecialchars($r['site']) ?></td>
          <td style="text-align:center;padding:.45rem .35rem;color:var(--muted);"><?= $r['d'] ?></td>
          <td style="text-align:center;padding:.45rem .35rem;color:var(--muted);"><?= $r['req'] ?></td>
          <td style="text-align:center;padding:.45rem .35rem;color:var(--muted);"><?= $r['done'] ?></td>
          <td style="text-align:center;padding:.45rem .35rem;font-weight:700;color:#c0392b;"><?= $r['pct'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php pageFooter(); ?>
