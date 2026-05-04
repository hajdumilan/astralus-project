<?php
session_start();

$error = $_SESSION['login_error'] ?? '';
$success = $_SESSION['login_success'] ?? ($_SESSION['register_success'] ?? '');
$oldEmail = $_SESSION['old_login_email'] ?? '';

unset(
    $_SESSION['login_error'],
    $_SESSION['login_success'],
    $_SESSION['register_success'],
    $_SESSION['old_login_email']
);

if (!empty($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (function_exists('autoLoginFromRememberCookie')) {
    autoLoginFromRememberCookie($pdo);
}

if (!empty($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BejelentkezĂ©s - Astralus Intelligence</title>

    <link rel="icon" href="data:image/svg+xml,
      <svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22>
        <text y=%22.9em%22 font-size=%2290%22>âś¦</text>
      </svg>">

    <style>
        /* ===== FIERCE (HEADLINES) ===== */
        @font-face {
            font-family: 'Fierce';
            src: url('/assets/fonts/fierce/Fierce-Black.woff2') format('woff2');
            font-weight: 900;
            font-style: normal;
            font-display: swap;
        }

        /* ===== MONIGUE (MAIN TEXT) ===== */
        @font-face {
            font-family: 'Monigue';
            src: url('/assets/fonts/monigue/Monigue-Regular.woff2') format('woff2');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        /* ===== FRANIE (UI TEXT) ===== */
        @font-face {
            font-family: 'Franie';
            src: url('/assets/fonts/franie/Franie-Regular.woff2') format('woff2');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        :root {
            --bg-1: #040711;
            --bg-2: #07111f;
            --bg-3: #081829;

            --surface: rgba(10, 15, 26, 0.72);
            --surface-strong: rgba(8, 12, 22, 0.86);
            --surface-soft: rgba(255, 255, 255, 0.06);

            --text: #f7f9ff;
            --text-soft: #dce4f4;
            --muted: #a6b1c8;

            --line: rgba(255, 255, 255, 0.08);
            --line-strong: rgba(255, 255, 255, 0.13);

            --brand-1: #7fe6ff;
            --brand-2: #52cfff;
            --brand-3: #8478ff;
            --brand-4: #d946ef;

            --success: #34d399;
            --danger: #ff7f7f;

            --radius-2xl: 46px;
            --radius-xl: 34px;
            --radius-lg: 24px;
            --radius-md: 18px;
            --radius-sm: 14px;

            --shadow-stage:
                0 42px 120px rgba(0, 0, 0, 0.42),
                0 14px 36px rgba(0, 0, 0, 0.18);

            --shadow-panel:
                0 32px 96px rgba(0, 0, 0, 0.36),
                0 10px 28px rgba(0, 0, 0, 0.16);

            --shadow-soft:
                0 18px 40px rgba(0, 0, 0, 0.18);

            --container: 1360px;
            --header-width: min(95vw, 1180px);
        }

        body.light-mode {
            --bg-1: #f5f7fc;
            --bg-2: #edf2fb;
            --bg-3: #e8eef9;

            --surface: rgba(255, 255, 255, 0.80);
            --surface-strong: rgba(255, 255, 255, 0.92);
            --surface-soft: rgba(15, 20, 30, 0.04);

            --text: #111827;
            --text-soft: #2b3850;
            --muted: #69788f;

            --line: rgba(15, 20, 30, 0.08);
            --line-strong: rgba(15, 20, 30, 0.12);

            --shadow-stage:
                0 28px 80px rgba(17, 24, 39, 0.10),
                0 8px 24px rgba(17, 24, 39, 0.05);

            --shadow-panel:
                0 22px 56px rgba(17, 24, 39, 0.08),
                0 8px 20px rgba(17, 24, 39, 0.04);

            --shadow-soft:
                0 14px 30px rgba(17, 24, 39, 0.06);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-width: 320px;
            font-family: 'Franie', system-ui, -apple-system, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 15% 18%, rgba(82, 207, 255, 0.12), transparent 22%),
                radial-gradient(circle at 82% 12%, rgba(217, 70, 239, 0.12), transparent 24%),
                radial-gradient(circle at 55% 92%, rgba(132, 120, 255, 0.12), transparent 28%),
                linear-gradient(180deg, var(--bg-1) 0%, var(--bg-2) 52%, var(--bg-3) 100%);
            background-attachment: fixed;
            line-height: 1.5;
            overflow-x: hidden;
            position: relative;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            transition: background 0.25s ease, color 0.25s ease;
            animation: pageCinemaBase 1s cubic-bezier(0.22, 1, 0.36, 1);
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            opacity: 0.10;
            background:
                linear-gradient(rgba(255,255,255,0.01), rgba(255,255,255,0.01)),
                linear-gradient(90deg, rgba(255,255,255,0.015) 1px, transparent 1px),
                linear-gradient(180deg, rgba(255,255,255,0.012) 1px, transparent 1px);
            background-size: 100% 100%, 88px 88px, 88px 88px;
            mask-image: linear-gradient(180deg, rgba(0,0,0,0.95), rgba(0,0,0,0.45) 78%, transparent);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        textarea,
        select {
            font: inherit;
            font-family: 'Franie', sans-serif !important;
        }

        button {
            cursor: pointer;
        }

        h1, h2, h3, p {
            margin: 0;
        }

        h1, h2, h3, h4, h5, h6,
        .hero-title,
        .hero-title-line,
        .auth-title,
        .brand,
        .credit-count {
            font-family: 'Fierce', sans-serif;
            letter-spacing: -0.04em;
        }

        .panel-subtitle,
        .field-label,
        .input,
        .textarea,
        .select,
        button,
        .tag,
        .hero-text {
            font-family: 'Franie', sans-serif;
            font-size: 13px;
        }

        .hero-text {
            font-size: 14px;
            opacity: 0.8;
            max-width: 680px;
            margin-left: auto;
            margin-right: auto;
        }

        .output-content {
            font-family: 'Franie', sans-serif;
            font-size: 13px;
            line-height: 1.6;
        }

        .container {
            width: min(100% - 34px, var(--container));
            margin-inline: auto;
            position: relative;
            z-index: 2;
        }

        .sky,
        .sky-2,
        .page-glow {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .sky { z-index: 0; }
        .sky-2 { z-index: 0; }
        .page-glow { z-index: 1; }

        .sky::before,
        .sky::after,
        .sky-2::before,
        .sky-2::after {
            content: "";
            position: absolute;
            inset: -20%;
            background-repeat: repeat;
            animation-iteration-count: infinite;
            animation-timing-function: linear;
        }

        .sky::before {
            background-image:
                radial-gradient(circle, rgba(255,255,255,0.72) 0 1px, transparent 1.8px),
                radial-gradient(circle, rgba(127,230,255,0.24) 0 1.2px, transparent 2px),
                radial-gradient(circle, rgba(132,120,255,0.22) 0 1.1px, transparent 1.9px);
            background-size: 200px 200px, 280px 280px, 340px 340px;
            background-position: 0 0, 70px 90px, 130px 50px;
            animation: driftA 42s linear infinite;
            opacity: 0.46;
        }

        .sky::after {
            background-image:
                radial-gradient(circle, rgba(217,70,239,0.18) 0 1.4px, transparent 2px),
                radial-gradient(circle, rgba(255,255,255,0.18) 0 1.2px, transparent 2px);
            background-size: 250px 250px, 340px 340px;
            background-position: 30px 60px, 120px 140px;
            animation: driftB 54s linear infinite;
            opacity: 0.24;
        }

        .sky-2::before {
            background:
                radial-gradient(circle at 20% 30%, rgba(0, 214, 255, 0.12), transparent 18%),
                radial-gradient(circle at 80% 20%, rgba(217, 70, 239, 0.10), transparent 18%),
                radial-gradient(circle at 50% 80%, rgba(132, 120, 255, 0.10), transparent 20%);
            filter: blur(120px);
            animation: auraMove 18s ease-in-out infinite;
            opacity: 0.70;
        }

        .sky-2::after {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.02), transparent 18%),
                linear-gradient(90deg, transparent 0%, rgba(127,230,255,0.03) 45%, transparent 100%);
            opacity: 0.28;
        }

        .page-glow::before,
        .page-glow::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            filter: blur(120px);
            animation: glowCinemaIn 1.1s ease forwards, auraMove 16s ease-in-out infinite;
        }

        .page-glow::before {
            width: 420px;
            height: 420px;
            background: radial-gradient(circle, rgba(82,207,255,0.16), transparent 70%);
            left: -120px;
            top: 80px;
        }

        .page-glow::after {
            width: 380px;
            height: 380px;
            background: radial-gradient(circle, rgba(217,70,239,0.12), transparent 70%);
            right: -80px;
            top: 220px;
            animation-delay: 0s, -8s;
        }

        /* ================= CINEMATIC INTRO ================= */

        .cinematic-intro {
            position: fixed;
            inset: 0;
            z-index: 9999;
            overflow: hidden;
            pointer-events: none;
            background:
                radial-gradient(circle at 50% 22%, rgba(217,70,239,0.18), transparent 28%),
                radial-gradient(circle at 50% 68%, rgba(82,207,255,0.10), transparent 36%),
                linear-gradient(180deg, #09031a 0%, #090f22 52%, #081523 100%);
            animation: introFadeOut 1.35s ease forwards 1.55s;
        }

        .cinematic-intro-vignette,
        .cinematic-intro-shine,
        .cinematic-intro-grid,
        .cinematic-intro-logo-wrap {
            position: absolute;
            inset: 0;
        }

        .cinematic-intro-grid {
            background:
                linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px),
                linear-gradient(180deg, rgba(255,255,255,0.020) 1px, transparent 1px);
            background-size: 84px 84px;
            opacity: 0.12;
            animation: introGridPulse 1.6s ease-in-out forwards;
        }

        .cinematic-intro-vignette {
            background:
                radial-gradient(circle at center, transparent 44%, rgba(0,0,0,0.18) 72%, rgba(0,0,0,0.42) 100%);
            animation: introVignette 1.8s ease forwards;
        }

        .cinematic-intro-shine {
            background:
                linear-gradient(
                    110deg,
                    rgba(255,255,255,0) 0%,
                    rgba(255,255,255,0) 40%,
                    rgba(255,255,255,0.16) 49%,
                    rgba(255,255,255,0.06) 54%,
                    rgba(255,255,255,0) 64%,
                    rgba(255,255,255,0) 100%
                );
            transform: translateX(-130%) skewX(-18deg);
            filter: blur(8px);
            mix-blend-mode: screen;
            opacity: 0.66;
            animation: introShinePass 1.05s cubic-bezier(0.22, 1, 0.36, 1) 0.22s forwards;
        }

        .cinematic-intro-logo-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cinematic-intro-logo {
            position: relative;
            width: min(18vw, 128px);
            aspect-ratio: 1 / 1;
            opacity: 0;
            transform: scale(0.84) translateY(10px);
            animation: introLogoReveal 0.82s cubic-bezier(0.22, 1, 0.36, 1) 0.18s forwards;
        }

        .cinematic-intro-logo::before {
            content: "";
            position: absolute;
            inset: -34px;
            border-radius: 50%;
            background:
                radial-gradient(circle at center, rgba(110, 170, 255, 0.16), transparent 42%),
                radial-gradient(circle at center, rgba(140, 120, 255, 0.08), transparent 64%);
            filter: blur(30px);
            opacity: 0;
            animation: introGlowBurst 0.82s ease-out 0.24s forwards;
        }

        .intro-energy-ring {
            position: absolute;
            inset: -26%;
            border-radius: 50%;
            border: 1px solid rgba(110,170,255,0.12);
            opacity: 0;
            transform: scale(0.82);
            animation: introRingExpandClean 1s cubic-bezier(0.22, 1, 0.36, 1) 0.18s forwards;
        }

        .intro-aura-ring {
            position: absolute;
            inset: -10%;
            border-radius: 50%;
            border: 1px solid rgba(110,170,255,0.08);
            opacity: 0;
            transform: scale(0.92);
            animation: introRingExpandSoftClean 1.15s cubic-bezier(0.22, 1, 0.36, 1) 0.24s forwards;
        }

        .intro-star-wrap {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            opacity: 0;
            transform: scale(0.88);
            animation: introStarRevealClean 0.9s cubic-bezier(0.22, 1, 0.36, 1) 0.18s forwards;
        }

        .intro-star-symbol {
            display: inline-block;
            font-family: 'Fierce', sans-serif;
            font-size: clamp(58px, 7vw, 84px);
            line-height: 1;
            font-weight: 800;
            color: #ffffff;
            text-rendering: geometricPrecision;
            -webkit-font-smoothing: antialiased;
            filter:
                drop-shadow(0 0 6px rgba(255,255,255,0.18))
                drop-shadow(0 0 14px rgba(110,170,255,0.14))
                drop-shadow(0 0 28px rgba(110,170,255,0.08));
            animation: introStarFloatClean 4.8s ease-in-out 1.2s infinite;
        }

        .intro-star-glow {
            position: absolute;
            inset: 18%;
            border-radius: 50%;
            background:
                radial-gradient(circle at center, rgba(255,255,255,0.14), transparent 60%);
            filter: blur(12px);
            opacity: 0;
            animation: introStarGlowClean 0.85s ease-out 0.26s forwards;
        }

        .intro-horizon-line {
            position: absolute;
            left: -46%;
            right: -46%;
            top: 50%;
            height: 2px;
            transform: translateY(-50%);
            background:
                linear-gradient(
                    90deg,
                    transparent 0%,
                    rgba(90, 180, 255, 0.00) 10%,
                    rgba(110, 185, 255, 0.58) 26%,
                    rgba(180, 225, 255, 0.92) 50%,
                    rgba(110, 185, 255, 0.58) 74%,
                    rgba(90, 180, 255, 0.00) 90%,
                    transparent 100%
                );
            box-shadow:
                0 0 12px rgba(110,185,255,0.20),
                0 0 28px rgba(110,185,255,0.08);
            opacity: 0;
            animation: introHorizonReveal 0.8s ease-out 0.42s forwards;
        }

        .intro-particles,
        .intro-particles::before,
        .intro-particles::after {
            position: absolute;
            inset: 0;
            content: "";
            pointer-events: none;
        }

        .intro-particles {
            background-image:
                radial-gradient(circle, rgba(255,255,255,0.80) 0 1px, transparent 1.9px),
                radial-gradient(circle, rgba(120,190,255,0.62) 0 1.2px, transparent 2px),
                radial-gradient(circle, rgba(255,230,180,0.34) 0 1.2px, transparent 2px);
            background-size: 120px 120px, 180px 180px, 220px 220px;
            background-position: 0 0, 40px 60px, 120px 30px;
            opacity: 0;
            animation: introParticlesPopClean 0.85s ease-out 0.42s forwards;
        }

        /* ================= HEADER ================= */

        .site-header {
            position: fixed;
            top: 14px;
            left: 50%;
            transform: translateX(-50%);
            width: var(--header-width);
            z-index: 120;
            padding: 10px 14px;
            border-radius: 30px;
            border: 1px solid var(--line);
            background:
                linear-gradient(180deg, rgba(8, 14, 27, 0.62), rgba(7, 12, 22, 0.72));
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%);
            box-shadow:
                0 22px 56px rgba(0, 0, 0, 0.26),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
            overflow: hidden;
            isolation: isolate;
            animation: headerIn 0.82s cubic-bezier(0.22, 1, 0.36, 1) 1.05s both;
        }

        body.light-mode .site-header {
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.84), rgba(247, 250, 255, 0.78));
        }

        .site-header::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            pointer-events: none;
            z-index: 0;
            background:
                linear-gradient(
                    115deg,
                    transparent 0%,
                    rgba(255, 255, 255, 0.02) 16%,
                    rgba(255, 255, 255, 0.10) 34%,
                    rgba(255, 255, 255, 0.02) 52%,
                    transparent 68%
                );
            background-size: 220% 100%;
            animation: headerLiquidSweep 9s ease-in-out infinite;
        }

        .site-header .header-liquid-layer,
        .site-header .header-pointer-glow,
        .site-header .header-border-shine {
            position: absolute;
            inset: 0;
            pointer-events: none;
            border-radius: inherit;
        }

        .site-header .header-liquid-layer::before {
            content: "";
            position: absolute;
            top: -35%;
            left: -20%;
            width: 54%;
            height: 180%;
            transform: rotate(10deg);
            background:
                linear-gradient(
                    90deg,
                    transparent 0%,
                    rgba(255, 255, 255, 0.02) 20%,
                    rgba(255, 255, 255, 0.13) 48%,
                    rgba(255, 255, 255, 0.02) 76%,
                    transparent 100%
                );
            filter: blur(8px);
            animation: headerGlassBand 11s cubic-bezier(0.22, 1, 0.36, 1) infinite;
        }

        .site-header .header-pointer-glow {
            background:
                radial-gradient(
                    220px circle at var(--mx, 50%) var(--my, 50%),
                    rgba(82, 207, 255, 0.18),
                    rgba(132, 120, 255, 0.10) 28%,
                    transparent 66%
                );
            opacity: 0.6;
            animation: headerPointerBreath 5.2s ease-in-out infinite;
        }

        .site-header .header-border-shine::before {
            content: "";
            position: absolute;
            top: 0;
            left: -35%;
            width: 28%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent 0%,
                rgba(255, 255, 255, 0.22) 50%,
                transparent 100%
            );
            transform: skewX(-20deg);
            filter: blur(10px);
            animation: headerBorderPass 7s ease-in-out infinite;
            opacity: 0.7;
        }

        .site-header .header-inner {
            position: relative;
            z-index: 2;
            min-height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .brand-mark {
            width: 50px;
            height: 50px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            background: linear-gradient(180deg, #ffffff 0%, #eef4ff 100%);
            color: #0f1420;
            box-shadow:
                0 14px 28px rgba(78, 110, 220, 0.16),
                0 0 0 1px rgba(255, 255, 255, 0.45);
            font-size: 16px;
            position: relative;
            overflow: hidden;
            flex: 0 0 auto;
        }

        .brand-text {
            white-space: nowrap;
            font-size: 1.08rem;
            color: var(--text);
        }

        .header-actions {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .ghost-btn,
        .theme-toggle,
        .outline-btn,
        .primary-btn,
        .password-toggle {
            appearance: none;
            -webkit-appearance: none;
            border: 1px solid var(--line);
            border-radius: 999px;
            min-height: 46px;
            padding: 0 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: -0.02em;
            transition:
                transform 0.22s ease,
                box-shadow 0.22s ease,
                background 0.22s ease,
                color 0.22s ease,
                border-color 0.22s ease,
                opacity 0.22s ease;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
        }

        .ghost-btn,
        .theme-toggle,
        .outline-btn,
        .password-toggle {
            background: rgba(255, 255, 255, 0.07);
            color: var(--text);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.05),
                0 10px 22px rgba(0, 0, 0, 0.10);
        }

        .ghost-btn:hover,
        .theme-toggle:hover,
        .outline-btn:hover,
        .primary-btn:hover,
        .password-toggle:hover {
            transform: translateY(-1px);
        }

        .outline-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  margin-bottom: 28px; /* â†“ eltĂˇvolĂ­tja a kĂˇrtyĂˇtĂłl */
  margin-top: -8px;    /* â†‘ feljebb hĂşzza */

  position: relative;
  z-index: 2;
}

        .theme-toggle {
            width: 46px;
            min-width: 46px;
            padding: 0;
            font-size: 18px;
        }

        .primary-btn {
            width: 100%;
            min-height: 58px;
            color: #ffffff;
            background:
                linear-gradient(135deg, var(--brand-1) 0%, var(--brand-2) 48%, var(--brand-3) 100%);
            border: 1px solid rgba(164,184,255,0.34);
            box-shadow:
                0 16px 34px rgba(46, 164, 255, 0.18),
                0 0 24px rgba(82, 207, 255, 0.18),
                inset 0 1px 0 rgba(255,255,255,0.16);
        }

        /* ================= PAGE ================= */

        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 118px 0 42px;
            position: relative;
            z-index: 2;
        }

        .login-stage {
            position: relative;
            border-radius: var(--radius-2xl);
            border: 1px solid rgba(255,255,255,0.04);
            background:
                linear-gradient(180deg, rgba(7, 13, 25, 0.28), rgba(5, 10, 20, 0.16));
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: var(--shadow-stage);
            padding: 24px;
            overflow: hidden;
            opacity: 0;
            transform: translateY(18px) scale(0.992);
            animation: pageReveal 0.82s cubic-bezier(0.22, 1, 0.36, 1) 1.12s forwards;
        }

        .login-layout {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.04fr) minmax(390px, 0.82fr);
            gap: 22px;
            align-items: stretch;
        }

        .hero-shell {
            position: relative;
            min-height: 650px;
            border-radius: var(--radius-xl);
            overflow: hidden;
            border: 1px solid var(--line);
            background:
                radial-gradient(circle at 78% 14%, rgba(82,207,255,0.12), transparent 18%),
                radial-gradient(circle at 18% 28%, rgba(217,70,239,0.12), transparent 20%),
                linear-gradient(180deg, rgba(7,12,22,0.78) 0%, rgba(5,9,18,0.94) 100%);
            box-shadow: var(--shadow-panel);
            opacity: 0;
            transform: translateY(20px);
            filter: blur(4px);
            animation: heroReveal 0.76s cubic-bezier(0.22, 1, 0.36, 1) 1.18s forwards;
        }

        .hero-shell::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.035), transparent 24%),
                linear-gradient(90deg, rgba(255,255,255,0.016), transparent 30%, rgba(255,255,255,0.008) 70%, transparent);
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 40px 38px;
        }

        .hero-top {
            max-width: 640px;
            margin-left: auto;
            margin-right: auto;
            text-align: center;
        }

        .panel-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 38px;
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.06);
            color: var(--text-soft);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.01em;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        .hero-badge {
            margin-bottom: 22px;
        }

        .hero-title {
            margin: 0;
            margin-left: auto;
            margin-right: auto;
            max-width: min(1200px, 100%);
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: clamp(3.2rem, 5vw, 5.1rem);
            font-family: 'Fierce', sans-serif;
            line-height: 0.92;
            letter-spacing: -0.06em;
            text-align: center;
            font-weight: 900;
            color: #ffffff;
            text-wrap: balance;
            text-shadow:
                0 12px 34px rgba(0,0,0,0.24),
                0 0 18px rgba(82,207,255,0.06);
            opacity: 0;
            transform: none !important;
            animation: heroTitleReveal 0.64s cubic-bezier(0.22, 1, 0.36, 1) 1.30s forwards;
        }

        .hero-title-line,
        .hero-title-line.primary,
        .hero-title-line.secondary,
        .hero-title-line.tertiary {
            display: block;
            width: 100%;
            margin-left: auto;
            margin-right: auto;
            position: relative;
            left: 0;
            text-align: center;
            transform: none !important;
        }

        .hero-title .accent {
            background: linear-gradient(180deg, #dffbff 0%, #86eaff 54%, #8b7fff 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .hero-text {
            margin-top: 24px;
            max-width: 680px;
            margin-left: auto;
            margin-right: auto;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.78;
            opacity: 0;
            transform: translateY(10px);
            animation: heroTextReveal 0.54s cubic-bezier(0.22, 1, 0.36, 1) 1.40s forwards;
        }

        .hero-actions {
            margin-top: 28px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            opacity: 0;
            transform: translateY(10px);
            animation: textReveal 0.54s cubic-bezier(0.22, 1, 0.36, 1) 1.48s forwards;
        }

        .hero-bottom {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .stat-card {
            min-height: 116px;
            border-radius: 22px;
            padding: 16px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.045);
            box-shadow: var(--shadow-soft);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            transition: transform 0.22s ease;
            opacity: 0;
            transform: translateY(12px);
        }

        .hero-bottom .stat-card:nth-child(1) { animation: statReveal 0.46s ease 1.54s forwards; }
        .hero-bottom .stat-card:nth-child(2) { animation: statReveal 0.46s ease 1.62s forwards; }
        .hero-bottom .stat-card:nth-child(3) { animation: statReveal 0.46s ease 1.70s forwards; }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-value {
            display: block;
            font-size: 1.5rem;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -0.05em;
            color: #ffffff;
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.45;
        }

        .auth-shell {
            position: relative;
            min-height: 650px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-card {
            position: relative;
            width: 100%;
            max-width: 430px;
            border-radius: 34px;
            border: 1px solid var(--line-strong);
            background:
                linear-gradient(180deg, rgba(10,16,29,0.82) 0%, rgba(8,13,24,0.94) 100%);
            box-shadow:
                0 28px 80px rgba(0,0,0,0.32),
                0 0 0 1px rgba(255,255,255,0.03),
                inset 0 1px 0 rgba(255,255,255,0.05);
            backdrop-filter: blur(28px) saturate(180%);
            -webkit-backdrop-filter: blur(28px) saturate(180%);
            overflow: hidden;
            opacity: 0;
            transform: translateY(20px);
            filter: blur(5px);
            animation: cardReveal 0.78s cubic-bezier(0.22, 1, 0.36, 1) 1.24s forwards;
        }

        .auth-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 86% 12%, rgba(82,207,255,0.10), transparent 18%),
                linear-gradient(180deg, rgba(255,255,255,0.04), transparent 22%);
            pointer-events: none;
        }

        .auth-card-inner {
            position: relative;
            z-index: 1;
            padding: 32px 28px 26px;
        }

        .auth-top,
        .login-form,
        .auth-footer {
            opacity: 0;
            transform: translateY(10px);
            animation: textReveal 0.54s cubic-bezier(0.22, 1, 0.36, 1) 1.36s forwards;
        }

        .auth-top {
            margin-bottom: 22px;
        }

        .auth-title {
            margin: 14px 0 10px;
            font-size: clamp(2rem, 3vw, 2.6rem);
            line-height: 0.94;
            letter-spacing: -0.055em;
            font-weight: 900;
            color: #ffffff;
        }

        .auth-subtitle {
            max-width: 34ch;
            color: var(--muted);
            font-size: 0.96rem;
            line-height: 1.6;
        }

        .form-message {
            margin: 0 0 14px;
            padding: 12px 14px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.05);
            font-size: 14px;
            line-height: 1.5;
        }

        .form-message.error {
            color: #ffb6b6;
            background: rgba(255, 84, 84, 0.10);
            border-color: rgba(255, 84, 84, 0.24);
        }

        .form-message.success {
            color: #baf7d2;
            background: rgba(28, 185, 102, 0.10);
            border-color: rgba(28, 185, 102, 0.24);
        }

        .login-form {
            display: grid;
            gap: 16px;
        }

        .field {
            display: grid;
            gap: 8px;
        }

        .field-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-soft);
            letter-spacing: -0.01em;
        }

        .input {
            width: 100%;
            min-height: 58px;
            padding: 14px 18px;
            border-radius: 18px;
            border: 1px solid var(--line);
            outline: none;
            color: var(--text);
            background:
                linear-gradient(180deg, rgba(8,13,24,0.90) 0%, rgba(8,12,22,0.98) 100%);
            box-shadow:
                inset 0 1px 0 rgba(255,255,255,0.04),
                0 10px 22px rgba(0,0,0,0.08);
            transition:
                border-color 0.22s ease,
                box-shadow 0.22s ease,
                background 0.22s ease,
                transform 0.22s ease;
        }

        .input::placeholder {
            color: rgba(170, 180, 200, 0.72);
        }

        .input:focus {
            transform: translateY(-1px);
            border-color: rgba(82, 207, 255, 0.44);
            box-shadow:
                0 0 0 4px rgba(82, 207, 255, 0.12),
                0 12px 26px rgba(31, 53, 120, 0.08),
                inset 0 1px 0 rgba(255,255,255,0.06);
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap .input {
            padding-right: 102px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 8px;
            transform: translateY(-50%);
            min-height: 40px;
            padding: 0 14px;
            font-size: 13px;
            z-index: 2;
        }

        .password-toggle:hover {
            transform: translateY(-50%) translateY(-1px);
        }

        .login-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 2px;
        }

        .login-check {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--muted);
        }

        .login-check input {
            width: 16px;
            height: 16px;
            accent-color: #52cfff;
            flex: 0 0 auto;
        }

        .login-link {
            font-size: 14px;
            font-weight: 800;
            color: var(--text-soft);
            transition: opacity 0.2s ease;
        }

        .login-link:hover {
            opacity: 0.72;
        }

        .login-link[aria-disabled="true"] {
            cursor: default;
            opacity: 0.64;
        }

        .login-link[aria-disabled="true"]:hover {
            opacity: 0.64;
        }

        .auth-footer {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
            text-align: center;
            color: var(--muted);
            font-size: 14px;
        }

        .auth-footer a {
            color: var(--text);
            font-weight: 800;
        }

        .auth-footer a:hover {
            opacity: 0.8;
        }

        * {
            scrollbar-width: thin;
            scrollbar-color: color-mix(in srgb, var(--muted) 35%, transparent) transparent;
        }

        *::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        *::-webkit-scrollbar-track {
            background: transparent;
        }

        *::-webkit-scrollbar-thumb {
            background: color-mix(in srgb, var(--muted) 35%, transparent);
            border-radius: 999px;
            border: 2px solid transparent;
            background-clip: content-box;
        }

        /* ================= ANIMATIONS ================= */

        @keyframes pageCinemaBase {
            0% { filter: saturate(0.92) brightness(0.96); }
            100% { filter: saturate(1) brightness(1); }
        }

        @keyframes driftA {
            0% { transform: translate3d(0, 0, 0); }
            100% { transform: translate3d(-120px, 80px, 0); }
        }

        @keyframes driftB {
            0% { transform: translate3d(0, 0, 0); }
            100% { transform: translate3d(140px, -90px, 0); }
        }

        @keyframes auraMove {
            0%, 100% { transform: translateY(0) translateX(0); }
            50% { transform: translateY(-18px) translateX(12px); }
        }

        @keyframes glowCinemaIn {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }

        @keyframes introLogoReveal {
            0% { opacity: 0; transform: scale(0.84) translateY(10px); filter: blur(10px); }
            100% { opacity: 1; transform: scale(1) translateY(0); filter: blur(0); }
        }

        @keyframes introGlowBurst {
            0% { opacity: 0; transform: scale(0.82); }
            100% { opacity: 1; transform: scale(1); }
        }

        @keyframes introRingExpandClean {
            0% {
                opacity: 0;
                transform: scale(0.82);
            }
            40% {
                opacity: 0.20;
            }
            100% {
                opacity: 0.10;
                transform: scale(1.08);
            }
        }

        @keyframes introRingExpandSoftClean {
            0% {
                opacity: 0;
                transform: scale(0.92);
            }
            45% {
                opacity: 0.14;
            }
            100% {
                opacity: 0.06;
                transform: scale(1.12);
            }
        }

        @keyframes introStarRevealClean {
            0% {
                opacity: 0;
                transform: scale(0.88);
                filter: blur(8px);
            }
            100% {
                opacity: 1;
                transform: scale(1);
                filter: blur(0);
            }
        }

        @keyframes introStarGlowClean {
            0% {
                opacity: 0;
                transform: scale(0.82);
            }
            100% {
                opacity: 1;
                transform: scale(1.02);
            }
        }

        @keyframes introStarFloatClean {
            0%, 100% {
                transform: translateY(0px) scale(1);
            }
            50% {
                transform: translateY(-2px) scale(1.01);
            }
        }

        @keyframes introHorizonReveal {
            0% {
                opacity: 0;
                transform: translateY(-50%) scaleX(0.78);
            }
            100% {
                opacity: 1;
                transform: translateY(-50%) scaleX(1);
            }
        }

        @keyframes introParticlesPopClean {
            0% {
                opacity: 0;
                transform: scale(0.96);
            }
            40% {
                opacity: 0.62;
            }
            100% {
                opacity: 0.22;
                transform: scale(1.02);
            }
        }

        @keyframes introGridPulse {
            0% { opacity: 0.05; transform: scale(1.02); }
            50% { opacity: 0.14; }
            100% { opacity: 0.10; transform: scale(1); }
        }

        @keyframes introShinePass {
            0% { transform: translateX(-130%) skewX(-18deg); opacity: 0; }
            18% { opacity: 0.64; }
            100% { transform: translateX(130%) skewX(-18deg); opacity: 0; }
        }

        @keyframes introVignette {
            0% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        @keyframes introFadeOut {
            0% { opacity: 1; visibility: visible; }
            100% { opacity: 0; visibility: hidden; }
        }

        @keyframes headerIn {
            from { opacity: 0; transform: translateX(-50%) translateY(-18px) scale(0.985); }
            to { opacity: 1; transform: translateX(-50%) translateY(0) scale(1); }
        }

        @keyframes headerLiquidSweep {
            0% { background-position: 0% 50%; opacity: 0.72; }
            25% { background-position: 42% 50%; opacity: 0.96; }
            50% { background-position: 100% 50%; opacity: 0.78; }
            75% { background-position: 58% 50%; opacity: 0.92; }
            100% { background-position: 0% 50%; opacity: 0.72; }
        }

        @keyframes headerGlassBand {
            0% { left: -28%; opacity: 0; }
            10% { opacity: 0.55; }
            45% { opacity: 0.88; }
            70% { opacity: 0.36; }
            100% { left: 118%; opacity: 0; }
        }

        @keyframes headerBorderPass {
            0% { left: -35%; opacity: 0; }
            18% { opacity: 0.45; }
            45% { opacity: 0.85; }
            70% { opacity: 0.35; }
            100% { left: 120%; opacity: 0; }
        }

        @keyframes headerPointerBreath {
            0%, 100% { opacity: 0.40; transform: scale(1); }
            50% { opacity: 0.62; transform: scale(1.04); }
        }

        @keyframes pageReveal {
            0% { opacity: 0; transform: translateY(18px) scale(0.992); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes heroReveal {
            0% { opacity: 0; transform: translateY(20px); filter: blur(4px); }
            100% { opacity: 1; transform: translateY(0); filter: blur(0); }
        }

        @keyframes cardReveal {
            0% { opacity: 0; transform: translateY(20px); filter: blur(5px); }
            100% { opacity: 1; transform: translateY(0); filter: blur(0); }
        }

        @keyframes textReveal {
            0% { opacity: 0; transform: translateY(10px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        @keyframes heroTitleReveal {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }

        @keyframes heroTextReveal {
            0% { opacity: 0; transform: translateY(10px); }
            100% { opacity: 0.8; transform: translateY(0); }
        }

        @keyframes statReveal {
            0% { opacity: 0; transform: translateY(12px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        /* ================= RESPONSIVE ================= */

        @media (max-width: 1120px) {
            .login-layout {
                grid-template-columns: 1fr;
            }

            .hero-shell,
            .auth-shell {
                min-height: auto;
            }

            .hero-content {
                gap: 28px;
            }

            .auth-card {
                max-width: none;
            }
        }

        

        


        @media (max-width: 760px) {
            .site-header {
                top: 8px;
                width: calc(100% - 14px);
                padding: 10px 12px;
                border-radius: 24px;
            }

            .container {
                width: min(100% - 18px, var(--container));
            }

            .brand-mark {
                width: 44px;
                height: 44px;
                border-radius: 16px;
            }

            .brand-text {
                font-size: 1rem;
                line-height: 1;
                white-space: nowrap;
            }

            .header-actions .ghost-btn {
                display: none;
            }

            .login-page {
                padding: 92px 0 24px;
                align-items: flex-start;
            }

            .login-stage {
                padding: 10px;
                border-radius: 26px;
            }

            .login-layout {
                display: grid;
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .auth-shell {
                order: 1;
            }

            .hero-shell {
                order: 2;
            }

            .hero-shell,
            .auth-card {
                border-radius: 24px;
            }

            .hero-content {
                padding: 20px 16px;
                gap: 18px;
            }

            .auth-card-inner {
                padding: 22px 16px 18px;
            }

            .hero-title {
                font-size: clamp(2.15rem, 10vw, 3.2rem);
                line-height: 0.92;
                max-width: min(1200px, 100%);
            }

            .hero-text {
                font-size: 14px;
                line-height: 1.55;
                max-width: 680px;
            }

            .hero-bottom {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 16px 14px;
                min-height: auto;
                border-radius: 20px;
            }

            .stat-value {
                font-size: 1.6rem;
                line-height: 0.95;
            }

            .stat-label {
                font-size: 0.92rem;
                line-height: 1.45;
            }

            .login-row,
            .hero-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .outline-btn,
            .primary-btn {
                width: 100%;
                min-height: 54px;
            }

            .field {
                margin-bottom: 14px;
            }

            .input {
                min-height: 56px;
                border-radius: 18px;
                font-size: 13px;
            }

            .password-wrap .input {
                padding-right: 16px;
            }

            .password-toggle {
                position: static;
                transform: none;
                width: 100%;
                min-height: 50px;
                margin-top: 10px;
                border-radius: 999px;
            }

            .password-toggle:hover {
                transform: translateY(-1px);
            }

            .auth-footer {
                margin-top: 18px;
            }

            .cinematic-intro-logo {
                width: min(28vw, 112px);
            }

            .intro-star-symbol {
                font-size: clamp(50px, 12vw, 70px);
            }
        }

        @media (max-width: 520px) {
            .site-header {
                width: calc(100% - 10px);
                padding: 9px 10px;
                border-radius: 22px;
            }

            .brand-mark {
                width: 42px;
                height: 42px;
                border-radius: 15px;
            }

            .brand-text {
                font-size: 0.95rem;
            }

            .login-page {
                padding-top: 88px;
            }

            .login-stage {
                padding: 8px;
                border-radius: 22px;
            }

            .hero-shell,
            .auth-card {
                border-radius: 22px;
            }

            .hero-content,
            .auth-card-inner {
                padding: 18px 14px;
            }

            .hero-title {
                font-size: clamp(1.95rem, 10.2vw, 2.7rem);
                line-height: 0.93;
                max-width: min(1200px, 100%);
            }

            .hero-text {
                font-size: 14px;
                line-height: 1.52;
            }

            .auth-subtitle {
                font-size: 0.94rem;
                line-height: 1.52;
            }

            .stat-card {
                padding: 14px 12px;
                border-radius: 18px;
            }

            .stat-value {
                font-size: 1.45rem;
            }

            .stat-label {
                font-size: 0.88rem;
            }

            .input {
                min-height: 54px;
                font-size: 13px;
                border-radius: 17px;
            }

            .password-toggle {
                min-height: 48px;
            }

            .primary-btn,
            .outline-btn {
                min-height: 52px;
                font-size: 0.95rem;
            }

            .cinematic-intro-logo {
                width: min(34vw, 98px);
            }

            .intro-star-symbol {
                font-size: clamp(44px, 13vw, 60px);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .cinematic-intro,
            .site-header,
            .login-stage,
            .hero-shell,
            .auth-card,
            .hero-title,
            .hero-text,
            .hero-actions,
            .stat-card,
            .auth-top,
            .login-form,
            .auth-footer,
            .page-glow::before,
            .page-glow::after,
            .intro-star-symbol {
                animation: none !important;
                opacity: 1 !important;
                transform: none !important;
                filter: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="sky"></div>
    <div class="sky-2"></div>
    <div class="page-glow"></div>

    <div class="cinematic-intro" aria-hidden="true">
        <div class="cinematic-intro-grid"></div>
        <div class="cinematic-intro-shine"></div>
        <div class="cinematic-intro-vignette"></div>

        <div class="cinematic-intro-logo-wrap">
            <div class="cinematic-intro-logo">
                <div class="intro-energy-ring"></div>
                <div class="intro-aura-ring"></div>
                <div class="intro-star-glow"></div>
                <div class="intro-horizon-line"></div>

                <div class="intro-star-wrap">
                    <span class="intro-star-symbol" aria-hidden="true">âś¦</span>
                </div>

                <div class="intro-particles"></div>
            </div>
        </div>
    </div>

    <header class="site-header" id="site-header">
        <div class="header-liquid-layer"></div>
        <div class="header-pointer-glow"></div>
        <div class="header-border-shine"></div>

        <div class="header-inner">
            <a href="/index.php" class="brand">
                <span class="brand-mark">âś¦</span>
                <span class="brand-text">Astralus Intelligence</span>
            </a>

            <div class="header-actions">
                <a href="/index.php" class="ghost-btn">FĹ‘oldal</a>
                <button type="button" class="theme-toggle" id="theme-toggle" aria-label="TĂ©ma vĂˇltĂˇsa">â€</button>
            </div>
        </div>
    </header>

    <main class="login-page">
        <div class="container">
            <section class="login-stage">
                <div class="login-layout">
                    <section class="hero-shell">
                        <div class="hero-content">
                            <div class="hero-top">
                                <div class="panel-badge hero-badge">Astralus access</div>

                                <h1 class="hero-title">
                                    <span class="accent">LĂ©pj be</span><br>
                                    Ă©s folytasd<br>
                                    ott,<br>
                                    ahol<br>
                                    abbahagytad.
                                </h1>

                                <p class="hero-text">
                                    EgysĂ©ges, professzionĂˇlis belĂ©pĂ©si Ă©lmĂ©ny az Astralus vizuĂˇlis Ă©s funkcionĂˇlis alapelvei mentĂ©n.
                                </p>

                                <div class="hero-actions">
                                    <a href="/index.php" class="outline-btn">Vissza a fĹ‘oldalra</a>
                                </div>
                            </div>

                            <div class="hero-bottom">
                                <div class="stat-card">
                                    <span class="stat-value">Kifinomult belĂ©pĂ©s</span>
                                    <span class="stat-label">NĂ©hĂˇny mezĹ‘ kitĂ¶ltĂ©se Ă©s kĂ©sz.</span>
                                </div>

                                <div class="stat-card">
                                    <span class="stat-value">EgysĂ©ges rendszer</span>
                                    <span class="stat-label">EgysĂ©ges brand Ă©s rendszer Ă©lmĂ©ny.</span>
                                </div>

                                <div class="stat-card">
                                    <span class="stat-value">PrecĂ­z megjelenĂ©s</span>
                                    <span class="stat-label">Letisztult rĂ©szletek Ă©s kiegyensĂşlyozott vizuĂˇlis Ă©lmĂ©ny.</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="auth-shell">
                        <div class="auth-card">
                            <div class="auth-card-inner">
                                <div class="auth-top">
                                    <div class="panel-badge">BiztonsĂˇgos hozzĂˇfĂ©rĂ©s</div>
                                    <h2 class="auth-title">BejelentkezĂ©s</h2>
                                    <p class="auth-subtitle">
                                        Add meg az e-mail cĂ­medet Ă©s a jelszavadat a folytatĂˇshoz.
                                    </p>
                                </div>

                                <?php if (!empty($success)): ?>
                                    <div class="form-message success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>

                                <?php if (!empty($error)): ?>
                                    <div class="form-message error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>

                                <form action="/login_php/login-process.php" method="POST" class="login-form">
                                    <div class="field">
                                        <label for="email" class="field-label">E-mail cĂ­m</label>
                                        <input
                                            type="email"
                                            name="email"
                                            id="email"
                                            class="input"
                                            placeholder="pelda@email.hu"
                                            required
                                            autocomplete="email"
                                            value="<?php echo htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                    </div>

                                    <div class="field">
                                        <label for="password" class="field-label">JelszĂł</label>
                                        <div class="password-wrap">
                                            <input
                                                type="password"
                                                name="password"
                                                id="password"
                                                class="input"
                                                placeholder="ĂŤrd be a jelszavad"
                                                required
                                                autocomplete="current-password"
                                            >
                                            <button type="button" class="password-toggle" id="toggle-login-password">Mutat</button>
                                        </div>
                                    </div>

                                    <div class="login-row">
                                        <label class="login-check">
                                            <input type="checkbox" name="remember_me" value="1">
                                            <span>Maradjak bejelentkezve</span>
                                        </label>

                                        <span
                                            class="login-link"
                                            aria-disabled="true"
                                            title="A jelsz&oacute;-vissza&aacute;ll&iacute;t&aacute;s jelenleg nem &eacute;rhet&#337; el."
                                        >Elfelejtett jelsz&oacute;</span>
                                    </div>

                                    <button type="submit" class="primary-btn">BejelentkezĂ©s</button>
                                </form>

                                <div class="auth-footer">
                                    Nincs mĂ©g fiĂłkod? <a href="/register_php/register.php">RegisztrĂˇciĂł</a>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </section>
        </div>
    </main>

    <script>
        (function () {
            const body = document.body;
            const themeToggle = document.getElementById('theme-toggle');
            const savedTheme = localStorage.getItem('astralus-theme');

            if (savedTheme === 'light') {
                body.classList.add('light-mode');
                if (themeToggle) themeToggle.textContent = 'âľ';
            } else {
                body.classList.remove('light-mode');
                if (themeToggle) themeToggle.textContent = 'â€';
            }

            if (themeToggle) {
                themeToggle.addEventListener('click', function () {
                    const isLight = body.classList.toggle('light-mode');

                    if (isLight) {
                        localStorage.setItem('astralus-theme', 'light');
                        themeToggle.textContent = 'Dark';
                    } else {
                        localStorage.setItem('astralus-theme', 'dark');
                        themeToggle.textContent = 'Light';
                    }
                });
            }
        })();

        (function () {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.getElementById('toggle-login-password');

            if (!passwordInput || !toggleBtn) return;

            toggleBtn.addEventListener('click', function () {
                const isHidden = passwordInput.type === 'password';
                passwordInput.type = isHidden ? 'text' : 'password';
                toggleBtn.textContent = isHidden ? 'Rejt' : 'Mutat';
            });
        })();

        (function () {
            const header = document.getElementById('site-header');
            if (!header) return;

            function updatePointer(x, y) {
                const rect = header.getBoundingClientRect();
                const px = ((x - rect.left) / rect.width) * 100;
                const py = ((y - rect.top) / rect.height) * 100;

                header.style.setProperty('--mx', `${Math.max(0, Math.min(100, px))}%`);
                header.style.setProperty('--my', `${Math.max(0, Math.min(100, py))}%`);
            }

            header.addEventListener('mousemove', (e) => {
                updatePointer(e.clientX, e.clientY);
            });

            header.addEventListener('mouseleave', () => {
                header.style.setProperty('--mx', '50%');
                header.style.setProperty('--my', '50%');
            });
        })();

        (function () {
            window.addEventListener('load', function () {
                const intro = document.querySelector('.cinematic-intro');
                if (intro) {
                    setTimeout(() => {
                        intro.remove();
                    }, 3200);
                }
            });
        })();
    </script>
</body>
</html>
