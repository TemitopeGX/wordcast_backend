<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
  <title>Your WordCast Live beta access is ready</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #0B0B0F; color: #ffffff; -webkit-font-smoothing: antialiased; }
    .wrapper { background: #0B0B0F; padding: 40px 16px; }
    .container { max-width: 580px; margin: 0 auto; background: #111117; border-radius: 16px; border: 1px solid rgba(255,255,255,0.07); overflow: hidden; }
    .header { background: linear-gradient(135deg, #1f0800 0%, #111117 65%); padding: 40px 40px 32px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.06); position: relative; }
    .header::before { content: ''; position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 300px; height: 2px; background: linear-gradient(90deg, transparent, #FF6600, transparent); }
    .logo-text { font-size: 16px; font-weight: 800; color: #ffffff; letter-spacing: -0.3px; }
    .hero-badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,102,0,0.15); border: 1px solid rgba(255,102,0,0.3); border-radius: 100px; font-size: 12px; font-weight: 700; color: #FF8533; padding: 6px 16px; margin-top: 20px; }
    .hero-badge::before { content: '🎉'; }
    .body { padding: 40px; }
    h1 { font-size: 26px; font-weight: 900; color: #ffffff; line-height: 1.2; margin-bottom: 12px; letter-spacing: -0.5px; }
    .sub { font-size: 15px; color: rgba(255,255,255,0.6); line-height: 1.7; margin-bottom: 32px; }
    .cta-block { background: linear-gradient(135deg, rgba(255,102,0,0.08) 0%, rgba(255,102,0,0.03) 100%); border: 1px solid rgba(255,102,0,0.2); border-radius: 14px; padding: 28px; text-align: center; margin-bottom: 28px; }
    .cta-block p { font-size: 13px; color: rgba(255,255,255,0.5); margin-bottom: 20px; line-height: 1.6; }
    .btn-main { display: inline-block; background: #FF6600; color: #ffffff !important; text-decoration: none; font-size: 15px; font-weight: 800; padding: 16px 36px; border-radius: 10px; letter-spacing: -0.2px; }
    .expiry-note { display: flex; align-items: center; gap: 8px; background: rgba(251,191,36,0.06); border: 1px solid rgba(251,191,36,0.2); border-radius: 8px; padding: 12px 16px; margin-top: 16px; font-size: 12.5px; color: rgba(251,191,36,0.85); line-height: 1.5; }
    .divider { height: 1px; background: rgba(255,255,255,0.06); margin: 28px 0; }
    .section-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; color: rgba(255,255,255,0.3); margin-bottom: 14px; }
    .perks { margin-bottom: 28px; }
    .perk { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 14px; color: rgba(255,255,255,0.75); }
    .perk:last-child { border-bottom: none; }
    .perk-check { color: #FF6600; font-size: 14px; font-weight: 900; flex-shrink: 0; }
    .steps { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 20px; margin-bottom: 24px; }
    .step-row { display: flex; align-items: center; gap: 12px; padding: 8px 0; font-size: 13.5px; color: rgba(255,255,255,0.65); border-bottom: 1px solid rgba(255,255,255,0.04); }
    .step-row:last-child { border-bottom: none; }
    .step-n { font-size: 11px; font-weight: 800; color: #FF6600; background: rgba(255,102,0,0.12); border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .warning-box { background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.2); border-radius: 10px; padding: 16px; font-size: 13px; color: rgba(239,100,100,0.9); line-height: 1.6; margin-bottom: 24px; }
    .warning-box strong { color: #f87171; }
    .sign-off { font-size: 14px; color: rgba(255,255,255,0.5); line-height: 1.7; }
    .sign-off strong { color: rgba(255,255,255,0.8); }
    .fallback-url { word-break: break-all; font-size: 12px; color: rgba(255,102,0,0.6); background: rgba(255,102,0,0.05); border: 1px solid rgba(255,102,0,0.12); border-radius: 6px; padding: 10px 14px; margin-top: 12px; font-family: 'Courier New', monospace; }
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
      <div>
        <span class="logo-text">WordCast <span style="color:#FF6600;">Live</span></span>
      </div>
      <div class="hero-badge">You've been approved!</div>
    </div>

    <!-- Body -->
    <div class="body">

      <h1>Your beta spot is ready, {{ $entry->firstName() }}.</h1>

      <p class="sub">
        We've reviewed your application and you're in. Click the button below to set your password
        and activate your WordCast Live beta account. The whole process takes less than 60 seconds.
      </p>

      <!-- Big CTA -->
      <div class="cta-block">
        <p>Your unique invite link is below. This link is tied to your email address
           and is valid for <strong style="color:rgba(255,255,255,0.8);">48 hours after you first click it</strong>.</p>
        <a href="{{ $setupUrl }}" class="btn-main">Activate My Beta Account →</a>

        <div class="expiry-note">
          ⏱ This link expires 48 hours after your first click. If it expires, contact
          <a href="mailto:support@wordcastlive.site" style="color:inherit;font-weight:700;">support@wordcastlive.site</a> for a new one.
        </div>
      </div>

      <!-- What's included -->
      <p class="section-label">Your beta access includes</p>
      <div class="perks">
        <div class="perk"><span class="perk-check">✓</span>Pro plan — free for 3 months, no card needed</div>
        <div class="perk"><span class="perk-check">✓</span>5 workstation license (connect up to 5 PCs)</div>
        <div class="perk"><span class="perk-check">✓</span>Full theme editor and lyric management system</div>
        <div class="perk"><span class="perk-check">✓</span>Real-time AI transcription via Deepgram</div>
        <div class="perk"><span class="perk-check">✓</span>AI Bible verse detection across 50+ translations</div>
        <div class="perk"><span class="perk-check">✓</span>Unique beta license key, emailed after setup</div>
      </div>

      <div class="divider"></div>

      <!-- How to activate -->
      <p class="section-label">How to activate</p>
      <div class="steps">
        <div class="step-row"><span class="step-n">1</span>Click the "Activate My Beta Account" button above</div>
        <div class="step-row"><span class="step-n">2</span>Set your password (minimum 8 characters)</div>
        <div class="step-row"><span class="step-n">3</span>You're automatically logged in — check your inbox for your license key</div>
        <div class="step-row"><span class="step-n">4</span>Download the WordCast Live desktop app from your dashboard</div>
      </div>

      <!-- Security warning -->
      <div class="warning-box">
        <strong>⚠ Do not share this link.</strong> This invite is unique to your email address.
        Anyone who uses this link will gain access to your account. If you believe your link has been compromised,
        contact support immediately.
      </div>

      <!-- Fallback URL -->
      <p style="font-size:13px;color:rgba(255,255,255,0.4);margin-bottom:8px;">
        If the button above doesn't work, copy and paste this URL into your browser:
      </p>
      <div class="fallback-url">{{ $setupUrl }}</div>

      <!-- Sign off -->
      <div class="sign-off" style="margin-top:28px;">
        <p>We can't wait to see what you build. Welcome to the team.</p>
        <br>
        <p>— <strong>The WordCast Live Team</strong></p>
      </div>

    </div>

    <!-- Footer -->
    <div class="footer">
      <p>
        This invite was sent to <strong style="color:rgba(255,255,255,0.4);">{{ $entry->email }}</strong>.
        If you didn't request beta access, please disregard this email or
        <a href="mailto:support@wordcastlive.site">contact support</a>.
      </p>
      <p style="margin-top:8px;">© {{ date('Y') }} WordCast Live. All rights reserved.</p>
    </div>

  </div>
</div>
</body>
</html>
