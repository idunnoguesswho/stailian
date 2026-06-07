<?php
session_start();
ob_start();
require_once 'guess_db.php';
$db = getDB();

$msg = ''; $msgType = '';

// ── ACTIVE SET (stored in session) ───────────────────────
if (isset($_POST['switch_set'])) {
    $_SESSION['admin_set_id'] = (int)$_POST['switch_set'];
    header('Location: guess_admin.php'); exit;
}
$activeSetId = (int)($_SESSION['admin_set_id'] ?? 0);

// ── CREATE SET ───────────────────────────────────────────
if (isset($_POST['create_set'])) {
    $setName = trim($_POST['set_name'] ?? '');
    $setDesc = trim($_POST['set_desc'] ?? '');
    if ($setName === '') { $msg = 'Set name is required.'; $msgType = 'error'; }
    else {
        $db->prepare("INSERT INTO character_sets (name, description) VALUES (?,?)")->execute([$setName, $setDesc]);
        $newId = (int)$db->lastInsertId();
        $_SESSION['admin_set_id'] = $newId;
        $activeSetId = $newId;
        $msg = "Set \"$setName\" created!"; $msgType = 'success';
    }
}

// ── DELETE SET ───────────────────────────────────────────
if (isset($_POST['delete_set'])) {
    $delSetId = (int)$_POST['delete_set'];
    // Delete character images for this set
    $imgRows = $db->prepare("SELECT image_path FROM characters WHERE set_id = ?")->execute([$delSetId]) ?
               $db->query("SELECT image_path FROM characters WHERE set_id = $delSetId")->fetchAll() : [];
    foreach ($imgRows as $row) {
        if ($row['image_path'] && file_exists(__DIR__ . '/' . $row['image_path'])) {
            unlink(__DIR__ . '/' . $row['image_path']);
        }
    }
    $db->prepare("DELETE FROM character_sets WHERE id = ?")->execute([$delSetId]);
    if ($activeSetId === $delSetId) { $activeSetId = 0; unset($_SESSION['admin_set_id']); }
    $msg = 'Set deleted.'; $msgType = 'warn';
}

// ── RENAME SET ───────────────────────────────────────────
if (isset($_POST['rename_set'])) {
    $renId   = (int)$_POST['rename_set_id'];
    $renName = trim($_POST['rename_set_name'] ?? '');
    $renDesc = trim($_POST['rename_set_desc'] ?? '');
    if ($renName) {
        $db->prepare("UPDATE character_sets SET name=?, description=? WHERE id=?")->execute([$renName, $renDesc, $renId]);
        $msg = 'Set updated.'; $msgType = 'success';
    }
}

// ── DELETE CHARACTER ─────────────────────────────────────
if (isset($_POST['delete_id'])) {
    $delId = (int)$_POST['delete_id'];
    $row = $db->prepare("SELECT image_path FROM characters WHERE id = ?");
    $row->execute([$delId]); $row = $row->fetch();
    if ($row && $row['image_path'] && file_exists(__DIR__ . '/' . $row['image_path'])) {
        unlink(__DIR__ . '/' . $row['image_path']);
    }
    $db->prepare("DELETE FROM characters WHERE id = ?")->execute([$delId]);
    $msg = 'Character deleted.'; $msgType = 'warn';
}

// ── SAVE CHARACTER ───────────────────────────────────────
if (isset($_POST['save_character']) && $activeSetId) {
    $charId = (int)($_POST['char_id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');
    if ($name === '') { $msg = 'Name is required.'; $msgType = 'error'; }
    else {
        $imagePath = $_POST['existing_image'] ?? null;
        if (!empty($_FILES['image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) {
                $msg = 'Invalid image type.'; $msgType = 'error';
            } else {
                $filename = 'char_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $filename)) {
                    if ($imagePath && file_exists(__DIR__ . '/' . $imagePath)) unlink(__DIR__ . '/' . $imagePath);
                    $imagePath = UPLOAD_URL . $filename;
                } else { $msg = 'Image upload failed.'; $msgType = 'error'; }
            }
        }
        if ($msgType !== 'error') {
            if ($charId > 0) {
                $db->prepare("UPDATE characters SET name=?, image_path=? WHERE id=?")->execute([$name, $imagePath, $charId]);
            } else {
                $db->prepare("INSERT INTO characters (set_id, name, image_path) VALUES (?,?,?)")->execute([$activeSetId, $name, $imagePath]);
                $charId = (int)$db->lastInsertId();
            }
            // Save up to 6 traits — scoped to this set
            $traitLabels = $_POST['trait_label'] ?? [];
            $traitValues = $_POST['trait_value'] ?? [];
            $db->prepare("DELETE FROM character_traits WHERE character_id = ?")->execute([$charId]);

            for ($i = 0; $i < 6; $i++) {
                $label = trim($traitLabels[$i] ?? '');
                $value = trim($traitValues[$i] ?? '');
                if ($label === '' || $value === '') continue;

                // Upsert trait def scoped to this set
                $stmt = $db->prepare("SELECT id, possible_values FROM trait_definitions WHERE set_id = ? AND label = ?");
                $stmt->execute([$activeSetId, $label]);
                $def = $stmt->fetch();
                if ($def) {
                    $defId = $def['id'];
                    $vals = json_decode($def['possible_values'], true) ?? [];
                    if (!in_array($value, $vals)) {
                        $vals[] = $value;
                        $db->prepare("UPDATE trait_definitions SET possible_values=? WHERE id=?")->execute([json_encode($vals), $defId]);
                    }
                } else {
                    $db->prepare("INSERT INTO trait_definitions (set_id, label, possible_values, sort_order) VALUES (?,?,?,?)")
                       ->execute([$activeSetId, $label, json_encode([$value]), $i + 1]);
                    $defId = (int)$db->lastInsertId();
                }
                $db->prepare("INSERT INTO character_traits (character_id, trait_def_id, value) VALUES (?,?,?)")->execute([$charId, $defId, $value]);
            }
            $msg = 'Character saved!'; $msgType = 'success';
        }
    }
}

// ── LOAD DATA ────────────────────────────────────────────
$allSets    = loadSets($db);
$activeSet  = null;
$characters = [];
$traitDefs  = [];
if ($activeSetId) {
    $stmt = $db->prepare("SELECT * FROM character_sets WHERE id = ?");
    $stmt->execute([$activeSetId]);
    $activeSet = $stmt->fetch();
    if ($activeSet) {
        $characters = loadCharactersBySet($db, $activeSetId);
        $traitDefs  = loadTraitDefs($db, $activeSetId);
    } else {
        $activeSetId = 0;
    }
}

$editChar = null; $editTraits = [];
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM characters WHERE id = ? AND set_id = ?");
    $stmt->execute([(int)$_GET['edit'], $activeSetId]);
    $editChar = $stmt->fetch();
    if ($editChar) {
        $stmt2 = $db->prepare("SELECT td.label, ct.value FROM character_traits ct JOIN trait_definitions td ON ct.trait_def_id = td.id WHERE ct.character_id = ? ORDER BY td.sort_order");
        $stmt2->execute([$editChar['id']]);
        $editTraits = $stmt2->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>⚙️ Guess Who — Admin</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --bg:#0d0b12;--card:#16121e;--panel:#1d1828;--border:#3a2f50;
    --gold:#c9a84c;--gold-l:#f0c96e;--gold-d:#7a6230;
    --red:#c94040;--teal:#3fa09a;--green:#4a9a5a;
    --text:#e8dfc8;--dim:#8a7f6e;--font:Georgia,serif;
}
body{background:var(--bg);color:var(--text);font-family:var(--font);min-height:100vh}

header{text-align:center;padding:1.2rem 1rem;border-bottom:1px solid var(--border)}
header h1{color:var(--gold-l);font-size:1.6rem;letter-spacing:.08em}
header nav a{color:var(--teal);text-decoration:none;margin:0 .7rem;font-size:.85rem}
header nav a:hover{color:var(--gold)}

.container{max-width:1200px;margin:0 auto;padding:1.2rem 1rem}

.msg{padding:.7rem 1rem;border-radius:5px;margin-bottom:1rem;font-size:.88rem}
.msg.success{background:rgba(74,154,90,.12);border:1px solid var(--green);color:var(--green)}
.msg.error  {background:rgba(201,64,64,.12); border:1px solid var(--red);  color:var(--red)}
.msg.warn   {background:rgba(201,168,76,.1); border:1px solid var(--gold-d);color:var(--gold)}

/* ── SET SWITCHER ── */
.set-bar{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-bottom:1.2rem;padding-bottom:1rem;border-bottom:1px solid var(--border)}
.set-pill{display:inline-block;padding:.35rem .8rem;border-radius:20px;font-size:.8rem;border:1px solid var(--border);cursor:pointer;background:var(--card);color:var(--dim);text-decoration:none}
.set-pill.active{border-color:var(--gold);color:var(--gold-l);background:rgba(201,168,76,.08)}
.set-pill:hover:not(.active){border-color:var(--teal);color:var(--teal)}
.set-pill.new-set{border-color:var(--teal);color:var(--teal)}
.set-count{font-size:.7rem;opacity:.6;margin-left:.3rem}

/* ── GRID ── */
.two-col{display:grid;grid-template-columns:1fr 1.5fr;gap:1.2rem;align-items:start}
@media(max-width:720px){.two-col{grid-template-columns:1fr}}

/* ── CARDS ── */
.card{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:1rem}
.card.editing{border-color:var(--gold-d);box-shadow:0 0 16px rgba(201,168,76,.1)}
.card h2{color:var(--gold);font-size:.82rem;text-transform:uppercase;letter-spacing:.13em;margin-bottom:.8rem;padding-bottom:.4rem;border-bottom:1px solid var(--border)}

/* ── FORM ── */
.fg{margin-bottom:.65rem}
.fg label{display:block;font-size:.72rem;color:var(--dim);text-transform:uppercase;letter-spacing:.09em;margin-bottom:.25rem}
.fg input[type=text],.fg input[type=file],.fg textarea,.fg select{
    width:100%;background:var(--panel);border:1px solid var(--border);color:var(--text);
    padding:.45rem .6rem;border-radius:4px;font-family:var(--font);font-size:.84rem}
.fg input:focus,.fg textarea:focus,.fg select:focus{outline:none;border-color:var(--gold-d)}
.fg textarea{resize:vertical;height:60px}

.trait-row{display:grid;grid-template-columns:1fr 1fr .8rem;gap:.35rem;margin-bottom:.35rem;align-items:center}
.trait-num{color:var(--gold-d);font-size:.7rem;text-align:right}
.traits-hint{font-size:.72rem;color:var(--dim);font-style:italic;margin-bottom:.4rem}

.img-cur{max-width:80px;max-height:80px;border:1px solid var(--border);border-radius:4px;object-fit:cover;margin-top:.4rem;display:block}

/* ── BUTTONS ── */
.btn{display:inline-block;padding:.55rem .95rem;border:1px solid;border-radius:4px;font-family:var(--font);font-size:.84rem;cursor:pointer;transition:all .18s;letter-spacing:.04em;text-align:center;text-decoration:none}
.btn-block{display:block;width:100%;margin-top:.6rem}
.btn-gold   {background:linear-gradient(135deg,#3a2f10,#1e1a0a);border-color:var(--gold);color:var(--gold-l)}
.btn-gold:hover{background:linear-gradient(135deg,#4a3f18,#2e2a12);box-shadow:0 0 10px rgba(201,168,76,.25)}
.btn-teal   {background:linear-gradient(135deg,#0f2a2a,#081818);border-color:var(--teal);color:var(--teal)}
.btn-teal:hover{background:linear-gradient(135deg,#143838,#0c2020)}
.btn-red    {background:linear-gradient(135deg,#2a0c0c,#180808);border-color:var(--red);color:var(--red)}
.btn-red:hover{background:linear-gradient(135deg,#3a1010,#200c0c)}
.btn-sm{padding:.28rem .55rem;font-size:.76rem}

/* ── CHARACTER LIST ── */
.char-list{display:flex;flex-direction:column;gap:.5rem}
.char-item{background:var(--card);border:1px solid var(--border);border-radius:6px;padding:.6rem;display:grid;grid-template-columns:54px 1fr auto;gap:.7rem;align-items:center}
.char-item img{width:50px;height:50px;object-fit:cover;border-radius:4px;border:1px solid var(--border)}
.char-no-img{width:50px;height:50px;background:var(--panel);border:1px solid var(--border);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:var(--dim)}
.char-name{color:var(--gold-l);font-size:.88rem;margin-bottom:.2rem}
.trait-pills{display:flex;flex-wrap:wrap;gap:.2rem}
.trait-pill{background:var(--panel);border:1px solid var(--border);padding:.1rem .3rem;border-radius:3px;font-size:.65rem;color:var(--dim)}
.char-actions{display:flex;flex-direction:column;gap:.25rem}
.no-chars{color:var(--dim);text-align:center;padding:1.5rem;font-style:italic;font-size:.85rem}

/* ── TRAIT TABLE ── */
.trait-table{width:100%;border-collapse:collapse;font-size:.8rem;margin-top:.4rem}
.trait-table th{color:var(--gold-d);text-align:left;padding:.3rem .45rem;border-bottom:1px solid var(--border);text-transform:uppercase;letter-spacing:.08em;font-size:.7rem}
.trait-table td{padding:.3rem .45rem;border-bottom:1px solid rgba(58,47,80,.3);color:var(--dim)}
.trait-table td:first-child{color:var(--gold);white-space:nowrap}
.vpill{background:var(--panel);border:1px solid var(--border);padding:.1rem .3rem;border-radius:10px;margin:.1rem;display:inline-block;font-size:.68rem}

/* ── SET MANAGEMENT ── */
.set-manage{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-top:.6rem}
@media(max-width:500px){.set-manage{grid-template-columns:1fr}}

/* ── NO SET ── */
.no-set-prompt{text-align:center;padding:3rem 1rem;color:var(--dim)}
.no-set-prompt h2{color:var(--gold);font-size:1.4rem;margin-bottom:.6rem}
.no-set-prompt p{margin-bottom:1.2rem;font-style:italic}

/* ── SECTION LABEL ── */
.section-lbl{color:var(--gold);font-size:.8rem;text-transform:uppercase;letter-spacing:.12em;margin:1rem 0 .6rem;padding-bottom:.35rem;border-bottom:1px solid var(--border)}
</style>
</head>
<body>

<header>
    <h1>⚙️ Guess Who — Character Administration</h1>
    <nav>
        <a href="guess_index.php">← Back to Game</a>
        <a href="guess_admin.php">Admin Panel</a>
    </nav>
</header>

<div class="container">

<?php if ($msg): ?>
<div class="msg <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- ── SET SWITCHER BAR ── -->
<div class="set-bar">
    <strong style="color:var(--dim);font-size:.8rem;white-space:nowrap">CHARACTER SET:</strong>
    <?php foreach ($allSets as $s): ?>
    <form method="POST" style="display:inline">
        <input type="hidden" name="switch_set" value="<?= $s['id'] ?>">
        <button type="submit" class="set-pill <?= $s['id'] === $activeSetId ? 'active' : '' ?>">
            <?= htmlspecialchars($s['name']) ?>
            <span class="set-count">(<?= $s['character_count'] ?>)</span>
        </button>
    </form>
    <?php endforeach; ?>
    <button class="set-pill new-set" onclick="document.getElementById('new-set-form').style.display=document.getElementById('new-set-form').style.display==='none'?'block':'none'">+ New Set</button>
</div>

<!-- ── CREATE NEW SET ── -->
<div id="new-set-form" style="display:none;margin-bottom:1rem">
    <form method="POST" class="card" style="max-width:480px">
        <h2>✨ Create New Character Set</h2>
        <div class="fg"><label>Set Name *</label><input type="text" name="set_name" placeholder="e.g. FNAF Security Breach" required></div>
        <div class="fg"><label>Description</label><textarea name="set_desc" placeholder="Brief description of this set..."></textarea></div>
        <button type="submit" name="create_set" class="btn btn-gold btn-block">Create Set</button>
    </form>
</div>

<?php if (!$activeSetId || !$activeSet): ?>
<!-- ── NO SET SELECTED ── -->
<div class="no-set-prompt">
    <h2>🎭 No Set Selected</h2>
    <p>Choose a set above or create a new one to get started.</p>
</div>

<?php else: ?>
<!-- ── ACTIVE SET HEADER ── -->
<div style="margin-bottom:1rem;display:flex;align-items:baseline;gap:1rem;flex-wrap:wrap">
    <h2 style="color:var(--gold-l);font-size:1.2rem"><?= htmlspecialchars($activeSet['name']) ?></h2>
    <?php if ($activeSet['description']): ?>
    <span style="color:var(--dim);font-size:.85rem;font-style:italic"><?= htmlspecialchars($activeSet['description']) ?></span>
    <?php endif; ?>
    <button class="btn btn-sm" style="border-color:var(--dim);color:var(--dim);background:transparent;margin-left:auto"
            onclick="document.getElementById('rename-form').style.display=document.getElementById('rename-form').style.display==='none'?'block':'none'">✏️ Rename</button>
    <form method="POST" onsubmit="return confirm('Delete this entire set and all its characters?')" style="display:inline">
        <input type="hidden" name="delete_set" value="<?= $activeSetId ?>">
        <button type="submit" class="btn btn-sm btn-red">🗑 Delete Set</button>
    </form>
</div>

<!-- Rename form (hidden) -->
<div id="rename-form" style="display:none;margin-bottom:1rem">
    <form method="POST" class="card" style="max-width:480px">
        <h2>✏️ Rename Set</h2>
        <input type="hidden" name="rename_set_id" value="<?= $activeSetId ?>">
        <div class="fg"><label>Name</label><input type="text" name="rename_set_name" value="<?= htmlspecialchars($activeSet['name']) ?>" required></div>
        <div class="fg"><label>Description</label><textarea name="rename_set_desc"><?= htmlspecialchars($activeSet['description'] ?? '') ?></textarea></div>
        <button type="submit" name="rename_set" class="btn btn-gold btn-block">Save</button>
    </form>
</div>

<div class="two-col">

<!-- ── LEFT: ADD / EDIT CHARACTER FORM ── -->
<div>
    <div class="card <?= $editChar ? 'editing' : '' ?>">
        <h2><?= $editChar ? '✏️ Edit Character' : '➕ Add Character' ?></h2>
        <form method="POST" enctype="multipart/form-data" action="guess_admin.php">
            <?php if ($editChar): ?>
                <input type="hidden" name="char_id" value="<?= $editChar['id'] ?>">
                <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editChar['image_path'] ?? '') ?>">
            <?php endif; ?>

            <div class="fg">
                <label>Name *</label>
                <input type="text" name="name" value="<?= htmlspecialchars($editChar['name'] ?? '') ?>" required placeholder="Character name">
            </div>

            <div class="fg">
                <label>Image (jpg, png, gif, webp, svg)</label>
                <input type="file" name="image" accept="image/*">
                <?php if (!empty($editChar['image_path'])): ?>
                    <img src="<?= htmlspecialchars($editChar['image_path']) ?>" class="img-cur">
                    <div style="font-size:.7rem;color:var(--dim);margin-top:.2rem">Upload new to replace</div>
                <?php endif; ?>
            </div>

            <div class="fg">
                <label>Traits for <em><?= htmlspecialchars($activeSet['name']) ?></em> (up to 6)</label>
                <div class="traits-hint">Same label across characters = askable Oracle question</div>
                <?php
                $traitKeys = array_keys($editTraits);
                for ($i = 0; $i < 6; $i++):
                    $lbl = $traitKeys[$i] ?? '';
                    $val = $editTraits[$lbl] ?? '';
                ?>
                <div class="trait-row">
                    <input type="text" name="trait_label[]" value="<?= htmlspecialchars($lbl) ?>" placeholder="Label <?= $i+1 ?>" list="label-suggestions">
                    <input type="text" name="trait_value[]" value="<?= htmlspecialchars($val) ?>" placeholder="Value">
                    <span class="trait-num"><?= $i+1 ?></span>
                </div>
                <?php endfor; ?>
                <datalist id="label-suggestions">
                    <?php foreach ($traitDefs as $td): ?>
                        <option value="<?= htmlspecialchars($td['label']) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <button type="submit" name="save_character" class="btn btn-gold btn-block">
                <?= $editChar ? '💾 Update Character' : '✨ Add Character' ?>
            </button>
            <?php if ($editChar): ?>
                <a href="guess_admin.php" class="btn btn-teal btn-block" style="margin-top:.4rem">✕ Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Trait Definitions Summary -->
    <?php if (!empty($traitDefs)): ?>
    <div class="section-lbl">📋 Trait Categories in This Set</div>
    <div class="card">
        <table class="trait-table">
            <tr><th>Label</th><th>Known Values</th></tr>
            <?php foreach ($traitDefs as $td):
                $vals = json_decode($td['possible_values'], true) ?? [];
            ?>
            <tr>
                <td><?= htmlspecialchars($td['label']) ?></td>
                <td><?php foreach ($vals as $v): ?><span class="vpill"><?= htmlspecialchars($v) ?></span><?php endforeach; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── RIGHT: CHARACTER LIST ── -->
<div>
    <div class="section-lbl">🧑‍🤝‍🧑 Characters in This Set (<?= count($characters) ?>)</div>

    <?php if (empty($characters)): ?>
    <div class="card no-chars">No characters yet — add one using the form!</div>
    <?php else: ?>
    <div class="char-list">
        <?php foreach ($characters as $c): ?>
        <div class="char-item">
            <?php if ($c['image_path']): ?>
                <img src="<?= htmlspecialchars($c['image_path']) ?>" alt="<?= htmlspecialchars($c['name']) ?>">
            <?php else: ?>
                <div class="char-no-img">🎭</div>
            <?php endif; ?>
            <div>
                <div class="char-name"><?= htmlspecialchars($c['name']) ?></div>
                <div class="trait-pills">
                    <?php foreach ($c['traits'] as $lbl => $val): ?>
                        <span class="trait-pill"><?= htmlspecialchars($lbl) ?>: <?= htmlspecialchars($val) ?></span>
                    <?php endforeach; ?>
                    <?php if (empty($c['traits'])): ?>
                        <span style="color:var(--red);font-size:.7rem;font-style:italic">No traits</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="char-actions">
                <a href="guess_admin.php?edit=<?= $c['id'] ?>" class="btn btn-teal btn-sm">✏️</a>
                <form method="POST" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($c['name'])) ?>?')">
                    <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                    <button type="submit" class="btn btn-red btn-sm">🗑</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</div><!-- /two-col -->
<?php endif; ?>

</div><!-- /container -->
</body>
</html>
