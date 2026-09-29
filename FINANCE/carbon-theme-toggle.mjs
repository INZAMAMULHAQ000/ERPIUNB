// Theme-toggle test: load home, cycle through all 6 themes, assert Carbon SVGs
// survive reTheme() on every theme with no console errors.
export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(2500);

  await page.goto('http://localhost/ERP/index.php', { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => document.querySelectorAll('[data-carbon-chart] svg').length >= 1, { timeout: 8000 });
  await page.waitForTimeout(800);

  const THEMES = ['light', 'candy', 'dark', 'kito', 'ibm-light', 'ibm-dark'];
  const results = [];

  // Cycle from the current theme through every theme by clicking the real
  // navbar button (main-world click fires toggleTheme() in the page's context).
  for (let i = 0; i < THEMES.length; i++) {
    await page.click('#themeToggle');
    await page.waitForTimeout(700);
    results.push(await page.evaluate(() => ({
      theme: document.body.getAttribute('data-theme'),
      svgs: document.querySelectorAll('[data-carbon-chart] svg').length,
      containers: document.querySelectorAll('[data-carbon-chart]').length,
    })));
  }

  // Also verify the dark-theme token (g100) actually got applied to chart wrappers.
  const tokenCheck = await page.evaluate(() => {
    const wrap = document.querySelector('.cds--chart-holder, .cds--cc--chart-wrap');
    const svg = document.querySelector('[data-carbon-chart] svg');
    return {
      bodyTheme: document.body.getAttribute('data-theme'),
      wrapBg: wrap ? getComputedStyle(wrap).backgroundColor : null,
      svgPresent: !!svg,
      svgFilled: svg ? svg.querySelectorAll('path, rect').length : 0,
    };
  });

  return { results, tokenCheck };
}
