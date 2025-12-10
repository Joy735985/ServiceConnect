<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ServIQ — Smart Service Platform</title>
  <!-- Use your existing styles -->
  <link rel="stylesheet" href="serviq.css">
  <link rel="stylesheet" href="style.css">
  <style>
    /* Page-scoped utilities (tiny — bulk is in serviq.css add-on below) */
    .container{max-width:1120px;margin:0 auto;padding:24px}
    .grid{display:grid;gap:18px}
    .grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}
    .grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}
    @media (max-width: 960px){.grid-3,.grid-2{grid-template-columns:1fr}}
  </style>
</head>
<body>

  <!-- Top nav -->
  <header class="glass-nav">
    <div class="container nav-row">
      <div class="brand-row">
        <span class="logo-dot">⚡</span>
        <span class="brand-name">ServiceConnect</span>
      </div>
      <nav class="nav-links">
        <a href="login.php">Login</a>
        <a href="signup.php">Sign Up</a>
        <a href="admin.php">Admin</a>
      </nav>
    </div>
  </header>

  <!-- Hero -->
  <section class="hero-wrap">
    <div class="hero-bg"></div>
    <div class="container hero-inner">
      <div class="hero-left">
        <div class="kicker">Smart Home Services</div>
        <h1>Run your service business on <span class="accent">ServiceConnect.</span></h1>
        <p class="sub">
          Bookings, dispatch, live tracking, and analytics — unified in one sleek portal for
          customers, technicians, and admins.
        </p>
        <div class="cta">
          <a class="btn primary bump" href="signup.php">Get Started</a>
          <a class="btn ghost" href="login.php">I already have an account</a>
        </div>
        <div class="hero-stats">
          <div><strong>120+</strong><span>Technicians</span></div>
          <div><strong>790</strong><span>Customers</span></div>
          <div><strong>98%</strong><span>On-time jobs</span></div>
          <div><strong>4.8★</strong><span>Avg rating</span></div>
        </div>
      </div>

      <div class="hero-right">
        <div class="glass phone">
          <div class="phone-top">
            <div class="bubble success">New job • 10:30</div>
            <div class="bubble info">ETA updated</div>
          </div>
          <div class="phone-map shimmer"></div>
          <div class="phone-cards">
            <div class="mini stat">
              <div class="ic">🚚</div>
              <div>
                <div class="k">Live Jobs</div>
                <div class="v">24</div>
              </div>
            </div>
            <div class="mini stat">
              <div class="ic">⭐</div>
              <div>
                <div class="k">Today’s CSAT</div>
                <div class="v">4.9</div>
              </div>
            </div>
            <div class="mini stat">
              <div class="ic">💸</div>
              <div>
                <div class="k">Revenue</div>
                <div class="v">$12.4k</div>
              </div>
            </div>
          </div>
        </div>
        <div class="orbit">
          <span class="dot d1"></span>
          <span class="dot d2"></span>
          <span class="dot d3"></span>
        </div>
      </div>
    </div>
  </section>

  <!-- Feature cards -->
  <section class="container features grid grid-3">
    <article class="feature card lift">
      <div class="badge">⚡</div>
      <h3>Instant Booking</h3>
      <p>Frictionless scheduling with smart time windows and automatic technician assignment.</p>
      <a class="link" href="signup.php">Start booking →</a>
    </article>
    <article class="feature card lift">
      <div class="badge">📍</div>
      <h3>Live Tracking</h3>
      <p>Realtime location and status updates — customers and admins see the same truth.</p>
      <a class="link" href="login.php">See tracking →</a>
    </article>
    <article class="feature card lift">
      <div class="badge">📊</div>
      <h3>Actionable Insights</h3>
      <p>Daily metrics, route efficiency, and CSAT — focus on what moves revenue.</p>
      <a class="link" href="admin.php">Explore analytics →</a>
    </article>
  </section>

  <!-- Social proof / logos -->
  <section class="container logos">
    <div class="logos-row">
      <span class="pill">Trusted by growing teams</span>
      <div class="logo-line">
        <i>ZenElectrics</i><i>HomeHero</i><i>AeroCool</i><i>FixRight</i><i>EcoSpark</i>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="site-footer">
    <div class="container foot-row">
      <div>© <?=date('Y')?> ServiceConnect</div>
      <div class="foot-links">
        <a href="privacy.php">Privacy</a>
        <a href="terms.php">Terms</a>
        <a href="contact.php">Contact</a>
      </div>
    </div>
  </footer>

</body>
</html>
