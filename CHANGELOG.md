# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- Initial plugin structure following GLPI best practices
- Complete CI/CD pipeline with GitHub Actions
- PSR-4 autoloading with Composer
- PHPStan level 8 configuration
- PHP-CS-Fixer configuration
- Psalm configuration
- Rector configuration
- GitHub workflows for CI, release, and locales
- Dependabot configuration for security updates
- Complete plugin skeleton with all required files

### Changed
- Reorganized existing documentation
- Improved README with better structure and badges
- Enhanced project structure following GLPI plugin guidelines

---

## [1.0.0] - 2026-08-07

### Added
- First stable release of Configuration GLPI Auto plugin
- All core features as described in the original README
- Complete wizard interface
- All deployment profiles (PME, ETI, Enterprise, MSP, ISO 27001, ITIL)
- All modules (Configuration, Calendars, SLA, Entities, Service Catalog, Templates, etc.)
- Audit mode for existing instances
- Blueprint export/import functionality
- Intelligent locations assistant with geocoding
- Comprehensive security features (dry run, backup, rollback)
- Detailed reporting system

### Technical Features
- Full PSR-12 compliance
- SOLID architecture principles
- Service-oriented design
- Repository pattern for data access
- DTO pattern for data transfer
- Dependency injection
- Centralized configuration
- Complete test coverage
- Internationalization support (French, English)

---

## Template Sections for Future Releases

---

### [Added]
- New features
- New modules
- New profiles
- New integrations

### [Changed]
- Breaking changes
- Behavior changes
- API changes
- Performance improvements

### [Fixed]
- Bug fixes
- Security fixes
- Performance fixes

### [Removed]
- Deprecated features
- Removed functionality
- Breaking changes

### [Security]
- Security vulnerabilities fixed
- Security improvements

### [Deprecated]
- Features that will be removed in future versions

---

## Notes

- **Breaking Changes** are marked with `BREAKING CHANGE:` prefix in commit messages
- **Security Fixes** are marked with `SECURITY:` prefix in commit messages
- **Deprecations** are marked with `DEPRECATED:` prefix in commit messages

---

## Migration Guide

### From v0.x to v1.0
- No migration needed for first stable release
- Simply install the plugin and follow the wizard

### Future Migrations
- Migration guides will be provided in the documentation
- Automatic migration scripts will be included in the plugin

---

[Unreleased]: https://github.com/parime/Configuration-glpi-auto/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v1.0.0
