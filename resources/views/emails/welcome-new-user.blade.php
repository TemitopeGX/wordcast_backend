<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
  <title>Welcome to WordCast Live — Your account is ready</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #0B0B0F; color: #ffffff; -webkit-font-smoothing: antialiased; }
    .wrapper { background: #0B0B0F; padding: 40px 16px; }
    .container { max-width: 580px; margin: 0 auto; background: #111117; border-radius: 16px; border: 1px solid rgba(255,255,255,0.07); overflow: hidden; }
    .header { background: linear-gradient(135deg, #0d2800 0%, #111117 65%); padding: 40px 40px 32px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.06); position: relative; }
    .header::before { content: ''; position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 300px; height: 2px; background: linear-gradient(90deg, transparent, #22c55e, transparent); }
    .logo-text { font-size: 16px; font-weight: 800; color: #ffffff; letter-spacing: -0.3px; }
    .welcome-badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.25); border-radius: 100px; font-size: 12px; font-weight: 700; color: #4ade80; padding: 6px 16px; margin-top: 20px; }
    .body { padding: 40px; }
    h1 { font-size: 26px; font-weight: 900; color: #ffffff; line-height: 1.2; margin-bottom: 12px; letter-spacing: -0.5px; }
    .sub { font-size: 15px; color: rgba(255,255,255,0.6); line-height: 1.7; margin-bottom: 32px; }
    .section-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: rgba(255,255,255,0.3); margin-bottom: 14px; }
    .divider { height: 1px; background: rgba(255,255,255,0.06); margin: 28px 0; }
    /* License key block */
    .license-block { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 24px; margin-bottom: 28px; }
    .license-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.12em; color: rgba(255,255,255,0.35); margin-bottom: 10px; }
    .license-key { font-family: 'Courier New', 'Lucida Console', monospace; font-size: 20px; font-weight: 800; color: #FF8533; letter-spacing: 0.12em; word-break: break-all; margin-bottom: 8px; background: rgba(255,102,0,0.08); border: 1px solid rgba(255,102,0,0.2); border-radius: 8px; padding: 12px 16px; display: block; }
    .license-note { font-size: 12px; color: rgba(255,255,255,0.4); line-height: 1.5; }
    /* Action buttons */
    .btn-primary { display: block; background: #FF6600; color: #ffffff !important; text-align: center; text-decoration: none; font-size: 15px; font-weight: 800; padding: 16px 24px; border-radius: 10px; margin-bottom: 10px; }
    .btn-secondary { display: block; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.8) !important; text-align: center; text-decoration: none; font-size: 14px; font-weight: 700; padding: 14px 24px; border-radius: 10px; margin-bottom: 10px; }
    /* Activate steps */
    .activate-steps { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 20px; margin-bottom: 24px; }
    .step-row { display: flex; align-items: flex-start; gap: 12px; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 13.5px; color: rgba(255,255,255,0.65); line-height: 1.5; }
    .step-row:last-child { border-bottom: none; }
    .step-n { font-size: 11px; font-weight: 800; color: #FF6600; background: rgba(255,102,0,0.12); border-radius: 50%; width: 22px; height: 22px; min-width: 22px; display: flex; align-items: center; justify-content: center; margin-top: 1px; }
    /* Beta note */
    .beta-note { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; padding: 16px; font-size: 13px; color: rgba(255,255,255,0.45); line-height: 1.7; margin-bottom: 24px; }
    .beta-note strong { color: rgba(255,255,255,0.65); }
    .sign-off { font-size: 14px; color: rgba(255,255,255,0.5); line-height: 1.7; }
    .sign-off strong { color: rgba(255,255,255,0.8); }
    .footer { padding: 24px 40px; border-top: 1px solid rgba(255,255,255,0.05); text-align: center; }
    .footer p { font-size: 11px; color: rgba(255,255,255,0.25); line-height: 1.6; }
    .footer a { color: rgba(255,102,0,0.7); text-decoration: none; }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="container">

    <!-- Header -->
    <div class="header">
      <span class="logo-text">WordCast <span style="color:#FF6600;">Live</span></span>
      <div class="welcome-badge">✓ Account Activated</div>
    </div>

    <!-- Body -->
    <div class="body">

      <h1>Welcome aboard, {{ $user->name }}. 🎉</h1>

      <p class="sub">
        Your WordCast Live beta account is all set. Below is everything you need to get started —
        your license key, download link, and instructions to connect the app.
      </p>

      <!-- License Key -->
      <p class="section-label">Your beta license key</p>
      <div class="license-block">
        <div class="license-label">License Key — Keep this safe</div>
        <code class="license-key">{{ $licenseKey }}</code>
        <p class="license-note">
          This key grants you Pro plan access for 3 months. Use it to activate the desktop app
          under <strong style="color:rgba(255,255,255,0.6);">Settings → Account → Activate License</strong>.
          Do not share it.
        </p>
      </div>

      <div class="divider"></div>

      <!-- Download & Dashboard CTAs -->
      <p class="section-label">Get started now</p>
      <a href="{{ $downloadUrl }}" class="btn-primary">Download WordCast Live for Windows →</a>
      <a href="{{ $dashboardUrl }}" class="btn-secondary">Open Your Dashboard →</a>

      <div class="divider"></div>

      <!-- Activate steps -->
      <p class="section-label">How to activate the desktop app</p>
      <div class="activate-steps">
        <div class="step-row"><span class="step-n">1</span>Download and install WordCast Live using the link above.</div>
        <div class="step-row"><span class="step-n">2</span>Open the app and click <strong style="color:rgba(255,255,255,0.8);">Settings</strong> in the top bar.</div>
        <div class="step-row"><span class="step-n">3</span>Navigate to <strong style="color:rgba(255,255,255,0.8);">Account</strong> and click <strong style="color:rgba(255,255,255,0.8);">Activate License</strong>.</div>
        <div class="step-row"><span class="step-n">4</span>Paste your license key from above and click <strong style="color:rgba(255,255,255,0.8);">Activate</strong>.</div>
        <div class="step-row"><span class="step-n">5</span>Restart the app — Pro features are now unlocked.</div>
      </div>

      <!-- Links -->
      <div class="divider"></div>
      <p class="section-label">Useful links</p>
      <table style="width:100%;border-collapse:collapse;">
        <tr>
          <td style="padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
            <a href="{{ $dashboardUrl }}" style="color:#FF8533;font-size:13.5px;font-weight:600;text-decoration:none;">📊 Dashboard</a>
            <span style="font-size:12px;color:rgba(255,255,255,0.35);"> — Manage your license, devices, and billing</span>
          </td>
        </tr>
        <tr>
          <td style="padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
            <a href="{{ $downloadUrl }}" style="color:#FF8533;font-size:13.5px;font-weight:600;text-decoration:none;">⬇ Download App</a>
            <span style="font-size:12px;color:rgba(255,255,255,0.35);"> — Latest version of WordCast Live</span>
          </td>
        </tr>
        <tr>
          <td style="padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
            <a href="{{ $telegramUrl }}" style="color:#FF8533;font-size:13.5px;font-weight:600;text-decoration:none;">💬 Telegram Community</a>
            <span style="font-size:12px;color:rgba(255,255,255,0.35);"> — Share feedback and get support</span>
          </td>
        </tr>
        <tr>
          <td style="padding:10px 0;">
            <a href="mailto:support@wordcastlive.site" style="color:#FF8533;font-size:13.5px;font-weight:600;text-decoration:none;">🛟 Support</a>
            <span style="font-size:12px;color:rgba(255,255,255,0.35);"> — support@wordcastlive.site</span>
          </td>
        </tr>
      </table>

      <!-- Beta note -->
      <div class="divider"></div>
      <div class="beta-note">
        <strong>A note about the beta:</strong> You're one of the first people using WordCast Live.
        Some things may not be perfect yet. If you find a bug or something confusing,
        please let us know via Telegram or email — your feedback directly shapes what we build next.
        We genuinely appreciate every report.
      </div>

      <!-- Sign off -->
      <div class="sign-off">
        <p>Thank you for being part of this. We're building something special together.</p>
        <br>
        <p>With gratitude,<br><strong>The WordCast Live Team</strong></p>
      </div>

    </div>

    <!-- Footer -->
    <div class="footer">
      <p>
        This email was sent to <strong style="color:rgba(255,255,255,0.4);">{{ $user->email }}</strong>
        because you created a WordCast Live beta account.
      </p>
      <p style="margin-top:8px;">© {{ date('Y') }} WordCast Live. All rights reserved.</p>
    </div>

  </div>
</div>
</body>
</html>
