# Private resume assets — never commit

Copy `inc/resume-data.example.php` to `inc/resume-data.php` and fill in your details.

Optional downloadable files (deploy to server manually):

- `suzy-easton-bsa-resume.html` — standalone HTML resume
- `Suzy_Easton_BSA_Resume.pdf` — generated PDF

Regenerate the PDF after editing the HTML:

```bash
npm run build:resume-pdf
```

Export copies to your computer (repo `downloads/resume/` + Mac `~/Downloads/`):

```bash
npm run resume:export
```

Deploy everything to production (theme files + private resume assets):

```bash
python3 scripts/deploy-resume-ssh.py
```

Or via GitHub Actions after storing resume secrets once:

```bash
./scripts/setup-github-resume-secrets.sh
gh workflow run deploy-resume.yml
```

## Live URLs (after deploy)

- **PDF download:** https://suzyeaston.ca/resume/download/
- **Resume page:** https://suzyeaston.ca/resume/ (create WP page + assign Resume template)

The `/resume/` WordPress page renders from `inc/resume-data.php` and links to these files when present.
