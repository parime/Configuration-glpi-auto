[🇫🇷 Français](#-français) · [🇬🇧 English](#-english)

## 🇫🇷 Français

### Résumé

<!-- Quoi, et surtout pourquoi. -->

### Vérifications

- [ ] Testé contre une vraie instance GLPI (Docker, voir CONTRIBUTING.md) — pas seulement PHPUnit
- [ ] `vendor/bin/phpstan analyse --no-progress` sans erreur
- [ ] `vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes` sans diff
- [ ] `vendor/bin/phpunit -c phpunit.xml.dist` vert
- [ ] `CHANGELOG.md` et, si pertinent, `ROADMAP.md` mis à jour
- [ ] Version bumpée (`setup.php`/`composer.json`/`configurationglpiauto.xml`) si applicable

### Base

Cette PR part de `dev` vers `main` (workflow standard du dépôt), sauf mention contraire ci-dessus.

---

## 🇬🇧 English

### Summary

<!-- What, and mainly why. -->

### Checklist

- [ ] Tested against a real GLPI instance (Docker, see CONTRIBUTING.md) — not just PHPUnit
- [ ] `vendor/bin/phpstan analyse --no-progress` clean
- [ ] `vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes` clean
- [ ] `vendor/bin/phpunit -c phpunit.xml.dist` green
- [ ] `CHANGELOG.md` and, if relevant, `ROADMAP.md` updated
- [ ] Version bumped (`setup.php`/`composer.json`/`configurationglpiauto.xml`) if applicable

### Base

This PR targets `dev` → `main` (the repo's standard workflow), unless noted above.
