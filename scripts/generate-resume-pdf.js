const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

async function main() {
  const htmlPath = path.resolve(__dirname, '../assets/resume/suzy-easton-bsa-resume.html');
  const pdfPath = path.resolve(__dirname, '../assets/resume/Suzy_Easton_BSA_Resume.pdf');

  if (!fs.existsSync(htmlPath)) {
    throw new Error(`Resume HTML not found: ${htmlPath}`);
  }

  const browser = await chromium.launch();
  const page = await browser.newPage();

  await page.goto(`file://${htmlPath}`, { waitUntil: 'networkidle' });
  await page.emulateMedia({ media: 'print' });
  await page.pdf({
    path: pdfPath,
    format: 'Letter',
    printBackground: true,
    margin: { top: '0.45in', right: '0.5in', bottom: '0.45in', left: '0.5in' },
  });

  await browser.close();

  const sizeKb = Math.round(fs.statSync(pdfPath).size / 1024);
  console.log(`Wrote ${pdfPath} (${sizeKb} KB)`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
