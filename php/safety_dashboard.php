<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'db.php';
require 'layout.php';

pageHeader('Safety Dashboard', 'safety_dashboard.php');
?>

<h1 class="page-title">🦺 Safety Forms — Manager Meeting Summary</h1>
<p class="page-sub">Period: May 16 – May 26, 2026 &nbsp;|&nbsp; Active Sites: 9 &nbsp;|&nbsp; Workers Tracked: 35</p>

<!-- ── JSA / TOOLBOX TALK COMPLIANCE ── -->
<div class="card" style="margin-bottom:1.25rem;">
  <div class="card-title">📋 JSA / Toolbox Talk Compliance <small style="font-weight:400;font-size:.8rem;color:var(--muted);">(signatures ÷ days on site)</small></div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:.75rem;margin-top:.75rem;">

    <?php
    $stats = [
      ['label'=>'Overall Compliance','value'=>'40%',  'color'=>'var(--fire)'],
      ['label'=>'Days On Site',       'value'=>'247',  'color'=>'var(--gold)'],
      ['label'=>'JSAs Signed',        'value'=>'98',   'color'=>'#27ae60'],
      ['label'=>'JSAs Missed',        'value'=>'149',  'color'=>'var(--fire)'],
      ['label'=>'Workers ≥ 80%',      'value'=>'3',    'color'=>'#27ae60'],
      ['label'=>'Workers 50–79%',     'value'=>'13',   'color'=>'#e8a020'],
      ['label'=>'Workers < 50%',      'value'=>'19',   'color'=>'var(--fire)'],
    ];
    foreach ($stats as $s): ?>
    <div style="background:var(--surface);border-radius:var(--radius);padding:.9rem .75rem;text-align:center;">
      <div style="font-size:1.6rem;font-family:'Cinzel',serif;font-weight:900;color:<?= $s['color'] ?>;"><?= $s['value'] ?></div>
      <div style="font-size:.72rem;color:var(--muted);margin-top:.25rem;line-height:1.3;"><?= $s['label'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── SHARE CARD COMPLIANCE ── -->
<div class="card" style="margin-bottom:1.25rem;">
  <div class="card-title">📤 Share Card Compliance <small style="font-weight:400;font-size:.8rem;color:var(--muted);">(1 required per person per week — May 18–23)</small></div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.75rem;margin-top:.75rem;">
    <?php
    $share = [
      ['label'=>'Overall Compliance','value'=>'19%','color'=>'var(--fire)'],
      ['label'=>'Required (workers on site)','value'=>'31','color'=>'var(--gold)'],
      ['label'=>'Submitted','value'=>'6','color'=>'#27ae60'],
      ['label'=>'Missing','value'=>'25','color'=>'var(--fire)'],
    ];
    foreach ($share as $s): ?>
    <div style="background:var(--surface);border-radius:var(--radius);padding:.9rem .75rem;text-align:center;">
      <div style="font-size:1.6rem;font-family:'Cinzel',serif;font-weight:900;color:<?= $s['color'] ?>;"><?= $s['value'] ?></div>
      <div style="font-size:.72rem;color:var(--muted);margin-top:.25rem;line-height:1.3;"><?= $s['label'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── SITE COMPLIANCE RANKING ── -->
<div class="card" style="margin-bottom:1.25rem;">
  <div class="card-title">🏆 Site Compliance Ranking</div>
  <div style="overflow-x:auto;margin-top:.75rem;">
    <table style="width:100%;border-collapse:collapse;font-size:.88rem;">
      <thead>
        <tr style="font-family:'Cinzel',serif;font-size:.7rem;letter-spacing:.07em;color:var(--muted);border-bottom:1px solid var(--border);">
          <th style="text-align:left;padding:.5rem .6rem;">Site</th>
          <th style="text-align:center;padding:.5rem .4rem;">Workers</th>
          <th style="text-align:center;padding:.5rem .4rem;">Days<br>On Site</th>
          <th style="text-align:center;padding:.5rem .4rem;">JSA<br>Signed</th>
          <th style="text-align:center;padding:.5rem .4rem;">JSA<br>Missed</th>
          <th style="text-align:center;padding:.5rem .4rem;">JSA %</th>
          <th style="text-align:center;padding:.5rem .4rem;">SHARE<br>Req.</th>
          <th style="text-align:center;padding:.5rem .4rem;">SHARE<br>Done</th>
          <th style="text-align:center;padding:.5rem .4rem;">SHARE %</th>
          <th style="text-align:center;padding:.5rem .4rem;">Vehicle<br>Insp.</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $sites = [
          ['medal'=>'🥇','name'=>'Lebanon',       'w'=>7,  'd'=>52, 'js'=>35,'jm'=>17,'jp'=>'67%','sr'=>7, 'sd'=>3,'sp'=>'43%','vi'=>0],
          ['medal'=>'🥈','name'=>'Woolfolk',       'w'=>4,  'd'=>33, 'js'=>21,'jm'=>12,'jp'=>'64%','sr'=>4, 'sd'=>0,'sp'=>'0%', 'vi'=>1],
          ['medal'=>'🥉','name'=>'Crystal Falls',  'w'=>1,  'd'=>9,  'js'=>5, 'jm'=>4, 'jp'=>'56%','sr'=>1, 'sd'=>0,'sp'=>'0%', 'vi'=>2],
          ['medal'=>'',  'name'=>'Mount Bracey',   'w'=>7,  'd'=>62, 'js'=>33,'jm'=>29,'jp'=>'53%','sr'=>7, 'sd'=>3,'sp'=>'43%','vi'=>1],
          ['medal'=>'',  'name'=>'Holmes',          'w'=>1,  'd'=>8,  'js'=>2, 'jm'=>6, 'jp'=>'25%','sr'=>1, 'sd'=>0,'sp'=>'0%', 'vi'=>0],
          ['medal'=>'',  'name'=>'Deer River',      'w'=>1,  'd'=>8,  'js'=>0, 'jm'=>8, 'jp'=>'0%', 'sr'=>1, 'sd'=>0,'sp'=>'0%', 'vi'=>0],
          ['medal'=>'',  'name'=>'Naubinway',       'w'=>1,  'd'=>9,  'js'=>0, 'jm'=>9, 'jp'=>'0%', 'sr'=>1, 'sd'=>0,'sp'=>'0%', 'vi'=>0],
          ['medal'=>'⚠','name'=>'Unassigned',      'w'=>13, 'd'=>66, 'js'=>2, 'jm'=>64,'jp'=>'3%', 'sr'=>9, 'sd'=>0,'sp'=>'0%', 'vi'=>0],
          ['medal'=>'',  'name'=>'TOTALS',          'w'=>35, 'd'=>247,'js'=>98,'jm'=>149,'jp'=>'40%','sr'=>31,'sd'=>6,'sp'=>'19%','vi'=>4, 'bold'=>true],
        ];

        function jsa_color(string $pct): string {
            $n = (int)$pct;
            if ($n >= 80) return '#27ae60';
            if ($n >= 50) return '#e8a020';
            return '#c0392b';
        }

        foreach ($sites as $s):
            $bold = !empty($s['bold']) ? 'font-weight:700;border-top:1px solid var(--border);' : '';
            $jc   = jsa_color($s['jp']);
            $sc   = jsa_color($s['sp']);
        ?>
        <tr style="border-bottom:1px solid rgba(255,255,255,.04);<?= $bold ?>">
          <td style="padding:.5rem .6rem;color:var(--text);"><?= $s['medal'] ? $s['medal'].' ' : '&nbsp;&nbsp;&nbsp;' ?><?= htmlspecialchars($s['name']) ?></td>
          <td style="text-align:center;padding:.5rem .4rem;color:var(--muted);"><?= $s['w'] ?></td>
          <td style="text-align:center;padding:.5rem .4rem;color:var(--muted);"><?= $s['d'] ?></td>
          <td style="text-align:center;padding:.5rem .4rem;color:var(--muted);"><?= $s['js'] ?></td>
          <td style="text-align:center;padding:.5rem .4rem;color:var(--muted);"><?= $s['jm'] ?></td>
          <td style="text-align:center;padding:.5rem .4rem;font-weight:700;color:<?= $jc ?>;"><?= $s['jp'] ?></td>
          <td style="text-align:center;padding:.5rem .4rem;color:var(--muted);"><?= $s['sr'] ?></td>
          <td style="text-align:center;padding:.5rem .4rem;color:var(--muted);"><?= $s['sd'] ?></td>
          <td style="text-align:center;padding:.5rem .4rem;font-weight:700;color:<?= $sc ?>;"><?= $s['sp'] ?></td>
          <td style="text-align:center;padding:.5rem .4rem;color:var(--muted);"><?= $s['vi'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── ACTIVE SITE SCHEDULE ── -->
<div class="card" style="margin-bottom:1.25rem;">
  <div class="card-title">🏗 Active Site Schedule <small style="font-weight:400;font-size:.8rem;color:var(--muted);">(9 sites)</small></div>
  <div style="overflow-x:auto;margin-top:.75rem;">
    <table style="width:100%;border-collapse:collapse;font-size:.88rem;">
      <thead>
        <tr style="font-family:'Cinzel',serif;font-size:.7rem;letter-spacing:.07em;color:var(--muted);border-bottom:1px solid var(--border);">
          <th style="text-align:left;padding:.5rem .6rem;">Project / Site Name</th>
          <th style="text-align:center;padding:.5rem .6rem;">PO #</th>
          <th style="text-align:center;padding:.5rem .6rem;">Start</th>
          <th style="text-align:center;padding:.5rem .6rem;">Ends</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $active = [
          ['name'=>'CLGT Deer River CS HP Upgrade',                       'po'=>'25633','start'=>'May 18, 2026','end'=>'Aug 16, 2026'],
          ['name'=>'TC Energy Crystal Falls HP Upgrade CSU',               'po'=>'25634','start'=>'May 13, 2026','end'=>'Sep 01, 2026'],
          ['name'=>'TC Energy Hartsville Cooler / MCC Replacement (Hartsville, TN)','po'=>'25641','start'=>'Sep 08, 2025','end'=>'Jul 18, 2026'],
          ['name'=>'TC Energy Holmes CS Automation Upgrade',               'po'=>'26349','start'=>'May 18, 2026','end'=>'Sep 01, 2026'],
          ['name'=>'TC-CLGT-Naubinway',                                    'po'=>'26302','start'=>'May 04, 2026','end'=>'Dec 31, 2026'],
          ['name'=>'TC-CLGT MOD--Farwell',                                 'po'=>'26291','start'=>'May 04, 2026','end'=>'Dec 31, 2026'],
          ['name'=>'TC Energy 2026 Kewaskum',                              'po'=>'26399','start'=>'Apr 06, 2026','end'=>'Aug 22, 2026'],
          ['name'=>'TC Woolfolk Automation Upgrade',                        'po'=>'26404','start'=>'May 13, 2026','end'=>'Jul 25, 2026'],
          ['name'=>'Mibeloon Meter Station',                                'po'=>'26466','start'=>'May 27, 2026','end'=>'Jun 04, 2026'],
        ];
        foreach ($active as $r): ?>
        <tr style="border-bottom:1px solid rgba(255,255,255,.04);">
          <td style="padding:.5rem .6rem;color:var(--text);"><?= htmlspecialchars($r['name']) ?></td>
          <td style="text-align:center;padding:.5rem .6rem;color:var(--muted);font-family:'Cinzel',serif;font-size:.8rem;"><?= $r['po'] ?></td>
          <td style="text-align:center;padding:.5rem .6rem;color:var(--muted);"><?= $r['start'] ?></td>
          <td style="text-align:center;padding:.5rem .6rem;color:var(--muted);"><?= $r['end'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── STARTING WITHIN 30 DAYS ── -->
<div class="card">
  <div class="card-title">📅 Starting Within 30 Days <small style="font-weight:400;font-size:.8rem;color:var(--muted);">(5 sites)</small></div>
  <div style="overflow-x:auto;margin-top:.75rem;">
    <table style="width:100%;border-collapse:collapse;font-size:.88rem;">
      <thead>
        <tr style="font-family:'Cinzel',serif;font-size:.7rem;letter-spacing:.07em;color:var(--muted);border-bottom:1px solid var(--border);">
          <th style="text-align:left;padding:.5rem .6rem;">Project / Site Name</th>
          <th style="text-align:center;padding:.5rem .6rem;">PO #</th>
          <th style="text-align:center;padding:.5rem .6rem;">Start</th>
          <th style="text-align:center;padding:.5rem .6rem;">Ends</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $upcoming = [
          ['name'=>'TC Energy Janesville 2026',                        'po'=>'26397','start'=>'Jun 01, 2026','end'=>'Aug 28, 2026'],
          ['name'=>'Huff Creek Dwg Review',                            'po'=>'26344','start'=>'Jun 01, 2026','end'=>'Aug 31, 2026'],
          ['name'=>'TC Energy ANR Loreed Replace Air Compressor',      'po'=>'26400','start'=>'Jun 11, 2026','end'=>'Jun 15, 2026'],
          ['name'=>'TC Energy 2025 Weyaywega',                         'po'=>'26398','start'=>'Jun 15, 2026','end'=>'Jun 26, 2026'],
          ['name'=>'TC Energy Woolfolk Station 2 Boiler Replacement',  'po'=>'26380','start'=>'Jun 22, 2026','end'=>'Jun 27, 2026'],
        ];
        foreach ($upcoming as $r): ?>
        <tr style="border-bottom:1px solid rgba(255,255,255,.04);">
          <td style="padding:.5rem .6rem;color:var(--text);"><?= htmlspecialchars($r['name']) ?></td>
          <td style="text-align:center;padding:.5rem .6rem;color:var(--muted);font-family:'Cinzel',serif;font-size:.8rem;"><?= $r['po'] ?></td>
          <td style="text-align:center;padding:.5rem .6rem;color:#27ae60;"><?= $r['start'] ?></td>
          <td style="text-align:center;padding:.5rem .6rem;color:var(--muted);"><?= $r['end'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div style="margin-top:1.25rem;display:flex;gap:.75rem;flex-wrap:wrap;">
  <a href="safety_site_detail.php"    class="btn">📊 Site Detail</a>
  <a href="safety_daily_detail.php"   class="btn">📅 Daily Detail</a>
  <a href="safety_action_required.php" class="btn" style="background:var(--fire);border-color:var(--fire);">⚠ Action Required</a>
</div>

<?php pageFooter(); ?>
