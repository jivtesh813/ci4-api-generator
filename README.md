# CodeIgniter 4 API Generator

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://php.net)
[![CodeIgniter](https://img.shields.io/badge/CodeIgniter-^4.0-orange.svg)](https://codeigniter.com)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-green.svg)](LICENSE)

Automatically generate RESTful APIs for your CodeIgniter 4 application based on your MySQL database tables. No code required! Just install, configure your database, and your APIs are ready to use.

## ✨ Features

- **Instant API Generation**: APIs work immediately after installation - no additional setup required
- **Full CRUD Operations**: Automatic GET, POST, PUT, DELETE endpoints for all tables
- **Composite Primary Key Support**: Automatically handles tables with single or multiple primary keys
- **Smart Validation**: Auto-generates validation rules from database schema
- **OpenAPI/Swagger Documentation**: Beautiful interactive API documentation with Scalar UI
- **Pagination Support**: Built-in pagination for listing endpoints
- **Advanced Filtering**: Filter records by any column via query parameters
- **Multi-tenant Support**: Built-in column filtering for multi-tenant applications
- **Flexible Configuration**: Customize everything - endpoints, columns, validation rules per table
- **Foreign Key Support**: Automatically detects relationships
- **Route Caching**: Fast performance with intelligent route caching
- **CLI Commands**: Powerful commands to manage and inspect your APIs

## 📋 Requirements

- PHP 8.1 or higher
- CodeIgniter 4.x
- MySQL/MariaDB database

## 🚀 Installation

Install via Composer:

```bash
composer require jivtesh/ci4-api-generator
```

That's it! The package uses CodeIgniter's auto-discovery feature. Your APIs are now live at `http://yoursite.com/api/v1/`.

## 🎯 Quick Start

### 1. Install the Package

```bash
composer require jivtesh/ci4-api-generator
```

### 2. Configure Database

Make sure your database is configured in `app/Config/Database.php`:

```php
public array $default = [
    'hostname' => 'localhost',
    'username' => 'your_username',
    'password' => 'your_password',
    'database' => 'your_database',
    'DBDriver' => 'MySQLi',
    // ... other settings
];
```

### 3. Test Your APIs

Your APIs are now available! If you have a table named `users`, you can access:

```bash
# List all users
GET http://yoursite.com/api/v1/users

# Get a specific user
GET http://yoursite.com/api/v1/users/1

# Create a new user
POST http://yoursite.com/api/v1/users

# Update a user
PUT http://yoursite.com/api/v1/users/1

# Delete a user
DELETE http://yoursite.com/api/v1/users/1
```

### 4. View Available Routes

```bash
php spark api:list
```

### 5. Generate API Documentation

```bash
php spark api:generate --openapi
```
This generates the file openapi.json in writable-->api-docs folderm which is used by Scalar in View file to create api documentation.

Then visit `http://yoursite.com/api/v1/docs` to see your interactive API documentation.

## 📚 Available Endpoints

For each table in your database, the following endpoints are automatically created:

| Method | Endpoint | Description | Example |
|--------|----------|-------------|---------|
| GET | `/api/v1/{table}` | List all records (paginated) | `/api/v1/users` |
| GET | `/api/v1/{table}/{id}` | Get a single record | `/api/v1/users/1` |
| GET | `/api/v1/{table}/{id1}/{id2}` | Get a single record (composite key) | `/api/v1/payments/103/HQ336336` |
| POST | `/api/v1/{table}` | Create a new record | `/api/v1/users` |
| PUT | `/api/v1/{table}/{id}` | Update a record | `/api/v1/users/1` |
| PUT | `/api/v1/{table}/{id1}/{id2}` | Update a record (composite key) | `/api/v1/payments/103/HQ336336` |
| DELETE | `/api/v1/{table}/{id}` | Delete a record | `/api/v1/users/1` |
| DELETE | `/api/v1/{table}/{id1}/{id2}` | Delete a record (composite key) | `/api/v1/payments/103/HQ336336` |

**Notes**: 
- Table names with underscores are converted to hyphens in URLs (e.g., `user_profiles` → `/api/v1/user-profiles`)
- The package automatically detects composite primary keys and generates appropriate routes
- For composite keys, provide all key values in order as defined in the database

## 🔧 Configuration (Optional)

By default, the package works with zero configuration. However, you can customize behavior by creating a config file.

### Create Custom Configuration

1. Create `app/Config/ApiGenerator.php`:

```php
<?php

namespace Config;

use JivteshGhatora\Ci4ApiGenerator\Config\ApiGenerator as BaseApiGenerator;

class ApiGenerator extends BaseApiGenerator
{
    // Override any settings here
}
```

### Configuration Options

#### Basic Settings

```php
/**
 * API prefix - the base URL path for all API endpoints
 */
public string $apiPrefix = 'api/v1';

/**
 * Enable/disable API documentation
 */
public bool $apiDocumentationEnabled = true;

/**
 * API documentation URL path
 */
public string $apiDocumentationPath = 'api/v1/docs';

/**
 * Route cache TTL in seconds (default: 1 hour)
 */
public int $maxCacheAge = 3600;
```

#### Table Selection

```php
/**
 * Generate APIs only for specific tables (empty = all tables)
 */
public array $tables = [];
// Example: public array $tables = ['users', 'posts', 'comments'];

/**
 * Exclude specific tables from API generation
 */
public array $excludeTables = ['migrations', 'ci_sessions'];
```

#### Enable/Disable Endpoints Per Table

```php
/**
 * Default endpoints for all tables
 * Options: 'index', 'show', 'create', 'update', 'delete'
 */
public array $defaultEndpoints = ['index', 'show', 'create', 'update', 'delete'];

/**
 * Customize endpoints for specific tables
 */
public array $enabledEndpoints = [
    'users' => ['index', 'show', 'update'],  // Read-only + update for users
    'logs' => ['index', 'show'],              // Read-only for logs
    'posts' => ['index', 'show', 'create', 'update', 'delete']  // Full CRUD for posts
];
```

#### Restrict Visible Columns

```php
/**
 * Control which columns are returned in API responses
 * Empty array = all columns (default)
 */
public array $enabledColumnsToShow = [
    'users' => ['id', 'name', 'email', 'created_at'],  // Hide password, etc.
    'posts' => ['id', 'title', 'content', 'author_id', 'created_at']
];
```

#### Custom Validation Rules

```php
/**
 * Override auto-generated validation rules
 * Format: ['table_name' => ['column' => 'validation_rules']]
 */
public array $validationRules = [
    'users' => [
        'email' => 'required|valid_email|is_unique[users.email]',
        'username' => 'required|min_length[3]|max_length[20]|alpha_numeric',
        'age' => 'required|integer|greater_than_equal_to[18]'
    ],
    'posts' => [
        'title' => 'required|min_length[5]|max_length[200]',
        'content' => 'required|min_length[10]'
    ]
];
```

#### Multi-Tenant Filtering

```php
/**
 * Automatically filter queries by specific columns
 * Perfect for multi-tenant applications
 */
public array $multiTenantColumns = [
    'company_id' => '123',  // All queries will filter by company_id = 123
    'tenant_id' => '456'
];

/**
 * Exclude specific tables from multi-tenant filtering
 */
public array $multiTenantExcludeTables = ['settings', 'system_logs'];
```

**Dynamic Multi-Tenant Example**:

```php
// In your BaseController or filter
$config = config('ApiGenerator');
$config->multiTenantColumns = [
    'company_id' => session('user_company_id')  // Dynamically set from session
];
```

#### Pagination Settings

```php
/**
 * Default number of records per page
 */
public int $perPage = 20;

/**
 * Maximum allowed records per page
 */
public int $maxPerPage = 100;
```

### Complete Configuration Example

```php
<?php

namespace Config;

use JivteshGhatora\Ci4ApiGenerator\Config\ApiGenerator as BaseApiGenerator;

class ApiGenerator extends BaseApiGenerator
{
    public string $apiPrefix = 'api/v2';
    
    public array $excludeTables = ['migrations', 'ci_sessions', 'password_resets'];
    
    public array $enabledEndpoints = [
        'users' => ['index', 'show', 'update'],
        'posts' => ['index', 'show', 'create', 'update', 'delete'],
        'comments' => ['index', 'show', 'create', 'delete']
    ];
    
    public array $enabledColumnsToShow = [
        'users' => ['id', 'username', 'email', 'created_at']
    ];
    
    public array $validationRules = [
        'users' => [
            'email' => 'required|valid_email|is_unique[users.email]',
            'username' => 'required|min_length[3]|max_length[20]'
        ]
    ];
    
    public array $multiTenantColumns = [
        'company_id' => null  // Set dynamically in your app
    ];
    
    public int $perPage = 50;
    public int $maxPerPage = 200;
}
```

## 🎨 API Usage Examples

### Listing Records (GET)

```bash
# Get all users (paginated)
GET /api/v1/users

# With pagination
GET /api/v1/users?page=2&per_page=10

# With filtering
GET /api/v1/users?status=active&role=admin
```

**Response**:
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "created_at": "2024-01-15 10:30:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 45,
    "last_page": 3
  }
}
```

### Get Single Record (GET)

#### Single Primary Key

```bash
GET /api/v1/users/1
```

**Response**:
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "created_at": "2024-01-15 10:30:00"
  }
}
```

#### Composite Primary Key

For tables with composite primary keys (e.g., `payments` table with `customerNumber` and `checkNumber`):

```bash
GET /api/v1/payments/103/HQ336336
```

**Response**:
```json
{
  "status": "success",
  "data": {
    "customerNumber": "103",
    "checkNumber": "HQ336336",
    "paymentDate": "2004-10-19",
    "amount": "6066.78"
  }
}
```

**Note**: The order of primary key values in the URL must match the order defined in the database schema.

### Create Record (POST)

```bash
POST /api/v1/users
Content-Type: application/json

{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "password": "secure123"
}
```

**Response**:
```json
{
  "status": "success",
  "message": "Record created successfully",
  "id": 2
}
```

### Update Record (PUT)

#### Single Primary Key

```bash
PUT /api/v1/users/1
Content-Type: application/json

{
  "name": "John Updated",
  "email": "john.updated@example.com"
}
```

**Response**:
```json
{
  "status": "success",
  "message": "Record updated successfully"
}
```

#### Composite Primary Key

```bash
PUT /api/v1/payments/103/HQ336336
Content-Type: application/json

{
  "paymentDate": "2004-10-20",
  "amount": "7000.00"
}
```

**Response**:
```json
{
  "status": "success",
  "message": "Record updated successfully"
}
```

### Delete Record (DELETE)

#### Single Primary Key

```bash
DELETE /api/v1/users/1
```

**Response**:
```json
{
  "status": "success",
  "message": "Record deleted successfully"
}
```

#### Composite Primary Key

```bash
DELETE /api/v1/payments/103/HQ336336
```

**Response**:
```json
{
  "status": "success",
  "message": "Record deleted successfully"
}
```

## 🖥️ CLI Commands

### List All Available APIs

```bash
php spark api:list
```

**Output**:
```
Available API Endpoints:

+---------+--------------------------------+-------+----------+
| Method  | Endpoint                       | Table | Action   |
+---------+--------------------------------+-------+----------+
| GET     | http://site.com/api/v1/users   | users | List all |
| GET     | http://site.com/api/v1/users/1 | users | Get one  |
| POST    | http://site.com/api/v1/users   | users | Create   |
| PUT     | http://site.com/api/v1/users/1 | users | Update   |
| DELETE  | http://site.com/api/v1/users/1 | users | Delete   |
+---------+--------------------------------+-------+----------+

Total endpoints: 5
```

### List APIs for Specific Table

```bash
php spark api:list users
```

### Generate API Documentation

```bash
# Generate OpenAPI/Swagger specification
php spark api:generate --openapi

# Generate route cache
php spark api:generate --cache

# Generate everything
php spark api:generate --all
```

### Refresh Everything

```bash
# Clear cache and regenerate all APIs
php spark api:refresh
```

## 📖 API Documentation

The package includes beautiful, interactive API documentation powered by Scalar.

### Accessing Documentation

1. Generate the OpenAPI specification:
```bash
php spark api:generate --openapi
```

2. Visit the documentation URL:
```
http://yoursite.com/api/v1/docs
```

### Features of Documentation

- **Interactive Testing**: Test APIs directly from the browser
- **Request/Response Examples**: See example requests and responses
- **Schema Visualization**: View table structures and relationships
- **Authentication Testing**: Built-in request builder
- **Search Functionality**: Quickly find endpoints

### Customizing Documentation Path

```php
public string $apiDocumentationPath = 'api/docs';  // Custom path
```

### Disabling Documentation

```php
public bool $apiDocumentationEnabled = false;
```

## 🔒 Security & Best Practices

### Authentication & Authorization

This package provides the API infrastructure. You should add authentication:

1. **Create an Authentication Filter** (`app/Filters/ApiAuthFilter.php`):

```php
<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ApiAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $token = $request->getHeaderLine('Authorization');
        
        // Validate token
        if (!$this->isValidToken($token)) {
            return service('response')
                ->setJSON(['error' => 'Unauthorized'])
                ->setStatusCode(401);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }

    private function isValidToken($token): bool
    {
        // Your token validation logic
        return !empty($token);
    }
}
```

2. **Register Filter** in `app/Config/Filters.php`:

```php
public array $aliases = [
    'api-auth' => \App\Filters\ApiAuthFilter::class,
];

public array $filters = [
    'api-auth' => ['before' => ['api/*']],
];
```

### Recommended Security Practices

1. **Always use HTTPS in production**
2. **Implement rate limiting** (use CodeIgniter's Throttle filter)
3. **Validate and sanitize all inputs** (auto-handled by validation rules)
4. **Use strong validation rules** for sensitive fields
5. **Hide sensitive columns** using `enabledColumnsToShow`
6. **Restrict endpoints** as needed via `enabledEndpoints`
7. **Implement proper CORS policies** if accessed from browsers
8. **Use multi-tenant filtering** for data isolation

## 🛠️ Advanced Usage

### Dynamic Configuration

You can modify configuration at runtime:

```php
// In a controller or filter
$config = config('ApiGenerator');
$config->multiTenantColumns['company_id'] = session('user_company_id');
```

### Custom Validation Logic

Extend the ApiController for complex validation:

```php
<?php

namespace App\Controllers\Api;

use JivteshGhatora\Ci4ApiGenerator\Controllers\ApiController as BaseApiController;

class CustomApiController extends BaseApiController
{
    protected function beforeCreate($data)
    {
        // Add custom logic before creating
        $data['created_by'] = session('user_id');
        return $data;
    }
}
```

### Soft Deletes Support

The package automatically detects `deleted_at` columns and enables soft deletes.

### Timestamp Handling

Automatically detects and handles `created_at` and `updated_at` columns.

## 🐛 Troubleshooting

### APIs Not Working

1. **Check if composer autoload is updated**:
```bash
composer dump-autoload
```

2. **Verify database connection**:
```bash
php spark db:table tablename
```

3. **Check routes are registered**:
```bash
php spark routes
```

4. **Clear cache**:
```bash
php spark api:refresh
```

### 404 Errors

1. Ensure mod_rewrite is enabled (Apache)
2. Check `.htaccess` file exists in public folder
3. Verify `$baseURL` in `app/Config/App.php`

### Validation Errors

- Check your custom validation rules syntax
- Ensure column names match database exactly
- Review auto-generated rules: `php spark api:generate`

### Performance Issues

- Enable route caching: `php spark api:generate --cache`
- Increase `maxCacheAge` in configuration
- Add database indexes on frequently queried columns

## 📊 Response Formats

### Success Response

```json
{
  "status": "success",
  "data": { /* your data */ }
}
```

### Error Responses

```json
{
  "status": 400,
  "error": 400,
  "messages": {
    "error": "Validation failed",
    "field_name": "Field is required"
  }
}
```

### HTTP Status Codes

- `200 OK` - Successful GET, PUT, DELETE
- `201 Created` - Successful POST
- `400 Bad Request` - Validation error
- `404 Not Found` - Record not found
- `403 Forbidden` - Endpoint disabled
- `500 Internal Server Error` - Server error

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📝 License

This package is open-sourced software licensed under the [BSD-3-Clause license](LICENSE).

## 👤 Author

**Jivtesh Ghatora**
- Email: jivtesh813@outlook.com
- GitHub: [@jivtesh813](https://github.com/jivtesh813)

## 🙏 Acknowledgments

- Built for CodeIgniter 4 framework
- Uses Scalar for beautiful API documentation
- Inspired by the need for rapid API development

---

**Star ⭐ this repository if you find it helpful!**
