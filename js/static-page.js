const STATIC_PAGE_COPY = {
  'rules.html': {
    title: 'How to Play',
    subtitle: 'A quick guide to the static Stailian experience.',
    status: 'Available on GitHub Pages',
    sections: [
      ['The World', 'Explore a 10 by 10 elemental map across surface, sky, and underworld layers. Tiles influence resources, movement, and elemental battle strengths.'],
      ['Walking', 'Use Walk to roll dice, move across the map, gather flowers, scrolls, resources, and update your trail in Firebase.'],
      ['Wardrobe', 'Use Wardrobe to manage clothing, dyes, inventory, coins, and equipped items from the browser.'],
      ['Scorecard', 'Use Scorecard to review player stats, elemental power, equipment, recent moves, and battles.'],
      ['PHP Features', 'Some older tools still depend on PHP sessions and a SQL database. Their static pages explain what needs a Firebase or browser-side rewrite before they can fully run on GitHub Pages.']
    ],
    actions: [
      ['Start Walking', 'walk.html'],
      ['View Scorecard', 'scorecard.html'],
      ['Open Wardrobe', 'wardrobe.html']
    ]
  },
  'battle.html': {
    title: 'Battle Arena',
    subtitle: 'The legacy arena needs a browser-side rewrite.',
    status: 'Server feature pending conversion',
    sections: [
      ['Why this page changed', 'GitHub Pages cannot run the PHP form handler that calculated battles from SQL tables.'],
      ['Current static path', 'Walk can still trigger Firebase-backed play, and Scorecard can show player power from Firestore data.'],
      ['Next conversion target', 'Move the battle score calculation into JavaScript using Firestore collections for users, characters, skills, weapons, and clothing.']
    ],
    actions: [
      ['Go to Walk', 'walk.html'],
      ['View Scorecard', 'scorecard.html']
    ]
  },
  'masterbuilder.html': {
    title: 'Master Builder',
    subtitle: 'The 3D game shell can run in the browser, but its rewards still need Firebase storage.',
    status: 'Server feature pending conversion',
    sections: [
      ['What blocked it', 'The PHP version stores game phase in server sessions and reads build sets and rewards from SQL.'],
      ['Static rewrite path', 'Store build sets in Firestore, keep current phase in local state or a user game-state document, and award rewards with Firestore writes.'],
      ['Safe for Pages', 'The page is now served as HTML so navigation no longer breaks on GitHub Pages.']
    ],
    actions: [
      ['Back to Walk', 'walk.html'],
      ['Read Rules', 'rules.html']
    ]
  },
  'map.html': {
    title: 'Map Admin',
    subtitle: 'Map editing still depends on server-side data mutations.',
    status: 'Admin feature pending conversion',
    sections: [
      ['Static status', 'The PHP map editor cannot run on GitHub Pages.'],
      ['Conversion target', 'Use Firestore map_tiles and tile_types directly from an admin-only browser page protected by Firebase Auth rules.']
    ],
    actions: [['Go to Walk', 'walk.html']]
  },
  'charbase.html': {
    title: 'Characters',
    subtitle: 'Character administration is not yet static.',
    status: 'Admin feature pending conversion',
    sections: [
      ['Static status', 'The PHP character editor used SQL and server forms.'],
      ['Conversion target', 'Create a Firebase admin page for the characters collection and protect writes with Firestore rules.']
    ],
    actions: [['Go to Walk', 'walk.html']]
  },
  'weapons.html': {
    title: 'Weapons',
    subtitle: 'Weapon administration is not yet static.',
    status: 'Admin feature pending conversion',
    sections: [
      ['Static status', 'The PHP weapon editor used SQL tables and server forms.'],
      ['Conversion target', 'Move weapon definitions and skill links into Firestore and edit them from an admin-only HTML page.']
    ],
    actions: [['Go to Walk', 'walk.html']]
  },
  'skills.html': {
    title: 'Skills',
    subtitle: 'Skill administration is not yet static.',
    status: 'Admin feature pending conversion',
    sections: [
      ['Static status', 'The PHP skill editor used SQL tables and server forms.'],
      ['Conversion target', 'Move skill definitions into Firestore and edit them from an admin-only HTML page.']
    ],
    actions: [['Go to Walk', 'walk.html']]
  },
  'tiletypes.html': {
    title: 'Tile Types',
    subtitle: 'Tile type administration is not yet static.',
    status: 'Admin feature pending conversion',
    sections: [
      ['Static status', 'The PHP tile type editor used SQL tables and server forms.'],
      ['Conversion target', 'Move tile types, colors, resources, and multipliers into Firestore and edit them from an admin-only HTML page.']
    ],
    actions: [['Go to Walk', 'walk.html']]
  },
  'tower.html': {
    title: 'Tower',
    subtitle: 'Layer travel still needs a Firebase/browser rewrite.',
    status: 'Server feature pending conversion',
    sections: [
      ['Static status', 'The PHP tower action used server sessions, SQL updates, and health checks.'],
      ['Conversion target', 'Move layer travel into Walk or a Firebase-backed Tower page that updates user layer, health, and tower scroll inventory in Firestore.']
    ],
    actions: [
      ['Go to Walk', 'walk.html'],
      ['Read Rules', 'rules.html']
    ]
  },
  'changepassword.html': {
    title: 'Account',
    subtitle: 'Password changes are handled through Firebase Auth.',
    status: 'Available on GitHub Pages',
    sections: [
      ['Password reset', 'Use the button below to send a Firebase password-reset email to your signed-in account.'],
      ['Profile data', 'Your public game profile is stored in Firestore and shown in Scorecard.']
    ],
    actions: [
      ['Send Password Reset', '#reset-password'],
      ['View Scorecard', 'scorecard.html']
    ]
  }
};

function currentStaticPage() {
  return location.pathname.split('/').pop() || 'rules.html';
}

function renderStaticContent(page, user) {
  const data = STATIC_PAGE_COPY[page] || STATIC_PAGE_COPY['rules.html'];
  renderPageHeader(page, user || null);
  const root = document.getElementById('static-page');
  root.innerHTML = `
    <h1 class="page-title">${escHtml(data.title)}</h1>
    <p class="page-sub">${escHtml(data.subtitle)}</p>
    <div class="card" style="border-color:var(--gold-dim);">
      <div class="card-title">${escHtml(data.status)}</div>
      <div style="display:flex;flex-direction:column;gap:.85rem;">
        ${data.sections.map(([heading, body]) => `
          <section style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:.85rem 1rem;">
            <div style="font-family:'Cinzel',serif;font-size:.78rem;color:var(--gold);letter-spacing:.06em;margin-bottom:.25rem;">${escHtml(heading)}</div>
            <p style="font-size:.94rem;color:var(--muted);line-height:1.7;margin:0;">${escHtml(body)}</p>
          </section>
        `).join('')}
      </div>
      <div class="btn-row">
        ${data.actions.map(([label, href]) => `<a class="btn ${href === '#reset-password' ? 'btn-primary' : 'btn-outline'}" href="${href}">${escHtml(label)}</a>`).join('')}
      </div>
    </div>`;
  renderPageFooter();
  wireAccountActions(user);
}

function wireAccountActions(user) {
  const reset = document.querySelector('a[href="#reset-password"]');
  if (!reset) return;
  reset.addEventListener('click', async event => {
    event.preventDefault();
    if (!auth.currentUser?.email) {
      redirect('login.html', 'Sign in before requesting a password reset.', 'error');
      return;
    }
    await auth.sendPasswordResetEmail(auth.currentUser.email);
    reset.textContent = 'Reset Email Sent';
    reset.classList.remove('btn-primary');
    reset.classList.add('btn-outline');
  });
}

function bootStaticPage(options = {}) {
  const page = currentStaticPage();
  if (options.public) {
    auth.onAuthStateChanged(async fireUser => {
      if (!fireUser) {
        renderStaticContent(page, null);
        return;
      }
      const snap = await db.collection('users').doc(fireUser.uid).get();
      renderStaticContent(page, snap.exists ? { uid: fireUser.uid, ...snap.data() } : null);
    });
    return;
  }
  requireAuth(user => renderStaticContent(page, user));
}
