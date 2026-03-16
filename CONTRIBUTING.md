# Contributing to LPhenom

Thank you for considering contributing to LPhenom!

## Development Setup

```bash
# Clone the repository
git clone git@github.com:lphenom/lphenom.git
cd lphenom

# Start development environment
make up

# Run tests
make test

# Run linter
make lint

# Run static analysis
make analyse
```

## Coding Standards

- **PHP >= 8.1** — strict_types in every file
- **KPHP compatible** — no reflection, eval, dynamic class loading, variable variables
- **No trailing commas** in function arguments (KPHP limitation)
- **No constructor property promotion** — explicit property declarations
- **No readonly properties** — use private + getter
- **No match expressions** — use if/elseif chains
- **No str_starts_with/str_ends_with/str_contains** — use Str:: utils from lphenom/core
- **No callable in typed arrays** — use interfaces

## Pull Request Process

1. Fork the repository
2. Create a feature branch from `main`
3. Write tests for your changes
4. Ensure all tests pass: `make test`
5. Ensure code style: `make lint`
6. Ensure static analysis: `make analyse`
7. Submit a pull request

## Commit Messages

Follow conventional commits:

- `feat:` — new feature
- `fix:` — bug fix
- `docs:` — documentation
- `chore:` — maintenance
- `test:` — test additions/changes
- `ci:` — CI/CD changes

## Code of Conduct

Be respectful and constructive. We follow the [Contributor Covenant](https://www.contributor-covenant.org/).

