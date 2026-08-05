# Private resume assets — never commit

Copy `inc/resume-data.example.php` to `inc/resume-data.php` and fill in your details.

Optional downloadable files (deploy to server manually):

- `suzy-easton-bsa-resume.html` — standalone HTML resume
- `Suzy_Easton_BSA_Resume.pdf` — generated PDF

Regenerate the PDF after editing the HTML:

```bash
npm run build:resume-pdf
```

The `/resume/` WordPress page renders from `inc/resume-data.php` and links to these files when present.
