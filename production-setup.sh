#!/bin/bash

# Peerly Backend Production Setup Script
# Run this after deploying to Laravel Cloud

echo "🚀 Setting up Peerly Backend for Production..."
echo ""

# Clear all caches
echo "📦 Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
echo "✅ Caches cleared"
echo ""

# Run migrations
echo "🗄️  Running database migrations..."
php artisan migrate --force
echo "✅ Migrations complete"
echo ""

# Cache configurations for performance
echo "⚡ Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "✅ Optimizations complete"
echo ""

# Check database connection
echo "🔍 Checking database connection..."
php artisan db:show
echo ""

# Display current environment
echo "🌍 Current Environment:"
php artisan env
echo ""

# Check appointments table
echo "📊 Checking appointments table..."
php artisan tinker --execute="echo 'Total Appointments: ' . App\Models\Appointment::count() . PHP_EOL;"
echo ""

echo "✨ Setup complete!"
echo ""
echo "🔗 Your API is available at:"
echo "   https://peerly-be-main-hyer8m.laravel.cloud/api"
echo ""
echo "📱 Your admin panel is available at:"
echo "   https://peerly-be-main-hyer8m.laravel.cloud/admin"
echo ""
