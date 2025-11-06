#!/usr/bin/env node
/**
 * Simple helper to export the SVG logo to PNG using Puppeteer.
 * Run locally: `node scripts/export-logo.mjs ./assets/brand/suzanne-logo.svg out.png`
 */

import { readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';

const [,, inputPath, outputPath = 'suzanne-logo.png', scaleArg = '2'] = process.argv;

if (!inputPath) {
  console.error('Usage: node scripts/export-logo.mjs <input-svg> [output-png] [scale]');
  process.exit(1);
}

const scale = Number.parseFloat(scaleArg) || 2;

const puppeteer = await import('puppeteer');
const browser = await puppeteer.launch();
const page = await browser.newPage();

const svg = await readFile(inputPath, 'utf8');
await page.setContent(svg, { waitUntil: 'load' });
const element = await page.$('svg');

if (!element) {
  console.error('Could not find <svg> in the provided file.');
  await browser.close();
  process.exit(1);
}

const { width, height } = await element.boundingBox();
await page.setViewport({ width: Math.ceil(width * scale), height: Math.ceil(height * scale), deviceScaleFactor: scale });

const png = await element.screenshot({ omitBackground: true });
await writeFile(outputPath, png);

console.log(`Exported ${path.basename(outputPath)} at ${scale}x scale.`);
await browser.close();
