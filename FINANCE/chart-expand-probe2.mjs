// Dump the inner DOM of a GROWING chart holder on the executive page to find
// which child element has the runaway height and what inline styles Carbon sets.
export default async function run(page, ui) {
  const login = await ui.snapshot();
  const emailRef = login.match(/@(e\d+) .*"Email/)?.[1] || login.match(/@(e\d+) (?:textbox|input)/)?.[1];
  const passRef  = login.match(/@(e\d+) .*[Pp]assword/)?.[1];
  await ui.fill(emailRef, 'admin@erp.com');
  await ui.fill(passRef, 'admin123');
  await ui.click(login.match(/@(e\d+) button "Sign in"/)?.[1]);
  await page.waitForTimeout(2500);

  await page.goto('http://localhost/ERP/modules/executive_dashboard/index.php', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2500);

  const dump = () => page.evaluate(() => {
    const holder = [...document.querySelectorAll('[data-carbon-chart]')].find(el => el.offsetParent !== null);
    if (!holder) return { err: 'no visible holder' };
    const rec = el => ({
      tag: el.tagName.toLowerCase(),
      cls: (el.className && String(el.className).slice(0, 60)) || '',
      id: el.id || '',
      rectH: Math.round(el.getBoundingClientRect().height),
      rectW: Math.round(el.getBoundingClientRect().width),
      inlineH: el.style.height || '',
      inlineW: el.style.width || '',
    });
    const out = { holder: rec(holder) };
    const walk = (node, depth) => {
      if (!node || depth > 6) return;
      out.holder.children = out.holder.children || [];
      const info = rec(node);
      info.depth = depth;
      if (node.querySelector('svg')) {
        const svg = node.querySelector('svg');
        info.hasSvg = true;
        info.svgAttrH = svg.getAttribute('height');
        info.svgViewBox = svg.getAttribute('viewBox');
      }
      out.holder.children.push(info);
      for (const c of node.children) walk(c, depth + 1);
    };
    for (const c of holder.children) walk(c, 0);
    return out;
  });

  const t0 = await dump();
  await page.waitForTimeout(1200);
  const t1 = await dump();
  return { t0, t1 };
}
