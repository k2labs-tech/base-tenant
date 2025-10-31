# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-30

### Added
- Initial release of Base Tenant package
- Multi-tenant architecture with Accounts and Users
- Role-based access control (RBAC) system
- Extensible roles configuration
- Stripe subscription management via Laravel Cashier 16
- Two-factor authentication (2FA) support
- User preferences and localization
  - Timezone support
  - Currency formatting
  - Number formatting
  - Date/time formatting
  - Multi-language support
- Livewire 3 components
  - User management (CRUD)
  - Two-factor authentication
  - Login/Registration forms
- Laravel 12 compatibility
- Tailwind CSS 3 integration
- Livewire Flux Pro UI components
- Comprehensive testing suite with Pest 3
- Database migrations for all package tables
- Seeders with test users and roles
- Middleware
  - HasSubscription
  - DoesNotHaveSubscription
  - SetLocale
- Artisan commands
  - `base-tenant:sync-roles` - Sync roles from configuration
- Publishable assets (CSS, JS)
- Publishable views for customization
- Publishable configuration file
- Factory classes for testing
- API authentication via Laravel Sanctum
- UUID primary keys for security
- Session-based role caching for performance

### Configuration
- Multi-team toggle
- Subscription settings
- Extensible roles system
- Model overrides
- Route customization
- UI branding options

### Documentation
- Comprehensive README
- Detailed installation guide
- Frontend customization guide
- API documentation
- Upgrade guide from Laravel 11

### Security
- Two-factor authentication
- Password encryption
- CSRF protection
- UUID primary keys
- Recovery codes for 2FA
- Encrypted 2FA secrets

### Performance
- Session-based role caching
- Optimized database queries
- Lazy loading relationships
- Compiled frontend assets

## [Unreleased]

### Planned
- Multi-language admin panel
- Team switching interface
- Advanced permission system
- Activity logging
- Email notifications
- Webhook support
- API documentation with Scramble
- GraphQL API support
