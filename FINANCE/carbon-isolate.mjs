// Test the Carbon UMD bundle in complete isolation (no app, no login).
export default async function run(page, ui) {
  await page.goto('about:blank');
  const out = await page.evaluate(async () => {
    const res = {};
    // Capture any window.onerror during load
    const errs = [];
    const prev = window.onerror;
    window.onerror = (m, src, ln) => { errs.push(String(m) + ' @ ' + src + ':' + ln); };

    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/@carbon/charts@1.27.18/dist/umd/bundle.umd.js';
    s.onerror = () => { res.loadError = 'script onerror fired'; };
    document.head.appendChild(s);

    // Wait until the script either defines Charts or 4s elapse
    const start = Date.now();
    while (typeof window.Charts === 'undefined' && Date.now() - start < 4000) {
      await new Promise(r => setTimeout(r, 100));
    }

    res.errs = errs;
    res.Charts = typeof window.Charts;
    res.chartsKeys = window.Charts ? Object.keys(window.Charts).length : null;
    res.globalThisKeys = Object.keys(window).filter(k => k.toLowerCase().includes('chart')).slice(0, 20);
    res.loadError = res.loadError || null;
    window.onerror = prev;
    return res;
  });
  return out;
}
