# Admin Dashboard Documentation

## Overview

The Card-o-Bot Admin Dashboard is a comprehensive database and user management system accessible only to administrators. It provides tools to manage users, browse and edit database tables, and monitor system status.

## Access

The admin dashboard is only accessible to users with admin privileges. Admin status is determined by:
1. **Global Admin**: Users logged in with credentials from `.env` file (`ADMIN_USERNAME` and `ADMIN_PASSWORD`)
2. **Database Admin**: Users with `is_admin = 1` in the `cardobot_users` table

## Pages

### 1. Admin Dashboard (`/admin/dashboard.php`)

Main entry point for all admin functions. Provides quick access to:
- User Management
- Database Browser
- System Status

### 2. User Management (`/admin/users.php`)

Comprehensive user management interface with three views:

#### User List
- View all users in the system
- See user details: ID, username, email, name, auth method, password status, Google linking, admin status, creation date, last login
- Quick actions: Edit or Delete users

#### Create User
- Create new user accounts
- Set username, password, email, full name
- Option to make user an admin
- Automatic validation of username and password requirements

#### Edit User
- Modify user information
- Update username, email, full name
- Change password (optional)
- Toggle admin status
- View account information (auth method, creation date, etc.)
- Delete user (with confirmation)

**Features:**
- Prevents deleting your own account
- Requires typing "DELETE" to confirm deletion
- Real-time validation
- Error handling and success messages

### 3. Database Browser (`/admin/database.php`)

Powerful database management tool with three modes:

#### Tables View
- List all database tables
- Quick access to view any table

#### Table View
- View table data with pagination (50 rows per page)
- See table information (row count, engine, collation)
- Edit individual rows
- Delete rows (with confirmation)
- Responsive table design

#### SQL Query Interface
- Execute SELECT, UPDATE, INSERT, DELETE queries
- Safety restrictions: Only allows SELECT, UPDATE, INSERT, DELETE, SHOW, DESCRIBE, and EXPLAIN
- DDL statements (DROP, ALTER, CREATE) are disabled for safety
- Query results displayed in formatted tables

**Security Features:**
- Query type validation
- Prepared statements for all operations
- Confirmation required for destructive actions

### 4. System Status (`/admin/status.php`)

Monitor system health and configuration:

#### Database Statistics
- Total users count
- Total cards count
- Active sessions count
- Row counts for all tables

#### Environment Configuration
- OpenAI API key status
- OpenAI model configuration
- Database connection details
- Google OAuth configuration status

#### PHP Information
- PHP version
- Server software
- Document root
- Current domain
- Session status

#### Database Connection
- Connection status
- Database name, user, MySQL version

## Security

### Access Control
- All admin pages check `is_admin()` before allowing access
- Non-admin users are redirected to the main app
- Admin status verified on every page load

### Data Protection
- Prepared statements used for all database queries
- Input validation and sanitization
- Query type restrictions in SQL interface
- Confirmation required for destructive actions
- Password hashing for user passwords

### Best Practices
- Never expose sensitive data in error messages
- All database operations use transactions where appropriate
- Error logging for debugging without exposing details
- CSRF protection through session validation

## Styling

All admin pages use the Card-o-Bot design system:
- CSS variables from `variables.css`
- Base styles from `base.css`
- Responsive design with mobile-first approach
- Consistent with main application styling

## Navigation

Admin pages include consistent navigation:
- Header with current page title
- User info display
- Links to Dashboard, App, and Logout
- Breadcrumb-style tabs for page sections

## Usage Examples

### Creating a New User
1. Navigate to Admin Dashboard
2. Click "Manage Users"
3. Click "Create User" tab
4. Fill in username, password, and optional fields
5. Check "Make this user an admin" if needed
6. Click "Create User"

### Editing a User
1. Go to User Management
2. Find user in the list
3. Click "Edit" button
4. Modify fields as needed
5. Click "Update User"

### Viewing Database Table
1. Go to Database Browser
2. Click on a table name or "View" button
3. Browse data with pagination
4. Use "Edit" or "Delete" buttons for individual rows

### Running SQL Query
1. Go to Database Browser
2. Click "SQL Query" tab
3. Enter your query (SELECT, UPDATE, INSERT, or DELETE)
4. Click "Execute Query"
5. View results or confirmation message

## Troubleshooting

### "Access Denied" Error
- Verify you're logged in as an admin
- Check that your account has `is_admin = 1` in database
- Or use global admin credentials from `.env`

### Database Connection Failed
- Check `.env` file for correct database credentials
- Verify database exists and is accessible
- Check database user permissions

### Query Execution Errors
- Ensure query syntax is correct
- Check that only allowed query types are used
- Verify table and column names exist
- Check for foreign key constraints

## Future Enhancements

Potential additions:
- User activity logs
- Bulk user operations
- Database backup/restore
- Export/import functionality
- Advanced search and filtering
- Audit trail for admin actions
