# Contributing to Configuration GLPI Auto

Thank you for your interest in contributing to Configuration GLPI Auto! We welcome contributions from everyone.

This document provides guidelines for contributing to the project. By participating, you agree to abide by this code of conduct.

---

## 📋 Table of Contents

- [Code of Conduct](#-code-of-conduct)
- [Getting Started](#-getting-started)
- [Development Setup](#-development-setup)
- [Contribution Guidelines](#-contribution-guidelines)
- [Pull Request Process](#-pull-request-process)
- [Coding Standards](#-coding-standards)
- [Commit Messages](#-commit-messages)
- [Testing](#-testing)
- [Documentation](#-documentation)
- [Reporting Issues](#-reporting-issues)
- [Security Vulnerabilities](#-security-vulnerabilities)

---

## 🤝 Code of Conduct

This project and everyone participating in it is governed by our [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code. Please report unacceptable behavior to [support@parime.fr](mailto:support@parime.fr).

---

## 🚀 Getting Started

### 1. Fork the Repository

Click the "Fork" button on the top-right of the repository page to create your own copy.

### 2. Clone Your Fork

```bash
git clone git@github.com:your-username/Configuration-glpi-auto.git
cd Configuration-glpi-auto
```

### 3. Set Up Remote

Add the original repository as an upstream remote:

```bash
git remote add upstream https://github.com/parime/Configuration-glpi-auto.git
```

---

## 🛠️ Development Setup

### Prerequisites

- **PHP 8.2+**
- **Composer 2.0+**
- **Git 2.0+**
- **MySQL/MariaDB/PostgreSQL** (for testing)
- **GLPI 11.0+** (for integration testing)

### Install Dependencies

```bash
# Install PHP dependencies
composer install --dev

# For frontend development (if applicable)
# npm install
```

### Configure Environment

Create a `.env` file based on the example:

```bash
cp .env.example .env
```

Edit the `.env` file with your local configuration.

### Set Up Database for Testing

```bash
# Create test database
mysql -u root -p -e "CREATE DATABASE glpi_test;"

# Run migrations
php vendor/bin/console migrations:migrate
```

---

## 📝 Contribution Guidelines

### Types of Contributions

#### 🐛 Bug Reports
- Use GitHub Issues to report bugs
- Provide clear steps to reproduce
- Include your environment details (PHP version, GLPI version, etc.)
- Add screenshots if applicable

#### ✨ Feature Requests
- Use GitHub Issues with the `enhancement` label
- Describe the feature and its use case
- Explain why it would be beneficial
- Include mockups or examples if applicable

#### 🔧 Code Contributions
- Follow the [Pull Request Process](#-pull-request-process)
- Adhere to [Coding Standards](#-coding-standards)
- Include tests for new functionality
- Update documentation as needed

#### 📚 Documentation Improvements
- Fix typos, improve clarity
- Add missing documentation
- Update outdated information
- Add examples and tutorials

#### 🌍 Translations
- Translate existing strings
- Add new language support
- Review existing translations

---

## 🎯 Pull Request Process

### 1. Create a Feature Branch

```bash
git checkout -b feature/your-feature-name
# or for bugs:
git checkout -b fix/your-bug-fix
```

### 2. Make Your Changes

- Follow [Coding Standards](#-coding-standards)
- Add tests for new functionality
- Update documentation if needed
- Keep commits atomic and focused

### 3. Commit Your Changes

Follow [Commit Messages](#-commit-messages) guidelines:

```bash
git commit -m "feat: add new configuration profile"
```

### 4. Push to Your Fork

```bash
git push origin feature/your-feature-name
```

### 5. Create a Pull Request

- Go to the original repository on GitHub
- Click "New Pull Request"
- Select your branch
- Fill in the PR template
- Provide a clear description of your changes
- Link to any relevant issues

### 6. Review Process

- Your PR will be reviewed by maintainers
- Address any feedback or requested changes
- PRs are merged after approval
- Typically merged into `develop` branch first

### 7. After Merge

- Your changes will be included in the next release
- Thank you for your contribution!

---

## 💻 Coding Standards

### PHP Standards

- **PSR-1**: Basic Coding Standard
- **PSR-4**: Autoloading Standard
- **PSR-12**: Extended Coding Style Guide

### Project Specific Standards

- Use **4 spaces** for indentation (no tabs)
- **80-120 characters** per line maximum
- Use **type hints** for all method parameters and return values
- Follow **SOLID** principles
- Use **dependency injection** where appropriate
- Keep methods **small and focused**
- Use **meaningful names** for variables, methods, and classes
- Add **PHPDoc comments** for all classes and methods
- Use **English** for code, **French** for user-facing strings

### File Structure

```php
<?php

namespace GlpiPlugin\Configurationglpiauto\Service;

/**
 * Short description of the class.
 *
 * Longer description if needed.
 */
class YourService
{
    /**
     * @var Type $property Description
     */
    private Type $property;

    /**
     * Constructor.
     */
    public function __construct(Dependency $dependency)
    {
        $this->property = $value;
    }

    /**
     * Method description.
     *
     * @param Type $param Description
     * @return Type Description
     */
    public function methodName(Type $param): Type
    {
        // Method implementation
    }
}
```

### Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Classes | PascalCase | `ConfigurationService` |
| Methods | camelCase | `getConfiguration()` |
| Variables | camelCase | `$configurationData` |
| Constants | UPPER_SNAKE_CASE | `DEFAULT_TIMEOUT` |
| Files | snake_case | `configuration_service.php` |
| Namespaces | PascalCase | `GlpiPlugin\Configurationglpiauto\Service` |

---

## 📝 Commit Messages

We follow [Conventional Commits](https://www.conventionalcommits.org/) for commit messages.

### Commit Types

| Type | Usage |
|------|-------|
| `feat` | A new feature |
| `fix` | A bug fix |
| `docs` | Documentation only changes |
| `style` | Changes that do not affect the meaning of the code (formatting, etc.) |
| `refactor` | A code change that neither fixes a bug nor adds a feature |
| `perf` | A code change that improves performance |
| `test` | Adding missing tests |
| `chore` | Changes to the build process or auxiliary tools and libraries |
| `revert` | Reverts a previous commit |
| `WIP` | Work in progress |

### Commit Message Format

```
type(scope): description

body

footer
```

### Examples

```
feat(wizard): add ISO 27001 profile support

- Add new profile type
- Include predefined configurations
- Add validation rules

fix(deployment): correct SLA calculation bug

- Fix calculation formula
- Add unit tests
- Closes #123

Docs(readme): update installation instructions

- Add Composer installation method
- Fix typos
- Improve formatting

BREAKING CHANGE: This commit introduces breaking changes
```

---

## 🧪 Testing

### Running Tests

```bash
# Run all tests
composer test

# Run PHPUnit tests
vendor/bin/phpunit

# Run with coverage
vendor/bin/phpunit --coverage-text

# Run specific test suite
vendor/bin/phpunit --filter Unit
vendor/bin/phpunit --filter Integration
```

### Test Structure

```
tests/
├── Unit/           # Unit tests (isolated components)
├── Integration/    # Integration tests (component interactions)
└── Functional/     # Functional tests (end-to-end scenarios)
```

### Test Guidelines

- **Unit Tests**: Test individual classes and methods in isolation
- **Integration Tests**: Test interactions between multiple components
- **Functional Tests**: Test complete user scenarios
- Each test should be **independent**
- Use **descriptive names** for test methods
- Test **both success and failure cases**
- Keep tests **fast and reliable**

### Example Test

```php
<?php

namespace GlpiPlugin\Configurationglpiauto\Tests\Unit\Service;

use GlpiPlugin\Configurationglpiauto\Service\ConfigurationService;
use PHPUnit\Framework\TestCase;

class ConfigurationServiceTest extends TestCase
{
    private ConfigurationService $service;

    protected function setUp(): void
    {
        $this->service = new ConfigurationService();
    }

    public function testGetProfilesReturnsArray(): void
    {
        $profiles = $this->service->getProfiles();
        
        $this->assertIsArray($profiles);
        $this->assertNotEmpty($profiles);
    }

    public function testGetProfileByIdThrowsExceptionForInvalidId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->getProfileById(-1);
    }
}
```

---

## 📚 Documentation

### Documentation Guidelines

- Use **Markdown** format
- Keep it **clear and concise**
- Include **examples** where helpful
- Use **code blocks** for commands and configuration
- Add **screenshots** for UI documentation
- Keep documentation **up to date**

### Documentation Structure

```
docs/
├── user-guide.md          # User documentation
├── installation.md        # Installation guide
├── configuration.md       # Configuration guide
├── development/
│   ├── architecture.md    # Architecture overview
│   ├── module-development.md
│   ├── hooks.md
│   └── best-practices.md
└── api/
    └── index.md           # API documentation
```

---

## 🐛 Reporting Issues

### Bug Reports

When reporting a bug, please include:

1. **Clear title** describing the issue
2. **Detailed description** of the problem
3. **Steps to reproduce**
4. **Expected behavior**
5. **Actual behavior**
6. **Environment details**:
   - PHP version
   - GLPI version
   - Plugin version
   - Database type and version
   - Operating system
7. **Screenshots** if applicable
8. **Logs** if applicable

### Feature Requests

When requesting a feature, please include:

1. **Clear title** describing the feature
2. **Detailed description** of what you want to achieve
3. **Use case** - Why is this feature needed?
4. **Proposed solution** (if you have one)
5. **Additional context** (screenshots, examples, etc.)

---

## 🔒 Security Vulnerabilities

If you discover a security vulnerability within Configuration GLPI Auto, please handle it responsibly:

1. **Do not** create a public GitHub issue
2. **Do not** disclose the vulnerability publicly
3. **Do** send details to [security@parime.fr](mailto:security@parime.fr)

We will:
- Acknowledge your report within 24 hours
- Work with you to understand and verify the issue
- Develop and test a fix
- Release the fix in a timely manner
- Credit you for the discovery (if you wish)

---

## 🎁 Code Review Process

### What We Look For

- **Code Quality**: Follows coding standards
- **Functionality**: Works as intended
- **Tests**: Adequate test coverage
- **Documentation**: Updated as needed
- **Performance**: No performance regressions
- **Security**: No security vulnerabilities
- **Compatibility**: Works with supported GLPI versions

### Common Feedback

- "Please add tests for this functionality"
- "This could be simplified"
- "Please follow PSR-12 standards"
- "Add PHPDoc comments"
- "Update the documentation"
- "Consider the performance impact"

---

## 🌟 Recognition

All contributions are valuable and appreciated! Contributors will be:

- Listed in the [AUTHORS](AUTHORS.txt) file
- Recognized in release notes
- Thanked in the project's social media
- Invited to contribute to future development discussions

---

## 📞 Need Help?

- **GitHub Discussions**: For general questions and discussions
- **GitHub Issues**: For bug reports and feature requests
- **Documentation**: Check the [docs/](docs/) directory
- **Email**: For private matters, contact [support@parime.fr](mailto:support@parime.fr)

---

Thank you for contributing to Configuration GLPI Auto! Your contributions help make this project better for everyone in the GLPI community.

---

*Last updated: 7 August 2026*
