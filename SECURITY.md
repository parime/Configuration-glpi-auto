# Security Policy

## Supported Versions

Security updates are provided for the following versions:

| Version | Supported | End of Life |
|---------|----------|-------------|
| 2.x | ✅ Yes | TBD |
| 1.x | ✅ Yes | 2028-08-07 |
| < 1.0 | ❌ No | N/A |

> **Note**: Only the latest major version receives active security updates. Previous versions receive security updates for a limited time after the release of a new major version.

---

## Reporting a Vulnerability

If you discover a security vulnerability in Configuration GLPI Auto, please handle it responsibly by following these steps:

### ✅ Do

1. **Email the security team** at [security@parime.fr](mailto:security@parime.fr)
2. Include the following information:
   - Type of vulnerability
   - Detailed description
   - Steps to reproduce
   - Impact assessment
   - Affected versions
   - Any proof-of-concept code (if available)
3. Allow reasonable time for response and fix
4. Keep the vulnerability confidential until a fix is released

### ❌ Do Not

- Create a public GitHub issue
- Disclose the vulnerability publicly
- Exploit the vulnerability
- Share the vulnerability with others

---

## Security Response Process

1. **Acknowledgment**: You will receive an acknowledgment of your report within 24 hours.

2. **Assessment**: Our security team will assess the vulnerability and determine its severity and impact.

3. **Verification**: We will verify the vulnerability and work on a fix or mitigation.

4. **Development**: A fix will be developed and tested internally.

5. **Review**: The fix will be reviewed by the security team and core maintainers.

6. **Release**: 
   - For **critical vulnerabilities**: Immediate release with security advisory
   - For **high vulnerabilities**: Release within 7 days
   - For **medium vulnerabilities**: Release within next scheduled release
   - For **low vulnerabilities**: Release in a future version

7. **Disclosure**: 
   - Security advisories are published on GitHub
   - CVE IDs are requested for eligible vulnerabilities
   - Credit is given to the reporter (if desired)

---

## Security Advisories

Security vulnerabilities are documented in:

- [GitHub Security Advisories](https://github.com/parime/Configuration-glpi-auto/security/advisories)
- [Release Notes](https://github.com/parime/Configuration-glpi-auto/releases)

Each advisory includes:
- Vulnerability description
- Affected versions
- Fixed versions
- Mitigation steps
- Credit information

---

## Security Best Practices

### For Users

1. **Keep updated**: Always use the latest version of the plugin
2. **Monitor announcements**: Watch the repository for security advisories
3. **Secure configuration**: Follow GLPI security best practices
4. **Limit access**: Restrict plugin access to authorized users only
5. **Regular audits**: Periodically review your GLPI configuration

### For Developers

1. **Follow secure coding practices**:
   - Input validation and sanitization
   - Output escaping
   - Use prepared statements for database queries
   - Implement proper access controls

2. **Use secure dependencies**:
   - Keep Composer dependencies updated
   - Regularly run `composer audit`
   - Monitor for security vulnerabilities

3. **Code review**: All changes must be reviewed for security implications

4. **Testing**: Include security testing in your development process

---

## Dependency Security

### Automated Security Scanning

The project uses the following security scanning tools:

- **Dependabot**: Automatically checks for vulnerable dependencies
- **GitHub Code Scanning**: Static analysis for security vulnerabilities
- **Trivy**: Container and dependency vulnerability scanning
- **CodeQL**: Advanced static analysis

### Manual Security Review

- Regular security audits are performed
- Critical code changes undergo security review
- External security assessments are conducted periodically

---

## Security Features

Configuration GLPI Auto includes the following security features:

### 🔒 Access Control
- Role-based access control (RBAC)
- Granular permissions for all features
- Integration with GLPI's authentication system

### 🛡️ Input Validation
- All user inputs are validated and sanitized
- Protection against XSS attacks
- Prevention of SQL injection
- CSRF protection for forms

### 🔐 Data Protection
- Encryption of sensitive data
- Secure storage of API keys and credentials
- Compliance with data protection regulations

### 📊 Audit Trail
- Complete logging of all actions
- Audit logs for sensitive operations
- User activity tracking

---

## Security Contacts

| Purpose | Email | Response Time |
|---------|-------|---------------|
| Security Vulnerabilities | security@parime.fr | Within 24 hours |
| General Support | support@parime.fr | Within 48 hours |
| Legal Issues | legal@parime.fr | Within 72 hours |

---

## Security FAQ

### How do I know if I'm affected by a vulnerability?
Check the [Security Advisories](https://github.com/parime/Configuration-glpi-auto/security/advisories) page and compare your installed version with the affected versions listed.

### How do I update to a secure version?
Follow the normal [installation instructions](README.md#installation) to update your plugin.

### Are there any known vulnerabilities?
All known vulnerabilities are documented in the [Security Advisories](https://github.com/parime/Configuration-glpi-auto/security/advisories). If no advisories are listed, there are no known vulnerabilities in supported versions.

### Do you offer bug bounties?
Currently, we do not have a formal bug bounty program. However, we greatly appreciate and acknowledge security researchers who responsibly disclose vulnerabilities.

### How can I help improve security?
- Report vulnerabilities responsibly
- Review code changes for security issues
- Participate in security discussions
- Follow and promote security best practices

---

## Legal Notice

By reporting a security vulnerability, you acknowledge that you have read and agree to the following:

- You will not disclose the vulnerability to others until a fix is released
- You will not exploit the vulnerability
- You grant us permission to use and disclose your report for the purpose of addressing the vulnerability
- You release us from any liability related to the vulnerability or its disclosure

---

*Last updated: 7 August 2026*
