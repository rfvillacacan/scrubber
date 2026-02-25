# Contributing

## Development
1. Create a branch.
2. Keep changes focused.
3. Run checks before opening PR.

## Checks
```bash
php -l index.php
find lib -name '*.php' -print0 | xargs -0 -n1 php -l
docker compose config
```

## Pull Requests
- Describe behavior change clearly.
- Include testing steps and expected output.
- Keep secrets, certs, and runtime data out of commits.
