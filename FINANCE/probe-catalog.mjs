// Run the catalog probe page and wait for the DONE marker, then dump #out.
export default async function run(page, ui) {
  await page.waitForFunction(
    () => {
      const el = document.getElementById('out');
      return el && el.textContent.includes('DONE all types');
    },
    { timeout: 20000 }
  );
  await page.waitForTimeout(500);
  const text = await page.evaluate(() => document.getElementById('out').textContent);
  return { text };
}
