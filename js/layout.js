// ─────────────────────────────────────────────────────────────────────────────
// layout.js — shared nav / header / footer builders
// Depends on auth.js (escHtml, logout)
// ─────────────────────────────────────────────────────────────────────────────

// Nav items for regular users
const NAV_USER = [
  { url: 'walk.html',          icon: '🎲', label: 'Walk'         },
  { url: 'scorecard.html',     icon: '🃏', label: 'Scorecard'    },
  { url: 'wardrobe.html',      icon: '👕', label: 'Wardrobe'     },
  { url: 'battle.html',        icon: '⚔',  label: 'Battle'       },
  { url: 'masterbuilder.html', icon: '🧱', label: 'Masterbuilder'},
  { url: 'rules.html',         icon: '📜', label: 'Rules'        },
];

// Extra items only admins see
const NAV_ADMIN = [
  { url: 'charbase.html',   icon: '👤', label: 'Characters' },
  { url: 'weapons.html',    icon: '🗡',  label: 'Weapons'    },
  { url: 'skills.html',     icon: '✨', label: 'Skills'     },
  { url: 'map.html',        icon: '🗺',  label: 'Map'        },
  { url: 'tiletypes.html',  icon: '🏔', label: 'Tile Types' },
];

// Guest nav (not logged in)
const NAV_GUEST = [
  { url: 'login.html', icon: '🔑', label: 'Login'    },
  { url: 'rules.html', icon: '📜', label: 'Rules'    },
];

/**
 * Inject the full game page header (nav + <main> open tag).
 * Call at the top of <body> with a placeholder div:
 *   <div id="page-header"></div>
 * then: renderPageHeader('walk.html', userProfile);
 */
function renderPageHeader(activePage, user) {
  const isLoggedIn = !!user;
  const isAdmin    = isLoggedIn && user.is_admin;

  let items = isAdmin
    ? [...NAV_USER, ...NAV_ADMIN]
    : isLoggedIn
      ? NAV_USER
      : NAV_GUEST;

  const navLinks = items.map(item => {
    const cls = (activePage === item.url) ? ' class="active"' : '';
    return `<a href="${item.url}"${cls}>${item.icon} ${escHtml(item.label)}</a>`;
  }).join('');

  // Health bar HTML
  let healthHtml = '';
  if (isLoggedIn && user.health != null) {
    const pct    = Math.round((user.health / user.max_health) * 100);
    const color  = pct > 60 ? '#27ae60' : pct > 25 ? '#e8622a' : '#c0392b';
    const pulse  = pct <= 25 ? 'animation:navpulse 1s infinite;' : '';
    healthHtml = `
      <style>@keyframes navpulse{0%,100%{opacity:1}50%{opacity:.4}}</style>
      <div style="display:flex;align-items:center;gap:.4rem;"
           title="${user.health}/${user.max_health} HP">
        <span style="font-size:.65rem;color:#c0392b;">❤</span>
        <div style="width:60px;background:rgba(255,255,255,.1);
             border-radius:10px;height:6px;overflow:hidden;">
          <div style="background:${color};width:${pct}%;height:100%;
               border-radius:10px;${pulse}"></div>
        </div>
        <span style="font-size:.65rem;color:${color};font-family:'Cinzel',serif;">${user.health}</span>
      </div>`;
  }

  const adminBadge = isAdmin
    ? `<span style="font-family:'Cinzel',serif;font-size:.6rem;letter-spacing:.1em;
         color:var(--gold);background:rgba(200,153,58,.15);border:1px solid var(--gold-dim);
         border-radius:20px;padding:.15rem .5rem;">⚜ ADMIN</span>`
    : '';

  const userLinks = isLoggedIn
    ? `${healthHtml}
       ${adminBadge}
       <a href="changepassword.html" style="font-size:.75rem;color:var(--muted);text-decoration:none;"
          onmouseenter="this.style.color='var(--gold)'" onmouseleave="this.style.color='var(--muted)'">
         👤 ${escHtml(user.name)}
       </a>
       <a href="#" onclick="logout();return false;"
          style="font-size:.7rem;color:var(--muted);text-decoration:none;
                 font-family:'Cinzel',serif;letter-spacing:.06em;"
          onmouseenter="this.style.color='var(--gold)'" onmouseleave="this.style.color='var(--muted)'">
         🚪 Logout
       </a>`
    : '';

  const brandHref = isLoggedIn ? 'walk.html' : 'login.html';

  document.getElementById('page-header').outerHTML = `
<nav>
  <a href="${brandHref}" class="nav-brand">⚜ STAILIAN</a>
  <button class="nav-toggle" onclick="toggleNav()" aria-label="Menu">☰</button>
  <div class="nav-links" id="navLinks">${navLinks}</div>
  <div class="nav-right" id="navRight">${userLinks}</div>
</nav>
<script>
function toggleNav() {
  document.getElementById('navLinks').classList.toggle('open');
  document.getElementById('navRight').classList.toggle('open');
  var btn = document.querySelector('.nav-toggle');
  btn.textContent = btn.textContent === '☰' ? '✕' : '☰';
}
<\/script>
<main>`;
}

/**
 * Inject the page footer. Place <div id="page-footer"></div> at end of body.
 */
function renderPageFooter() {
  const el = document.getElementById('page-footer');
  if (el) el.outerHTML = `</main><footer>Stailian Game Database</footer>`;
}

// ─────────────────────────────────────────────────────────────────────────────
// Auth page helpers (login, register, forgot, reset)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Render the auth card header. Call before your form HTML.
 * Place <div id="auth-header"></div> as first child of <body>.
 */
function renderAuthHeader(subtitle) {
  const el = document.getElementById('auth-header');
  if (!el) return;
  el.outerHTML = `
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo">⚜ STAILIAN</div>
    ${subtitle ? `<div class="auth-sub">${escHtml(subtitle)}</div>` : ''}`;
}

/**
 * Close the auth card. Place <div id="auth-footer"></div> at end of <body>.
 */
function renderAuthFooter() {
  const el = document.getElementById('auth-footer');
  if (el) el.outerHTML = `  </div>\n</div>`;
}
