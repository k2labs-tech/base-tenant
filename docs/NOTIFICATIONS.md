# Notification System

## Overview

The base-tenant notification system provides real-time notifications for project activities, keeping all team members informed of important events.

## Features

- **Project-scoped notifications**: Users only receive notifications for projects they belong to
- **Priority levels**: High, Medium, Low
- **Notification categories**: Project settings, access, milestones, quotas, translations, AI usage
- **Real-time UI updates**: Livewire polling every 30 seconds
- **Full notifications page**: With filters, search, and bulk actions

## Notification Types

### Project Notifications
- Project settings updated
- New language added
- Base language changed
- API key regenerated
- User added/removed
- Project archived/deleted
- Project completed (100%)
- Export generated
- Webhook configured

### Quota Notifications
- API quota warning (80%)
- API quota exceeded (100%)
- AI quota warning

### Translation Notifications
- Translations imported
- Translations exported
- Extensive AI usage

## Usage

### Dispatching Notifications

```php
use Base\Tenant\Services\NotificationService;
use Base\Tenant\Notifications\Project\ProjectSettingsUpdatedNotification;

// Notify all project users except the current user
NotificationService::notifyProjectUsers(
    $project,
    new ProjectSettingsUpdatedNotification(
        $project,
        auth()->user(),
        ['name', 'description']
    ),
    auth()->user()
);
```

### Creating Custom Notifications

Extend `BaseTenantNotification`:

```php
<?php

namespace App\Notifications;

use Base\Tenant\Notifications\BaseTenantNotification;

class CustomNotification extends BaseTenantNotification
{
    public function __construct($project, $causer)
    {
        $this->title = 'Custom Event';
        $this->message = "{$causer->name} triggered custom event";
        $this->actionUrl = route('custom.route');
        $this->priority = 'medium';
        $this->category = 'custom.category';

        // Set causer, project, details arrays
    }
}
```

### Configuration

Edit `config/base-tenant.php`:

```php
'notifications' => [
    'enabled' => true,
    'polling_interval' => 30, // seconds
    'dropdown_limit' => 10,
    'per_page' => 25,
    'categories' => [
        'project.settings' => true,
        // Enable/disable categories
    ],
],
```

## UI Components

### Notification Bell
Displays in header with unread count badge. Polls for new notifications every 30 seconds.

### Notification Dropdown
Shows last 10 notifications with quick actions (mark as read, view).

### Notifications Page
Full page at `/notifications` with:
- Filters (priority, read status)
- Search
- Bulk actions (mark as read, delete)
- Pagination

## Database Schema

Notifications are stored in the `notifications` table (Laravel standard):

```sql
id (uuid), type, notifiable_type, notifiable_id,
data (json), read_at (timestamp), created_at, updated_at
```

## Events

Listen for notification events:

```javascript
// In Livewire component
$wire.on('notificationRead', () => {
    // Handle notification read event
});
```

## Troubleshooting

**Notifications not appearing:**
- Check that polling is enabled
- Verify user belongs to project
- Check browser console for errors

**High database load:**
- Increase polling interval
- Add database indexes
- Consider using Laravel Echo for real-time

**Old notifications accumulating:**
- Implement cleanup job to delete read notifications after X days
- Add to scheduler in AppServiceProvider

## Daily Notification Summary

The base-tenant package provides infrastructure for sending daily notification summary emails to users. This feature allows users to receive a consolidated email of their unread notifications from the previous day.

### Database Schema

Two columns are added to support this feature:

#### accounts table

```php
$table->boolean('daily_notification_summary')
    ->default(true)
    ->comment('Send daily notification summary emails to account users');
```

**Purpose:** Sets the account-level default for whether users receive daily notification summaries.

#### users table

```php
$table->boolean('daily_notification_summary')
    ->nullable()
    ->default(null)
    ->comment('Override account notification preference (null = use account default)');
```

**Purpose:** Allows individual users to override the account default setting.

### Preference Cascading Logic

The system uses a cascading preference model:

1. **User explicit preference (true/false):** If the user has set their own preference, that takes priority
2. **Account default setting:** If the user has no explicit preference (null), use the account's default
3. **System default:** If the account has no setting, default to false (no emails)

### User Model Helper Method

The `User` model provides a helper method to check if a user should receive daily summaries:

```php
/**
 * Determine if user should receive daily notification summaries.
 *
 * @return bool
 */
public function shouldReceiveDailySummary(): bool
{
    // User explicit preference overrides account
    if ($this->daily_notification_summary !== null) {
        return $this->daily_notification_summary;
    }

    // Fall back to account default
    return $this->account->daily_notification_summary ?? false;
}
```

**Usage:**

```php
$user = Auth::user();

if ($user->shouldReceiveDailySummary()) {
    // Queue daily summary email
}
```

### User Preferences UI

The `base-tenant::livewire.preferences` component includes an "Email Notifications" section where users can manage their daily notification summary preferences.

**Options:**
- **Use account default:** Uses the account's setting (shows current value in parentheses)
- **Always enabled:** Receive emails regardless of account setting
- **Always disabled:** Never receive emails regardless of account setting

**Location:** User Profile → Preferences

### Implementation Requirements

The base-tenant package provides the infrastructure (database columns, helper methods, UI) but does **not** include the actual email sending logic. Applications using this package must implement:

1. **Command:** Create an Artisan command to send daily summaries
2. **Mailable:** Create a mailable class for the email template
3. **Template:** Create an email view/template
4. **Scheduling:** Schedule the command to run daily

See your application's documentation for implementation details.

### Migration Files

- `2025_12_02_100000_add_daily_notification_summary_to_accounts.php`
- `2025_12_02_100001_add_daily_notification_summary_to_users.php`

### Related Files

- `src/Models/User.php` - shouldReceiveDailySummary() method
- `src/Livewire/Preferences.php` - Preference management component
- `resources/views/livewire/preferences.blade.php` - Preferences UI
