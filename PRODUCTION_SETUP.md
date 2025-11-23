# Peerly API - Production Setup Guide

## ✅ Pre-Production Checklist

Your Laravel API is now configured and ready for production deployment. Below is everything you need to know.

---

## 🔧 Configuration Summary

### ✅ Completed Setup

1. **Authentication**: Laravel Sanctum configured for token-based API authentication
2. **CORS**: Configured to accept requests from frontend applications
3. **Middleware**: Auth middleware applied to all protected routes
4. **Database**: All migrations run successfully
5. **Controllers**: All API controllers properly namespaced and functional

### 📁 API Endpoints

#### Public Routes (No Authentication)
```
POST /api/register - Register new user
POST /api/login    - Login and get token
```

#### Protected Routes (Requires Bearer Token)
```
GET    /api/user                          - Get authenticated user
POST   /api/logout                        - Logout (revoke token)

# V1 API Resources
GET    /api/v1/appointments               - List appointments
POST   /api/v1/appointments               - Create appointment
GET    /api/v1/appointments/{id}          - View appointment
PUT    /api/v1/appointments/{id}          - Update appointment
DELETE /api/v1/appointments/{id}          - Delete appointment

# Same pattern for:
- /api/v1/conversations
- /api/v1/groups
- /api/v1/messages
- /api/v1/notifications
- /api/v1/profiles
- /api/v1/reports
- /api/v1/resources
- /api/v1/therapists
```

---

## 🚀 Frontend Integration

### Authentication Flow

1. **Register/Login**
```javascript
// Register
const response = await fetch('http://your-api.com/api/register', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    name: 'John Doe',
    email: 'john@example.com',
    password: 'password123',
    password_confirmation: 'password123'
  })
});
const data = await response.json();
const token = data.token; // Store this token

// Login
const response = await fetch('http://your-api.com/api/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'john@example.com',
    password: 'password123'
  })
});
const data = await response.json();
const token = data.token; // Store this token
```

2. **Making Authenticated Requests**
```javascript
const response = await fetch('http://your-api.com/api/v1/messages', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});
```

3. **Logout**
```javascript
await fetch('http://your-api.com/api/logout', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  }
});
// Clear stored token from localStorage/sessionStorage
```

---

## 🔐 Production Environment Variables

Update your `.env` file for production:

```dotenv
# Application
APP_NAME="Peerly"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-api-domain.com

# Frontend URL (your React/Vue/etc app)
FRONTEND_URL=https://your-frontend-domain.com

# Sanctum stateful domains (if using cookie-based auth for SPA)
SANCTUM_STATEFUL_DOMAINS=your-frontend-domain.com

# Database (use production credentials)
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=your_production_db
DB_USERNAME=your_db_user
DB_PASSWORD=your_secure_password

# Cache & Session (recommended: redis for production)
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=your-redis-host
REDIS_PASSWORD=your-redis-password
REDIS_PORT=6379

# Mail (configure your mail service)
MAIL_MAILER=smtp
MAIL_HOST=your-mail-host
MAIL_PORT=587
MAIL_USERNAME=your-mail-username
MAIL_PASSWORD=your-mail-password
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

---

## 📦 Deployment Steps

### 1. Server Requirements
- PHP 8.2+
- MySQL 8.0+ or PostgreSQL
- Composer
- Redis (recommended)
- Node.js & NPM (for asset compilation)

### 2. Deploy Commands
```bash
# Clone repository
git clone your-repo-url
cd peerlyapp

# Install dependencies
composer install --optimize-autoloader --no-dev

# Set up environment
cp .env.example .env
php artisan key:generate

# Update .env with production values (see above)

# Run migrations
php artisan migrate --force

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
chmod -R 755 storage bootstrap/cache
```

### 3. Web Server Configuration

#### Nginx Example
```nginx
server {
    listen 80;
    server_name your-api-domain.com;
    root /path/to/peerlyapp/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 🔒 Security Checklist

- [ ] Set `APP_DEBUG=false` in production
- [ ] Set `APP_ENV=production`
- [ ] Use strong `APP_KEY` (auto-generated)
- [ ] Use HTTPS/SSL for production API
- [ ] Configure proper CORS origins (not `*` in production)
- [ ] Use strong database passwords
- [ ] Enable rate limiting (consider adding throttle middleware)
- [ ] Set up proper logging and monitoring
- [ ] Regular backups of database
- [ ] Keep dependencies updated (`composer update`)

---

## 🧪 Testing API Endpoints

### Using cURL
```bash
# Register
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"password123","password_confirmation":"password123"}'

# Login
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'

# Access protected route
curl -X GET http://localhost:8000/api/v1/messages \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

### Using Postman
1. Create a new request
2. Set method to POST/GET as needed
3. Add header: `Authorization: Bearer YOUR_TOKEN`
4. Add header: `Accept: application/json`
5. For POST/PUT: Add header `Content-Type: application/json`

---

## 📊 Monitoring & Maintenance

### Recommended Tools
- **Laravel Telescope**: Development debugging
- **Laravel Horizon**: Queue monitoring (if using queues)
- **Sentry**: Error tracking
- **New Relic/DataDog**: Performance monitoring

### Regular Maintenance
```bash
# Clear and rebuild caches
php artisan cache:clear
php artisan config:cache
php artisan route:cache

# Run queue workers (if using)
php artisan queue:work --tries=3

# Schedule runner (add to cron)
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🆘 Troubleshooting

### CORS Issues
- Ensure `FRONTEND_URL` is set correctly in `.env`
- Check browser console for specific CORS errors
- Verify frontend is sending proper headers

### 401 Unauthorized
- Check token is being sent: `Authorization: Bearer TOKEN`
- Verify token hasn't expired
- Ensure user hasn't been deleted

### 500 Server Error
- Check `storage/logs/laravel.log`
- Ensure storage and cache directories are writable
- Verify database connection

---

## 📞 Support

For issues or questions:
1. Check Laravel documentation: https://laravel.com/docs
2. Check Sanctum documentation: https://laravel.com/docs/sanctum
3. Review application logs in `storage/logs/`

---

**Your API is production-ready! 🎉**
