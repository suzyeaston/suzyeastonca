# Local resume exports

This folder is gitignored. Nothing here is committed to GitHub.

Generate your resume files on your computer:

```bash
npm run resume:export
```

That builds the PDF (if needed) and copies it to:

- `downloads/resume/` in this repo
- `~/Downloads/` on your Mac

Private source files stay in:

- `inc/resume-data.php`
- `assets/resume/Suzy_Easton_BSA_Resume.pdf`
- `assets/resume/suzy-easton-bsa-resume.html`
