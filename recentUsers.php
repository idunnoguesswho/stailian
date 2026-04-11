<hr class="divider">
<h2 class="page-title" style="font-size:1.2rem; margin-bottom:1rem;">Recent Users</h2>
<div class="card">
  <div class="tbl-wrap">
  <table>
    <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Joined</th></tr></thead>
    <tbody>
    <?php foreach ($db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5") as $r): ?>
    <tr>
      <td><?= $r['id'] ?></td>
      <td><?= htmlspecialchars($r['name']) ?></td>
      <td><?= htmlspecialchars($r['email']) ?></td>
      <td style="color:var(--muted)"><?= $r['created_at'] ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>