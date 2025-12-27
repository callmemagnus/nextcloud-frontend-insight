# Frontend-Insight

**Know what happens in your client's browser**

Frontend-Insight is a Nextcloud app that captures client-side JavaScript errors and exceptions from your users' browsers, providing valuable insights into frontend issues that may otherwise go unnoticed.

## Features

- **Automatic Error Capture**: Monitors and logs JavaScript errors and unhandled promise rejections from client browsers
- **Event Browser**: Modern, responsive UI to view and analyze captured events with:
  - Advanced filtering and search capabilities
  - Server-side sorting by timestamp, type, message, URL, or file
  - Pagination with configurable page sizes
  - Detailed event information including stack traces and user agent data
  - Copy-to-clipboard functionality for quick debugging
- **Configurable Collection**: Admin settings to enable/disable collection of:
  - JavaScript errors (`error` events)
  - Unhandled promise rejections (`unhandledrejection` events)
- **Data Retention Management**: Automatic purging of old events with configurable retention period (default: 30 days)
- **Group-based Access Control**: Restrict error collection to specific user groups
- **Rate Limiting**: Built-in protection against abuse:
  - Authenticated users: 20 requests per minute
  - Anonymous users: 10 requests per minute
- **RESTful API**: Access events programmatically via HTTP API (see [API.md](API.md))
- **Client-side Throttling**: Prevents flooding the server with duplicate errors (max 10 errors per 10 seconds)

## Screenshots

![Event Browser](screenshots/event-browser.png)
_View and analyze frontend errors with filtering, sorting, and detailed stack traces_

![Admin Settings](screenshots/admin-settings.png)
_Configure error collection, retention policy, and group permissions_

## Requirements

- Nextcloud 30, 31, or 32
- PHP 8.0 or higher
- Modern web browser with JavaScript enabled

## Installation

### From Nextcloud App Store

1. Navigate to **Apps** in your Nextcloud instance
2. Search for **frontend-insight**
3. Click **Download and enable**

### Manual Installation

1. Clone this repository into your Nextcloud apps directory:
   ```bash
   cd /path/to/nextcloud/apps
   git clone https://github.com/callmemagnus/nextcloud-frontend-insight.git frontend-insight
   ```

2. Install dependencies and build the app:
   ```bash
   cd frontend-insight
   npm install
   npm run build
   composer install --no-dev
   ```

3. Enable the app:
   ```bash
   php occ app:enable frontend-insight
   ```

## Configuration

### Admin Settings

Navigate to **Settings** → **Administration** → **Frontend Insight** to configure:

#### Collection Settings
- **Collect JavaScript errors**: Enable/disable capture of standard `error` events
- **Collect unhandled rejections**: Enable/disable capture of `unhandledrejection` events

#### Data Retention
- **Retention period (hours)**: How long to keep events before automatic deletion (default: 720 hours / 30 days)
- Events are purged hourly by a background job

#### Group Restrictions
- **Allowed groups**: Limit error collection to specific user groups
- Leave empty to collect errors from all users
- Useful for beta testing or staged rollouts

### Viewing Events

1. Navigate to **Settings** → **Administration** → **Frontend Insight**
2. Use the **Event Browser** to view captured errors:
   - **Filter**: Search across all event fields (excluding timestamps)
   - **Sort**: Order by timestamp, type, message, URL, or file
   - **Expand rows**: Double-click or use the arrow to view full details
   - **Copy values**: Click any text to copy to clipboard
   - **Purge all**: Remove all events (button disabled when no events exist)

### Date Display Options
- **Timezone**: UTC, Browser local, or Nextcloud server timezone
- **Format**: ISO 8601, Human-readable, Local format, or Relative time (e.g., "2 hours ago")

## How It Works

1. **Client Script**: When a user accesses any Nextcloud page, the Frontend Insight client script is automatically loaded
2. **Error Listening**: The script listens for:
   - JavaScript errors via `window.addEventListener('error')`
   - Unhandled promise rejections via `window.addEventListener('unhandledrejection')`
3. **Reporting**: When an error occurs, the client sends it to `/apps/frontend-insight/report/error` with:
   - Timestamp
   - Error type
   - User agent
   - Page URL
   - Error message
   - Stack trace
   - File, line, and column information (if available)
4. **Storage**: The server validates, rate-limits, and stores the event in the database
5. **Viewing**: Admins can browse events through the web UI or access them via the API
6. **Cleanup**: A background job runs hourly to delete events older than the retention period

## Development

### Building

```bash
# Install dependencies
npm install
composer install

# Build frontend
npm run build

# Build individual components
npm run build:event-browser  # Event browser UI (Svelte)
npm run build:client        # Client-side error reporter

# Type checking
npm run check
```

### Project Structure

```
frontend-insight/
├── appinfo/
│   ├── info.xml           # App metadata
│   └── routes.php         # HTTP routing
├── img/                   # App icons
├── js/                    # Compiled JavaScript (generated)
├── lib/
│   ├── BackgroundJob/     # Scheduled tasks (event purging)
│   ├── Controller/        # HTTP controllers
│   ├── Db/               # Database entities and mappers
│   ├── Sections/         # Admin settings sections
│   └── Settings/         # Admin settings UI
├── src/
│   ├── client/           # Client-side error reporter (TypeScript)
│   └── event-browser/    # Event browser UI (Svelte 5)
├── templates/            # PHP templates for admin UI
├── API.md               # API documentation
└── README.md            # This file
```

### Tech Stack

**Backend:**
- PHP 8.2+ with Nextcloud App Framework
- Database abstraction (supports SQLite, MySQL, PostgreSQL)

**Frontend:**
- Svelte 5 (event browser UI)
- TypeScript (client script)
- Vite (build tool)
- Axios (HTTP requests)

### API Documentation

See [API.md](API.md) for detailed API documentation, including:
- Endpoint specifications
- Request/response formats
- Rate limiting details
- CORS configuration
- Data models

## Privacy & Security

- **No sensitive data**: Avoid sending passwords or personal information in error messages
- **Rate limited**: Protection against abuse and denial-of-service attempts
- **Group restrictions**: Limit collection to specific user groups if needed
- **Automatic cleanup**: Old events are automatically purged based on retention policy
- **Admin only**: Event viewing is restricted to administrators

## Troubleshooting

### No errors are being captured

1. Check that the app is enabled: `php occ app:list`
2. Verify collection settings in **Settings** → **Frontend-Insight**
3. Check browser console for the initialization message:
   ```
   frontend-insight is enabled, sending error: true, unhandledrejection: true
   ```
4. If using group restrictions, ensure the user is in an allowed group

### Events not appearing in the UI

1. Check browser console for JavaScript errors in the event browser itself
2. Verify API endpoints are accessible: `GET /apps/frontend-insight/api/1.0/events`
3. Check Nextcloud logs for PHP errors

### Rate limit errors (HTTP 429)

- Users are hitting the rate limit (20/min for authenticated, 10/min for anonymous)
- This is normal behavior to prevent abuse
- Consider if there's an error loop causing repeated submissions

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests and linters: `composer run-script lint`, `npm run check`
5. Submit a pull request

## License

This app is licensed under the **GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later)**.

```
SPDX-License-Identifier: AGPL-3.0-or-later
```

See the [LICENSE](LICENSE) file for the full license text, or visit <https://www.gnu.org/licenses/agpl-3.0.html>.

## Authors

- **Morris Jobke** - Original author
- **Magnus Anderssen** - Maintainer ([magnus@magooweb.com](mailto:magnus@magooweb.com))

## Links

- **Repository**: [github.com/callmemagnus/nextcloud-frontend-insight](https://github.com/callmemagnus/nextcloud-frontend-insight)
- **Issues**: [github.com/callmemagnus/nextcloud-frontend-insight/issues](https://github.com/callmemagnus/nextcloud-frontend-insight/issues)
- **Changelog**: [CHANGELOG.md](CHANGELOG.md)
- **Homepage**: [magnus.anderssen.ch](http://magnus.anderssen.ch)
