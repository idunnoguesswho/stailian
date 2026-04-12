// ─────────────────────────────────────────────────────────────────────────────
// auth.js — shared authentication helpers
// Depends on firebase-config.js being loaded first (window.auth, window.db)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Require the user to be logged in. Loads their Firestore profile and
 * calls onUser(profile) once ready. Redirects to login.html if not authed.
 */
function requireAuth(onUser) {
  auth.onAuthStateChanged(async fireUser => {
    if (!fireUser) {
      window.location.href = 'login.html';
      return;
    }
    try {
      const snap = await db.collection('users').doc(fireUser.uid).get();
      if (!snap.exists) {
        await auth.signOut();
        window.location.href = 'login.html';
        return;
      }
      const profile = { uid: fireUser.uid, ...snap.data() };
      if (onUser) onUser(profile);
    } catch (err) {
      console.error('requireAuth error:', err);
      window.location.href = 'login.html';
    }
  });
}

/**
 * Require the user to be an admin. Wraps requireAuth.
 */
function requireAdmin(onUser) {
  requireAuth(profile => {
    if (!profile.is_admin) {
      window.location.href = 'walk.html?msg=' + encodeURIComponent('Admin access required') + '&type=error';
      return;
    }
    if (onUser) onUser(profile);
  });
}

/**
 * Sign out and redirect to login.
 */
function logout() {
  auth.signOut().then(() => { window.location.href = 'login.html'; });
}

// ─────────────────────────────────────────────────────────────────────────────
// Flash messages (passed via ?msg=...&type=success|error in the URL)
// ─────────────────────────────────────────────────────────────────────────────

function getFlash() {
  const p = new URLSearchParams(window.location.search);
  const msg  = p.get('msg');
  const type = p.get('type') === 'error' ? 'alert-error' : 'alert-success';
  if (!msg) return '';
  return `<div class="alert ${type}">${escHtml(msg)}</div>`;
}

function redirect(to, msg, type = 'success') {
  const sep = to.includes('?') ? '&' : '?';
  window.location.href = `${to}${sep}msg=${encodeURIComponent(msg)}&type=${type}`;
}

// ─────────────────────────────────────────────────────────────────────────────
// Utility
// ─────────────────────────────────────────────────────────────────────────────

function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/**
 * Look up a user's email by username.
 * Reads from the public `usernames` collection (no auth required)
 * so this works before the user is signed in.
 * Returns null if not found.
 */
async function emailForUsername(username) {
  const snap = await db.collection('usernames')
    .doc(username.toLowerCase().trim())
    .get();
  if (!snap.exists) return null;
  return snap.data().email ?? null;
}

/**
 * Auto-heal: +10 HP per minute since last_heal, capped at max_health.
 * Updates Firestore and returns the new health value.
 */
async function autoHeal(uid, profile) {
  const now       = Date.now();
  const lastHeal  = profile.last_heal?.toMillis?.() ?? now;
  const mins      = Math.floor((now - lastHeal) / 60000);
  if (mins < 1) return profile.health;

  const healed  = Math.min(profile.health + mins * 10, profile.max_health);
  await db.collection('users').doc(uid).update({
    health:    healed,
    last_heal: firebase.firestore.FieldValue.serverTimestamp()
  });
  return healed;
}
