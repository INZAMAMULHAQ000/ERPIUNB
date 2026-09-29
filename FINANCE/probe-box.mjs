// Boxplot probe runner: wait for DONE marker, return #out text.
export default async function run(page, ui) {
  await page.waitForFunction(
    () => {
      const el = document.getElementById('out');
      return el && el.textContent.includes('DONE boxplot');
    },
    { timeout: 20000 }
  );
  await page.waitForTimeout(400);
  const text = await page.evaluate(() => document.getElementById('out').textContent);
  return { text };
}
