<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="color-scheme" content="light dark">
  <meta name="supported-color-schemes" content="light dark">
  <title>WordCast Live Beta Waitlist</title>
  <style>
    :root { 
      color-scheme: light dark; 
      supported-color-schemes: light dark; 
    }
    body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f7f7f9; color: #111827; -webkit-font-smoothing: antialiased; }
    .wrapper { padding: 40px 20px; }
    .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
    .body { padding: 40px; }
    h1 { font-size: 24px; font-weight: 600; margin: 0 0 16px; line-height: 1.3; letter-spacing: -0.5px; }
    p { font-size: 15px; color: #4b5563; line-height: 1.6; margin: 0 0 24px; }
    .divider { height: 1px; background-color: #f3f4f6; margin: 32px 0; }
    .section-title { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: #6b7280; margin-bottom: 16px; }
    ul { list-style: none; margin: 0 0 32px; padding: 0; }
    li { font-size: 15px; color: #4b5563; padding: 12px 0; border-bottom: 1px solid #f9fafb; display: flex; align-items: flex-start; }
    li:last-child { border-bottom: none; padding-bottom: 0; }
    li strong { color: #111827; font-weight: 600; display: block; margin-bottom: 2px; }
    .bullet { color: #FF6600; font-size: 18px; line-height: 1; margin-right: 12px; margin-top: -2px; }
    
    .footer { max-width: 600px; margin: 40px auto 0; text-align: center; color: #0f172a; }

    .footer-socials { margin: 32px 0; }
    .social-icon { fill: #0f172a; width: 24px; height: 24px; margin: 0 12px; }
    .footer-disclaimer { font-size: 14px; color: #334155; line-height: 1.6; margin: 0 0 16px; padding: 0 20px; }
    .footer-disclaimer a { color: #0f172a; text-decoration: underline; font-weight: 600; }
    .footer-copyright { font-size: 13px; color: #334155; margin: 0 0 16px; font-weight: 500; }
    .footer-bottom-links { margin-top: 16px; }
    .footer-bottom-links a { display: inline-block; color: #334155; text-decoration: underline; font-size: 14px; margin: 0 12px; font-weight: 500; }
    
    @media (prefers-color-scheme: dark) {
      body { background-color: #000000 !important; color: #ffffff !important; }
      .container { background-color: #0B0B0F !important; border-color: #1a1a1a !important; }
      h1 { color: #ffffff !important; }
      p, li { color: #888888 !important; }
      li strong { color: #ffffff !important; }
      .divider { background-color: #1a1a1a !important; }
      li { border-color: #141414 !important; }
      .section-title { color: #555555 !important; }
      
      .footer { color: #f8fafc !important; }

      .social-icon { fill: #f8fafc !important; }
      .footer-disclaimer { color: #cbd5e1 !important; }
      .footer-disclaimer a { color: #f8fafc !important; }
      .footer-copyright { color: #cbd5e1 !important; }
      .footer-bottom-links a { color: #cbd5e1 !important; }
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="container">
      <img src="https://res.cloudinary.com/dg9elcrcw/image/upload/v1781535672/wailist_unyb1k.webp" alt="WordCast Live Waitlist" width="600" style="max-width: 100%; height: auto; display: block;" />
      <div class="body">
        <h1>You're on the waitlist</h1>
        <p>Hi {{ $entry->firstName() }},</p>
        <p>Thanks for requesting access to the WordCast Live beta. We have received your application and added you to our waitlist.</p>
        <p>We are currently reviewing applications and rolling out invites in batches. Once your account is ready, we will send an activation link to this email address.</p>
        
        <div class="divider"></div>
        
        <div class="section-title">What you'll get</div>
        <ul>
          <li><span class="bullet">&bull;</span><div><strong>Pro Plan Access</strong> Free for the first month</div></li>
          <li><span class="bullet">&bull;</span><div><strong>Theme Editor</strong> Full access to custom layouts</div></li>
          <li><span class="bullet">&bull;</span><div><strong>AI Features</strong> Real-time transcription &amp; Scripture detection</div></li>
        </ul>
        
        <p>We appreciate your patience and look forward to having you on board.</p>
        <p style="margin-bottom: 0;">&mdash; The WordCast Live Team</p>
      </div>
    </div>
    
    <div class="footer">

      
      <div class="footer-socials">
        <a href="https://twitter.com/wordcastlive"><svg class="social-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z"/></svg></a>
        <a href="https://instagram.com/wordcastlive"><svg class="social-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg></a>
        <a href="https://facebook.com/wordcastlive"><svg class="social-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M24 12.07C24 5.41 18.627 0 12 0 5.373 0 0 5.41 0 12.07c0 5.93 4.254 10.87 9.852 11.89V15.53H6.885V12.07h2.967V9.43c0-2.92 1.734-4.54 4.408-4.54 1.272 0 2.6.23 2.6.23v2.86h-1.46c-1.44 0-1.89.9-1.89 1.83v2.26h3.2l-.51 3.46H12.51v8.43C18.423 23.95 24 18.63 24 12.07z"/></svg></a>
      </div>
      
      <p class="footer-disclaimer">
        Want to change which emails you receive from us? You can <a href="https://wordcastlive.site/preferences">update your preferences</a> or <a href="https://wordcastlive.site/unsubscribe">unsubscribe</a>. You can view our <a href="https://wordcastlive.site/privacy">privacy policy</a>.
      </p>
      
      <p class="footer-copyright">&copy; {{ date('Y') }} WordCast Live. All Rights Reserved.</p>
      
      <div class="footer-bottom-links">
        <a href="https://wordcastlive.site/privacy">Privacy policy</a>
        <a href="https://wordcastlive.site/unsubscribe">Unsubscribe</a>
        <a href="https://wordcastlive.site/terms">Terms of Service</a>
      </div>
    </div>
  </div>
</body>
</html>
