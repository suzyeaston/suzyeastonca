#!/usr/bin/env node
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');
const SOURCE_DIR = path.join(ROOT, 'assets', 'resume');
const LOCAL_DIR = path.join(ROOT, 'downloads', 'resume');
const FILES = [
  {
    source: path.join(SOURCE_DIR, 'Suzy_Easton_BSA_Resume.pdf'),
    name: 'Suzy_Easton_BSA_Resume.pdf',
  },
  {
    source: path.join(SOURCE_DIR, 'suzy-easton-bsa-resume.html'),
    name: 'suzy-easton-bsa-resume.html',
  },
];

function ensureResumeAssets() {
  const resumeData = path.join(ROOT, 'inc', 'resume-data.php');
  if (!fs.existsSync(resumeData)) {
    throw new Error(
      'Missing inc/resume-data.php. Copy inc/resume-data.example.php to inc/resume-data.php and fill it in.'
    );
  }

  const missing = FILES.filter((file) => !fs.existsSync(file.source));
  if (missing.length === 0) {
    return;
  }

  console.log('Building resume PDF...');
  execSync('npm run build:resume-pdf', { cwd: ROOT, stdio: 'inherit' });

  const stillMissing = FILES.filter((file) => !fs.existsSync(file.source)).map((file) => file.name);
  if (stillMissing.length > 0) {
    throw new Error(`Resume export failed. Missing: ${stillMissing.join(', ')}`);
  }
}

function copyToDir(targetDir) {
  fs.mkdirSync(targetDir, { recursive: true });
  for (const file of FILES) {
    const destination = path.join(targetDir, file.name);
    fs.copyFileSync(file.source, destination);
    console.log(`Wrote ${destination}`);
  }
}

function copyToDownloadsFolder() {
  const home = process.env.HOME || process.env.USERPROFILE;
  if (!home) {
    return;
  }

  const downloadsDir = path.join(home, 'Downloads');
  if (!fs.existsSync(downloadsDir)) {
    return;
  }

  copyToDir(downloadsDir);
}

function revealOnMac(targetDir) {
  if (process.platform !== 'darwin') {
    return;
  }

  try {
    execSync(`open "${targetDir}"`, { stdio: 'ignore' });
  } catch (error) {
    // Finder reveal is optional.
  }
}

function main() {
  ensureResumeAssets();
  copyToDir(LOCAL_DIR);
  copyToDownloadsFolder();
  revealOnMac(LOCAL_DIR);

  console.log('');
  console.log('Resume files are ready locally:');
  console.log(`  ${LOCAL_DIR}`);
  if (process.platform === 'darwin') {
    console.log(`  ${path.join(process.env.HOME, 'Downloads')}`);
  }
  console.log('');
  console.log('These folders are gitignored. Your resume stays off GitHub.');
}

main();
