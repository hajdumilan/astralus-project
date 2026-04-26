<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

autoLoginFromRememberCookie($pdo);

$isLoggedIn = false;
$userName = '';

if (!empty($_SESSION['user_id'])) {
    $isLoggedIn = true;
    $userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT name, credits
    FROM users
    WHERE id = :id
    LIMIT 1
");

    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $userName = $user['name'] ?? ($_SESSION['user_name'] ?? '');
    } else {
        $userName = $_SESSION['user_name'] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Astralus Intelligence</title>
  <link rel="icon" href="data:image/svg+xml,
  <svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22>
    <text y=%22.9em%22 font-size=%2290%22>✨</text>
  </svg>">
  <link rel="preload" href="/assets/fonts/next-sphere/NextSphere-Black.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="styles_refactored.css?v=<?php echo time(); ?>">
</head>
<body data-index-page data-hero-title-v2="on" data-hero-weight="black">
<header class="header header-shell" data-site-header>
  <div class="header-liquid-layer" aria-hidden="true"></div>
  <div class="header-border-shine" aria-hidden="true"></div>
  <div class="header-inner header-ultra-inner">

    <div class="header-zone-left header-left header-left-shell">
      <a href="#top" class="brand-ultra-link header-brand-shell">
        <div class="brand-ultra-mark">
          <div class="brand-core">✦</div>
          <div class="brand-orbit orbit-1"></div>
          <div class="brand-orbit orbit-2"></div>
        </div>

        <div class="brand-copy brand-label" data-brand-label>
          <span class="brand-name">Astralus</span>
          <span class="brand-sub">Intelligence</span>
        </div>
      </a>

      <?php if (!$isLoggedIn): ?>
        <div class="header-login-standalone compact-login-slot" data-header-login aria-hidden="false">
          <a href="login.php" class="login-btn-header header-login-standalone-btn compact-login-btn" data-header-login-btn>Bejelentkezés</a>
        </div>
      <?php endif; ?>

    </div>

    <div class="desktop-nav-center header-center-shell">
      <nav class="floating-center-nav header-nav-shell" aria-label="Elsődleges navigáció">
        <a href="#modulok" class="modern-nav-link">Funkciók</a>
        <a href="#muvelet" class="modern-nav-link">Hogyan működik</a>
      </nav>
    </div>

    <div class="header-zone-right desktop-actions header-right-shell">
      <div class="header-auth-row header-auth-shell">
        <?php if (!$isLoggedIn): ?>
          <a href="register.php" class="modern-ghost-btn">Regisztráció</a>
        <?php else: ?>
          <div class="user-chip">
            <?php echo htmlspecialchars($userName ?: ($_SESSION['user_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="header-utility-actions header-utility-shell">
        <button id="theme-toggle" class="theme-btn" type="button" aria-label="Téma váltása">Light</button>

        <a href="#app" class="cta-ultra-btn">
          <span class="cta-label-wrap">
            <span class="cta-label-main">Indítás</span>
          </span>
          <span class="cta-icon">✦</span>
          <span class="cta-shine"></span>
        </a>
      </div>
    </div>

    <div class="mobile-brand">
      <span class="mobile-brand-text">Astralus Intelligence</span>
    </div>

    <div class="mobile-header-actions">
      <button
        id="mobile-theme-toggle"
        class="mobile-theme-btn"
        type="button"
        aria-label="Téma váltása"
      >Light</button>

      <button
        id="mobile-menu-open"
        class="mobile-menu-btn"
        type="button"
        aria-label="Menü megnyitása"
      >
        <span></span>
        <span></span>
      </button>
    </div>

  </div>

  <div class="header-pointer-glow"></div>
</header>

<div id="mobile-drawer-overlay" class="mobile-drawer-overlay" hidden></div>

<aside id="mobile-drawer" class="mobile-drawer" aria-hidden="true">
  <div class="mobile-drawer-head">
    <div class="mobile-drawer-title">Menü</div>
    <button id="mobile-menu-close" class="mobile-drawer-close" type="button" aria-label="Menü bezárása">✕</button>
  </div>

  <div class="mobile-drawer-views" id="mobile-drawer-views">
    <div class="mobile-drawer-view mobile-drawer-view-main is-active" id="mobile-drawer-view-main">
      <div class="mobile-drawer-body">
        <div class="mobile-drawer-section">
          <div class="mobile-drawer-section-title">Főmenü</div>

          <button type="button" class="mobile-drawer-link mobile-tools-entry-btn" id="mobile-tools-entry-btn">
            <span>Eszközök</span>
            <span class="mobile-entry-arrow">›</span>
          </button>

          <a href="#modulok" class="mobile-drawer-link">Funkciók</a>
          <a href="#muvelet" class="mobile-drawer-link">Hogyan működik</a>
        </div>

        <div class="mobile-drawer-divider"></div>

        <?php if (empty($_SESSION['user_id'])): ?>
          <a href="login.php" class="mobile-drawer-link dark">Bejelentkezés</a>
          <a href="register.php" class="mobile-drawer-link dark">Regisztráció</a>
        <?php else: ?>
          <div class="mobile-drawer-user"><?php echo htmlspecialchars($userName ?: ($_SESSION['user_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
          <button id="mobile-logout-btn" class="mobile-drawer-link dark" type="button">Kilépés</button>
        <?php endif; ?>

        <a href="#app" class="mobile-drawer-link primary">Indítás ✦</a>
      </div>
    </div>

    <div class="mobile-drawer-view mobile-drawer-view-tools" id="mobile-drawer-view-tools">
      <div class="mobile-subview-head">
        <button type="button" class="mobile-subview-back" id="mobile-tools-back-btn">‹</button>
        <div class="mobile-subview-title">Eszközök</div>
      </div>

      <div class="mobile-subview-body">
        <div class="mobile-tools-helper-text">
          Itt tudja kiválasztani, mely funkciót szeretné használni
        </div>

        <button type="button" class="mobile-tool-switch active" data-mobile-tab="news">
          <span class="mobile-tool-switch-label">Hírkereső</span>
        </button>

        <button type="button" class="mobile-tool-switch" data-mobile-tab="notes">
          <span class="mobile-tool-switch-label">Jegyzet Összefoglaló</span>
        </button>

        <button type="button" class="mobile-tool-switch" data-mobile-tab="study">
          <span class="mobile-tool-switch-label">Astralus Study</span>
        </button>

        <button type="button" class="mobile-tool-switch" data-mobile-tab="chat">
          <span class="mobile-tool-switch-label">AI Chat</span>
        </button>

        <button type="button" class="mobile-tool-switch" data-mobile-tab="history">
          <span class="mobile-tool-switch-label">Előzmények</span>
        </button>
      </div>
    </div>
  </div>
</aside>

<main id="top">
  <div class="sparkles-layer" aria-hidden="true">
    <div class="sparkles-stars"></div>
    <div class="sparkles-stars sparkles-stars--mid"></div>
    <div class="sparkles-stars sparkles-stars--near"></div>

    <div class="sparkles-shooting">
      <span></span>
      <span></span>
      <span></span>
    </div>

    <div class="hero-orbit-lines">
      <span></span>
      <i></i>
    </div>
  </div>

  <div class="index-cinematic-intro" data-intro-root aria-hidden="true">
    <div class="index-cinematic-backdrop"></div>
    <div class="index-cinematic-atmosphere"></div>
    <div class="index-cinematic-grid"></div>
    <div class="index-cinematic-vignette"></div>

    <div class="index-cinematic-center">
      <div class="index-cinematic-emblem">
        <div class="index-cinematic-emblem-glow"></div>
        <div class="index-cinematic-emblem-ring index-cinematic-emblem-ring--outer"></div>
        <div class="index-cinematic-emblem-ring index-cinematic-emblem-ring--inner"></div>
        <div class="index-cinematic-emblem-flare"></div>

        <div class="index-cinematic-emblem-core">
        <span class="index-intro-star-symbol" aria-hidden="true">✦</span>
      </div>

        <div class="index-cinematic-particles index-intro-particles"></div>
      </div>
    </div>
  </div>

<section class="hero hero-index-cinematic" data-astralus-hero>
  <div class="container hero-inner hero-index-cinematic-inner">
    <div class="hero-card hero-index-card">
      <div class="hero-index-badge-wrap" data-hero-eyebrow-wrap>
        <div class="eyebrow hero-eyebrow hero-index-badge" data-hero-eyebrow>
          <span class="eyebrow-dot"></span>
          Astralus Intelligence
        </div>
      </div>

      <div class="hero-index-copy hero-copy">
        <h1 class="hero-title hero-index-title cinematic-title" data-hero-title aria-label="Keress híreket Foglalj össze bármit">
          <span class="hero-title-mask">
            <span class="hero-title-line primary" data-hero-title-line>
              <span class="hero-title-line-inner">Keress híreket</span>
            </span>
          </span>
          <span class="hero-title-mask">
            <span class="hero-title-line secondary" data-hero-title-line>
              <span class="hero-title-line-inner">Foglalj össze</span>
            </span>
          </span>
          <span class="hero-title-mask">
            <span class="hero-title-line tertiary hero-title-line--accent" data-hero-title-line>
              <span class="hero-title-line-inner">Bármit</span>
            </span>
          </span>
        </h1>

        <p class="hero-text hero-text-animated hero-index-text" data-hero-copy data-hero-text>
          Letisztult, prémium és cinematic AI-élmény — olyan nyitó hero-val,
          ami már az első pillanatban minőséget sugall.
        </p>

        <div class="hero-actions hero-actions-animated hero-index-actions" data-hero-actions>
          <a href="#app" class="primary-btn hero-index-main-cta">
            <span class="hero-index-main-cta-label">Kipróbálom</span>
            <span class="hero-index-main-cta-shine"></span>
          </a>

          <a href="#modulok" class="secondary-btn hero-index-secondary-cta">
            <span>További részletek</span>
            <span class="hero-index-secondary-arrow">↓</span>
          </a>
        </div>
      </div>
    </div>

    <div class="hero-index-media" data-hero-media aria-hidden="true">
      <div class="hero-index-media-glow hero-index-media-glow-1"></div>
      <div class="hero-index-media-glow hero-index-media-glow-2"></div>
      <div class="hero-index-orb hero-index-orb-1"></div>
      <div class="hero-index-orb hero-index-orb-2"></div>
      <div class="hero-index-grid"></div>
      <div class="hero-index-ring hero-index-ring-1"></div>
      <div class="hero-index-ring hero-index-ring-2"></div>
      <div class="hero-index-beam"></div>
    </div>
  </div>
</section>

<section class="section ai-form" id="app">
  <div class="container">
    <div class="section-head">
      <div class="section-kicker">✦ Interfész modul</div>
      <h2>Müvelet Választása</h2>
      <p>Előnézetben működő AI felület, PDF-beolvasás, chat és dark mode kapcsoló.</p>
    </div>

    <div class="tool-panel ai-form-shell">
      <div class="tool-tabs">
        <button class="tool-tab active" type="button" data-tab="news">🔎 Hírkereső</button>
        <button class="tool-tab" type="button" data-tab="notes">📝 Jegyzet Összefoglaló</button>
        <button class="tool-tab" type="button" data-tab="study">🎓 Astralus Study</button>
        <button class="tool-tab" type="button" data-tab="chat">💬 AI Chat</button>
        <button class="tool-tab" type="button" data-tab="history">🕘 Előzmények</button>
      </div>

      <div class="panel-grid">
        <div class="sub-grid">
          <div class="tool-box modern-card ai-form-card">

            <div class="tab-panel" id="panel-news" data-news-panel">
  <div class="module-hero-card">
    <div class="module-hero-top">
      <div class="module-hero-badge">🔎 Hírkereső</div>
      <div class="module-hero-icon">✦</div>
    </div>

    <div class="module-hero-copy">
      <h3 class="module-hero-title">Hírkereső + AI összefoglaló</h3>
      <p class="module-hero-subtitle">
        Gyors hírkeresés és automatikus összefoglaló egy letisztult AI felületen.
      </p>
    </div>

    <div class="module-form-section">
      <div class="field">
        <label for="news-query">Keresési téma</label>
        <input id="news-query" class="input" type="text" placeholder="Példa: mesterséges intelligencia az oktatásban" />
      </div>

      <div class="field">
        <label for="summary-type">Összefoglalás típusa</label>

        <div class="custom-select" data-select="summary-type">
          <button type="button" class="custom-select-trigger">
            <span class="custom-select-value">Rövid kivonat</span>
            <span class="custom-select-arrow">
              <svg viewBox="0 0 24 24">
                <path d="M6 9L12 15L18 9"></path>
              </svg>
            </span>
          </button>

          <div class="custom-select-dropdown">
            <button type="button" class="custom-select-option is-selected" data-value="rovid-kivonat">Rövid kivonat</button>
            <button type="button" class="custom-select-option" data-value="reszletes-osszefoglalo">Részletes összefoglaló</button>
            <button type="button" class="custom-select-option" data-value="vizsgajegyzet">Vizsgajegyzet</button>
          </div>

          <input type="hidden" id="summary-type" name="summary-type" value="rovid-kivonat">
        </div>
      </div>

      <div class="module-actions">        <button id="run-news-btn" class="btn btn-dark module-primary-btn" type="button">
          Hírek lekérése + AI összefoglaló
        </button>
      </div>
    </div>
  </div>

  <div class="news-list" id="news-list"></div>
</div>

            <div class="tab-panel" id="panel-notes" hidden>
  <div class="module-hero-card">
    <div class="module-hero-top">
      <div class="module-hero-badge">📝 Jegyzet</div>
      <div class="module-hero-icon">📄</div>
    </div>

    <div class="module-hero-copy">
      <h3 class="module-hero-title">Jegyzet Összefoglaló</h3>
      <p class="module-hero-subtitle">
        Szöveg vagy PDF tartalom feldolgozása vizsgajegyzethez.
      </p>
    </div>

    <div class="module-form-section">
      <div class="field">
        <label for="pdf-file">PDF feltöltés</label>
        <input id="pdf-file" class="file-input-hidden" type="file" accept="application/pdf" multiple />
        <label for="pdf-file" class="file-upload-btn">
          <span class="upload-icon">📄</span>
          <span class="upload-text">PDF fájlok kiválasztása</span>
        </label>
        <div class="hint">
          Egyszerre több PDF is feltölthető. A rendszer beolvassa őket, de a PDF szövege nem jelenik meg a bemeneti mezőben.
        </div>
        <div id="pdf-file-list" class="hint" style="margin-top:8px;"></div>
      </div>

      <div class="field">
        <label for="notes-input">Bemeneti szöveg</label>
        <textarea id="notes-input" class="textarea" placeholder="Ide írhatsz saját kiegészítő szöveget. A feltöltött PDF-ek szövege nem fog itt megjelenni."></textarea>
      </div>

      <div class="field">
        <label for="output-format">Kimenet formátuma</label>

        <div class="custom-select" data-select="output-format">
          <button type="button" class="custom-select-trigger">
            <span class="custom-select-value">Bullet point összefoglaló</span>
            <span class="custom-select-arrow">
              <svg viewBox="0 0 24 24">
                <path d="M6 9L12 15L18 9"></path>
              </svg>
            </span>
          </button>

          <div class="custom-select-dropdown">
            <button type="button" class="custom-select-option is-selected" data-value="bullet-point">Bullet point összefoglaló</button>
            <button type="button" class="custom-select-option" data-value="bekezdeses">Bekezdéses összefoglaló</button>
            <button type="button" class="custom-select-option" data-value="tanulokartya">Tanulókártya stílus</button>
          </div>

          <input type="hidden" id="output-format" name="output-format" value="bullet-point">
        </div>
      </div>

      <div class="module-actions">        <button id="run-notes-btn" class="btn btn-dark module-primary-btn" type="button">
          Összefoglalás készítése
        </button>
      </div>
    </div>
  </div>
</div>

            <div class="tab-panel" id="panel-study" hidden>
  <div class="module-hero-card">
    <div class="module-hero-top">
      <div class="module-hero-badge">🎓 Study</div>
      <div class="module-hero-icon">🧠</div>
    </div>

    <div class="module-hero-copy">
      <h3 class="module-hero-title">Astralus Study</h3>
      <p class="module-hero-subtitle">
        Adj meg témát, tantárgyat és szintet, és kapsz kész tanulási anyagot.
      </p>
    </div>

    <div class="module-form-section">
      <div class="field">
        <label for="study-subject">Tantárgy</label>
        <input id="study-subject" class="input" type="text" placeholder="Példa: történelem" />
      </div>

      <div class="field">
        <label for="study-topic">Téma / feladat</label>
        <textarea id="study-topic" class="textarea" placeholder="Példa: A reformkor fő eseményei"></textarea>
      </div>

      <div class="field">
        <label for="study-level">Szint</label>

        <div class="custom-select" data-select="education">
          <button type="button" class="custom-select-trigger">
            <span class="custom-select-value">Általános iskola</span>
            <span class="custom-select-arrow">
              <svg viewBox="0 0 24 24">
                <path d="M6 9L12 15L18 9"></path>
              </svg>
            </span>
          </button>

          <div class="custom-select-dropdown">
            <button type="button" class="custom-select-option is-selected" data-value="altalanos-iskola">Általános iskola</button>
            <button type="button" class="custom-select-option" data-value="kozepiskola">Középiskola</button>
            <button type="button" class="custom-select-option" data-value="erettsegi-felkeszites">Érettségi felkészítés</button>
            <button type="button" class="custom-select-option" data-value="egyetem">Egyetem</button>
          </div>

          <input type="hidden" id="study-level" name="study-level" value="altalanos-iskola">
        </div>
      </div>

      <div class="module-actions">        <button id="run-study-btn" class="btn btn-dark module-primary-btn" type="button">
          Jegyzet generálása
        </button>
      </div>
    </div>
  </div>
</div>

            <div class="tab-panel" id="panel-chat" hidden>
  <div class="module-hero-card module-chat-shell">
    <div class="module-hero-top">
      <div class="module-hero-badge">💬 AI Chat</div>
      <div class="module-hero-icon">✦</div>
    </div>

    <div class="module-hero-copy">
      <h3 class="module-hero-title">AI Chat</h3>
      <p class="module-hero-subtitle">
        Írj kérdést vagy témát és az AI válaszol.
      </p>
    </div>

    <div class="module-form-section">
      <div class="module-surface-box">
        <div class="chat-messages" id="chat-messages">
          <div class="chat-bubble assistant">Szia! Írj ide kérdést vagy tanulási témát.</div>
        </div>
      </div>

      <div class="field" style="margin-top:16px;">
        <label for="chat-input">Üzenet</label>
        <textarea id="chat-input" class="textarea" placeholder="Írd ide a kérdésed..."></textarea>
      </div>

      <div class="module-actions">        <button id="send-chat-btn" class="btn btn-dark module-primary-btn" type="button">Küldés</button>
        <button id="clear-chat-btn" class="btn btn-light module-secondary-btn" type="button">Chat törlése</button>
      </div>
    </div>
  </div>
</div>

            <div class="tab-panel" id="panel-history" hidden>
  <div class="module-hero-card">
    <div class="module-hero-top">
      <div class="module-hero-badge">🕘 Előzmények</div>
      <div class="module-hero-icon">✦</div>
    </div>

    <div class="module-hero-copy">
      <h3 class="module-hero-title">Mentett előzmények</h3>
      <p class="module-hero-subtitle">
        A korábbi AI műveletek, összefoglalók és beszélgetések itt jelennek meg. Egy kattintással újranyithatod őket.
      </p>
    </div>

    <div class="module-actions">
      <button class="history-clear-btn module-secondary-btn" id="clear-history-btn" type="button">
        <span class="history-clear-btn-icon">🗑</span>
        <span>Összes előzmény törlése</span>
      </button>
    </div>

    <div class="history-list module-history-empty" id="history-list">
      <div class="history-empty-state">
        <div class="history-empty-icon">✦</div>
        <h4>Még nincs mentett előzmény</h4>
        <p>Amint használsz egy modult vagy beszélgetsz az AI-jal, itt szépen rendezve megjelennek a mentett elemek.</p>
      </div>
    </div>
  </div>
</div>

          </div>
        </div>

        <aside class="output-box modern-card ai-output-card" id="output-box">
          <div class="panel-head">
            <h3 class="panel-title">AI Kimenet</h3>
            <p class="panel-subtitle">
              Ebben a panelben jelenik meg a hírösszefoglaló, a jegyzetkivonat vagy a tanulási vázlat.
            </p>
          </div>

          <div class="output-content" id="output-content">
            Töltsd ki a mezőket, válassz egy modult, majd indítsd a feldolgozást.
          </div>

          <div class="output-tags" id="output-tags"></div>
        </aside>
      </div>
    </div>
  </div>
</section>

<section class="section" id="modulok">
  <div class="container">
    <div class="section-head">
      <div class="section-kicker">✦ Fő modulok</div>
      <h2>Fö Modulok</h2>
      <p>Hírösszefoglalás, PDF feldolgozás, jegyzetkészítés, AI chat és előzménymentés egy rendszerben.</p>
    </div>

    <div class="grid-3">
      <article class="card"><div class="feature-icon">🌐</div><h3>Hírkeresö modul</h3><p>Gyors témakeresés, áttekinthető találatok és automatikus AI összefoglaló egy helyen.</p></article>
      <article class="card"><div class="feature-icon">📄</div><h3>PDF Összefoglaló</h3><p>Böngészőből feltöltött PDF szövegének kivonata.</p></article>
      <article class="card"><div class="feature-icon">💬</div><h3>AI Chat</h3><p>Teljes beszélgetős panel helyi mentéssel.</p></article>
      <article class="card"><div class="feature-icon">🧠</div><h3>Házi Feladat AI</h3><p>Dolgozatra és beadandóra használható jegyzetek.</p></article>
      <article class="card"><div class="feature-icon">🕘</div><h3>Elözménymentés</h3><p>Korábbi válaszok és AI munkamenetek visszanézhetők.</p></article>
      <article class="card"><div class="feature-icon">🌙</div><h3>Dark mode</h3><p>Egy kattintással átváltható világos és sötét mód között.</p></article>
    </div>
  </div>
</section>

<section class="section" id="muvelet">
  <div class="container">
    <div class="section-head">
      <div class="section-kicker">✦ Működési protokoll</div>
      <h2>Végrehajtási Sorrend</h2>
      <p>3 egyszerű lépésben működik.</p>
    </div>

    <div class="workflow">
      <article class="step"><div class="step-num">01</div><h3>Mód Választása</h3><p>Válassz modult a hír, jegyzet, házi feladat, chat vagy előzmények közül.</p></article>
      <article class="step"><div class="step-num">02</div><h3>Bemenet Megadása</h3><p>Írd be a témát, tölts fel PDF-et vagy kezdj beszélgetni.</p></article>
      <article class="step"><div class="step-num">03</div><h3>AI Kimenet</h3><p>Kész jegyzetet, kivonatot, vázlatot vagy chat választ kapsz.</p></article>
    </div>
  </div>
</section>

</main>

<footer class="site-footer">
  <div class="container footer-box">
    <div><strong>Astralus Intelligence</strong><br>Hírkereső, jegyzet összefoglaló és tanulási AI platform.</div>
    <div class="footer-links">
      <a href="#top">Főoldal</a>
      <a href="#modulok">Funkciók</a>
      <a href="#muvelet">Hogyan működik</a>
    </div>
  </div>
</footer>

<div class="confirm-overlay" id="confirm-overlay" hidden>
  <div class="confirm-modal" style="width:min(460px,100%);">
    <h3 class="confirm-title">Előzmények törlése</h3>
    <p class="confirm-text">Biztosan törölni szeretnéd ezt az előzményt?</p>
    <div class="confirm-actions">
      <button class="btn btn-light" id="confirm-cancel" type="button">Mégse</button>
      <button class="btn btn-dark" id="confirm-delete" type="button">Igen, törlés</button>
    </div>
  </div>
</div>

<div class="confirm-overlay" id="chat-confirm-overlay" hidden>
  <div class="confirm-modal" style="width:min(460px,100%);">
    <h3 class="confirm-title">Chat törlése</h3>
    <p class="confirm-text">Biztosan törölni szeretnéd a teljes chat beszélgetést?</p>
    <div class="confirm-actions">
      <button class="btn btn-light" id="chat-confirm-cancel" type="button">Mégse</button>
      <button class="btn btn-dark" id="chat-confirm-delete" type="button">Igen, törlés</button>
    </div>
  </div>
</div>

<div class="toast-stack" id="toast-stack"></div>

<div class="scroll-progress" aria-hidden="true">
  <div class="scroll-progress-bar" id="scroll-progress-bar"></div>
</div>

<script>
window.ASTRALUS_IS_LOGGED_IN = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
window.ASTRALUS_USER_ID = <?php echo $isLoggedIn ? (int) $userId : 'null'; ?>;
window.ASTRALUS_SERVER_CREDITS = <?php
  echo $isLoggedIn
    ? (int) ($user['credits'] ?? 0)
    : (int) ($_SESSION['guest_credits'] ?? 100);
?>;
</script>

<script src="script.js?v=<?php echo time(); ?>"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const customSelects = document.querySelectorAll(".custom-select");

  customSelects.forEach((select) => {
    const trigger = select.querySelector(".custom-select-trigger");
    const valueEl = select.querySelector(".custom-select-value");
    const hiddenInput = select.querySelector('input[type="hidden"]');
    const options = select.querySelectorAll(".custom-select-option");

    if (!trigger || !valueEl || !hiddenInput || !options.length) return;

    trigger.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const willOpen = !select.classList.contains("is-open");

      customSelects.forEach((otherSelect) => {
        otherSelect.classList.remove("is-open");
        otherSelect.closest(".field")?.classList.remove("open-select");
      });

      if (willOpen) {
        select.classList.add("is-open");
        select.closest(".field")?.classList.add("open-select");
      }
    });

    options.forEach((option) => {
      option.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();

        options.forEach((item) => item.classList.remove("is-selected"));
        option.classList.add("is-selected");

        const label = option.textContent.trim();
        const value = option.dataset.value || label;

        valueEl.textContent = label;
        hiddenInput.value = value;

        hiddenInput.dispatchEvent(new Event("input", { bubbles: true }));
        hiddenInput.dispatchEvent(new Event("change", { bubbles: true }));

        select.classList.remove("is-open");
        select.closest(".field")?.classList.remove("open-select");
      });
    });
  });

  document.addEventListener("click", function () {
    document.querySelectorAll(".custom-select").forEach((select) => {
      select.classList.remove("is-open");
      select.closest(".field")?.classList.remove("open-select");
    });
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      document.querySelectorAll(".custom-select").forEach((select) => {
        select.classList.remove("is-open");
        select.closest(".field")?.classList.remove("open-select");
      });
    }
  });
});
</script>

<script>
const newsQuery = document.getElementById("news-query");

function normalizeText(text) {
  return (text || "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "");
}

function runAstralusAnimation() {
  if (!newsQuery) return;

  newsQuery.classList.remove("astralus-active");
  newsQuery.classList.remove("astralus-shake");

  void newsQuery.offsetWidth;

  newsQuery.classList.add("astralus-active");
  newsQuery.classList.add("astralus-shake");

  setTimeout(() => {
    newsQuery.classList.remove("astralus-active");
    newsQuery.classList.remove("astralus-shake");
  }, 1400);
}

if (newsQuery) {
  newsQuery.addEventListener("input", function () {
    const value = normalizeText(newsQuery.value);

    if (value.includes("astralus")) {
      runAstralusAnimation();
    }
  });
}

document.addEventListener("DOMContentLoaded", () => {
  const logoutBtn = document.querySelector(".logout-btn");
  const logoutModal = document.getElementById("logout-confirm");
  const logoutYes = document.getElementById("logout-yes");
  const logoutNo = document.getElementById("logout-no");
  const logoutForm = document.getElementById("logout-form");

  if (logoutBtn && logoutModal && logoutYes && logoutNo && logoutForm) {
    logoutBtn.addEventListener("click", () => {
      logoutModal.removeAttribute("hidden");
    });

    logoutNo.addEventListener("click", () => {
      logoutModal.setAttribute("hidden", true);
    });

    logoutModal.addEventListener("click", (e) => {
      if (e.target === logoutModal) {
        logoutModal.setAttribute("hidden", true);
      }
    });

    logoutYes.addEventListener("click", () => {
      logoutForm.submit();
    });
  }
});
</script>

<div id="logout-confirm" class="confirm-overlay" hidden>
  <div class="confirm-modal">
    <h3 class="confirm-title">Biztosan ki szeretnél jelentkezni?</h3>
    <p class="confirm-text">A kijelentkezés után újra be kell majd lépned.</p>

    <div class="confirm-actions">
      <button id="logout-yes" class="confirm-btn is-danger">
        Igen, kijelentkezem
      </button>

      <button id="logout-no" class="confirm-cancel-btn">
        Mégsem
      </button>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const body = document.body;
  const drawer = document.getElementById("mobile-drawer");
  const overlay = document.getElementById("mobile-drawer-overlay");
  const openBtn = document.getElementById("mobile-menu-open");
  const closeBtn = document.getElementById("mobile-menu-close");
  const mobileThemeBtn = document.getElementById("mobile-theme-toggle");
  const desktopThemeBtn = document.getElementById("theme-toggle");
  const mobileLogoutBtn = document.getElementById("mobile-logout-btn");
  const logoutForm = document.getElementById("logout-form");

  if (!drawer || !overlay) return;

  let touchStartX = 0;
  let touchCurrentX = 0;
  let isDragging = false;

  function openDrawer() {
    overlay.hidden = false;
    drawer.hidden = false;

    requestAnimationFrame(() => {
      body.classList.add("mobile-menu-open");
      drawer.classList.add("is-open");
      overlay.classList.add("is-open");
      drawer.setAttribute("aria-hidden", "false");
    });
  }

  function closeDrawer() {
    drawer.classList.remove("is-open");
    overlay.classList.remove("is-open");
    body.classList.remove("mobile-menu-open");
    drawer.setAttribute("aria-hidden", "true");
    drawer.style.transform = "";

    const drawerViews = document.getElementById("mobile-drawer-views");
    const mainView = document.getElementById("mobile-drawer-view-main");
    const toolsView = document.getElementById("mobile-drawer-view-tools");

    if (drawerViews && mainView && toolsView) {
      drawerViews.classList.remove("is-tools-view");
      toolsView.classList.remove("is-active");
      mainView.classList.add("is-active");
    }

    setTimeout(() => {
      if (!drawer.classList.contains("is-open")) {
        overlay.hidden = true;
        drawer.hidden = true;
      }
    }, 400);
  }

  if (openBtn) {
    openBtn.addEventListener("click", function () {
      if (drawer.classList.contains("is-open")) {
        closeDrawer();
      } else {
        openDrawer();
      }
    });
  }

  if (closeBtn) {
    closeBtn.addEventListener("click", closeDrawer);
  }

  overlay.addEventListener("click", closeDrawer);

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      closeDrawer();
    }
  });

  drawer.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", closeDrawer);
  });

if (mobileThemeBtn) {
  mobileThemeBtn.addEventListener("click", function (e) {
    e.preventDefault();
    e.stopPropagation();
  });
}

  if (mobileLogoutBtn && logoutForm) {
    mobileLogoutBtn.addEventListener("click", function () {
      logoutForm.submit();
    });
  }

  drawer.addEventListener("touchstart", function (e) {
    touchStartX = e.touches[0].clientX;
    touchCurrentX = touchStartX;
    isDragging = true;
  }, { passive: true });

  drawer.addEventListener("touchmove", function (e) {
    if (!isDragging) return;

    touchCurrentX = e.touches[0].clientX;
    const delta = Math.max(0, touchCurrentX - touchStartX);

    drawer.style.transform = `translate3d(${delta}px, 0, 0)`;
  }, { passive: true });

  drawer.addEventListener("touchend", function () {
    if (!isDragging) return;

    const delta = touchCurrentX - touchStartX;
    isDragging = false;

    if (delta > 70) {
      closeDrawer();
    } else {
      drawer.style.transform = "";
    }
  });
});
</script>
</body>
</html>
