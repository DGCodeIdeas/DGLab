# DGLab PWA - Deployment Guide

This guide covers deploying DGLab PWA to various hosting platforms, with special focus on InfinityFree.

## Table of Contents

1. [InfinityFree Deployment](#infinityfree-deployment)
2. [General Shared Hosting](#general-shared-hosting)
3. [VPS/Dedicated Server](#vpsdedicated-server)
4. [Post-Deployment Tasks](#post-deployment-tasks)
5. [Troubleshooting](#troubleshooting)

---

## InfinityFree Deployment

### Prerequisites

- InfinityFree account (free at infinityfree.net)
- FTP client (FileZilla recommended) or use File Manager
- Basic understanding of file management

### Step 1: Create Account

1. Visit [infinityfree.net](https://infinityfree.net)
2. Click "Sign Up" and create an account
3. Verify your email address
4. Log in to the client area

### Step 2: Create Hosting Account

1. In the client area, click "Create Account"
2. Choose a subdomain (e.g., `yourname.epizy.com`)
3. Select "Create Account"
4. Wait for account activation (usually instant)

### Step 3: Create Database

1. Go to "MySQL Databases" in the control panel
2. Create a new database:
   - Database Name: `dglab` (will become `epiz_xxx_dglab`)
   - Click "Create Database"
3. Note down:
   - Database name
   - Database username (usually same as database name)
   - Database host (usually `sqlXXX.epizy.com`)

### Step 4: Upload Files

#### Option A: Using File Manager (Recommended for beginners)

1. In the control panel, click "Online File Manager"
2. Navigate to `htdocs` folder
3. Delete the default `index.html` file
4. Upload all DGLab PWA files:
   - Click "Upload"
   - Select all files from your local `dglab-pwa` folder
   - Wait for upload to complete

#### Option B: Using FTP

1. Get FTP credentials from the control panel:
   - FTP Hostname: `ftpupload.net`
   - FTP Username: Your hosting account username
   - FTP Password: Your hosting account password
   - FTP Port: `21`

2. In FileZilla:
   - Host: `ftpupload.net`
   - Username: Your username
   - Password: Your password
   - Port: `21`
   - Click "Quickconnect"

3. Navigate to `/htdocs` on the remote side
4. Upload all files from your local `dglab-pwa` folder

### Step 5: Configure Application

1. In File Manager, navigate to `config/` folder
2. Copy `config.example.php` to `config.php`
3. Edit `config.php`:

```php
<?php
return [
    'app' => [
        'name'        => 'DGLab PWA',
        'version'     => '1.0.0',
        'env'         => 'production',
        'debug'       => false,
        'base_url'    => 'https://yourname.epizy.com',
        'timezone'    => 'UTC',
    ],
    
    'database' => [
        'enabled'     => true,
        'driver'      => 'mysql',
        'host'        => 'sqlXXX.epizy.com',  // Your MySQL host
        'port'        => 3306,
        'database'    => 'epiz_xxx_dglab',     // Your database name
        'username'    => 'epiz_xxx',           // Your database username
        'password'    => 'your_password',      // Your database password
        'charset'     => 'utf8mb4',
    ],
    
    'upload' => [
        'chunk_size'      => 1024 * 1024,      // 1MB
        'max_file_size'   => 50 * 1024 * 1024, // 50MB (InfinityFree limit)
    ],
];
```

### Step 6: Set Permissions

In File Manager:
1. Select the `storage` folder
2. Click "Permissions" or right-click → "Change Permissions"
3. Set to `755` (or `777` if 755 doesn't work)
4. Apply recursively to all subdirectories

### Step 7: Test Installation

1. Visit your subdomain: `https://yourname.epizy.com`
2. You should see the DGLab PWA homepage
3. Test uploading a file to verify everything works

---

## General Shared Hosting

### Requirements

- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite
- 100MB+ disk space

### Upload Steps

1. **Compress Files**: Create a ZIP of all DGLab PWA files
2. **Upload**: Use cPanel File Manager or FTP to upload
3. **Extract**: Extract the ZIP in your web root (usually `public_html/`)
4. **Configure**: Edit `config/config.php` with your database details
5. **Permissions**: Set `storage/` directory to 755

### Database Setup

Using cPanel:
1. Go to "MySQL Database Wizard"
2. Create database: `username_dglab`
3. Create user and password
4. Add user to database with ALL PRIVILEGES

---

## VPS/Dedicated Server

### Requirements

- Ubuntu 20.04+ or CentOS 8+
- Apache 2.4+ or Nginx
- PHP 8.0+ with FPM
- MySQL 8.0+ or MariaDB 10.5+
- Composer (optional)

### Installation Steps

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Apache
sudo apt install apache2 -y

# Install PHP
sudo apt install php8.1 php8.1-fpm php8.1-mysql php8.1-zip php8.1-gd php8.1-mbstring php8.1-xml -y

# Install MySQL
sudo apt install mysql-server -y

# Secure MySQL
sudo mysql_secure_installation

# Create database
sudo mysql -e "CREATE DATABASE dglab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'dglab'@'localhost' IDENTIFIED BY 'strong_password';"
sudo mysql -e "GRANT ALL PRIVILEGES ON dglab.* TO 'dglab'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

# Enable Apache modules
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod expires
sudo a2enmod deflate

# Configure Apache
sudo nano /etc/apache2/sites-available/dglab.conf
```

Apache configuration:
```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/dglab/public
    
    <Directory /var/www/dglab/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/dglab-error.log
    CustomLog ${APACHE_LOG_DIR}/dglab-access.log combined
</VirtualHost>
```

```bash
# Enable site
sudo a2ensite dglab
sudo systemctl reload apache2

# Upload files
# Use SCP, SFTP, or Git to upload files to /var/www/dglab

# Set permissions
sudo chown -R www-data:www-data /var/www/dglab
sudo chmod -R 755 /var/www/dglab/storage

# Configure application
sudo nano /var/www/dglab/config/config.php
```

---

## Post-Deployment Tasks

### 1. Clear Caches

Delete all files in:
- `storage/cache/views/`
- `storage/cache/assets/`

### 2. Test All Features

- Homepage loads correctly
- Tool listing page works
- Individual tool pages work
- File upload works
- Processing completes successfully
- Download works

### 3. Enable SSL (HTTPS)

#### InfinityFree
- SSL is automatically enabled
- Visit `https://yourname.epizy.com`

#### cPanel
1. Go to "SSL/TLS Status"
2. Run "AutoSSL" or install certificate

#### Let's Encrypt (VPS)
```bash
sudo apt install certbot python3-certbot-apache -y
sudo certbot --apache -d yourdomain.com
```

### 4. Configure PWA Icons

Upload icon files to `public/assets/icons/`:
- icon-72x72.png
- icon-96x96.png
- icon-128x128.png
- icon-144x144.png
- icon-152x152.png
- icon-192x192.png
- icon-384x384.png
- icon-512x512.png

### 5. Set Up Backups

Configure regular backups of:
- Database
- `storage/exports/` folder
- Configuration files

---

## Troubleshooting

### 404 Errors on All Pages

**Cause**: mod_rewrite not enabled or .htaccess not working

**Solution**:
1. Check `.htaccess` exists in `public/` folder
2. Ensure Apache mod_rewrite is enabled
3. Check `AllowOverride All` in Apache config

### Database Connection Failed

**Cause**: Wrong database credentials

**Solution**:
1. Verify database host, name, username, password
2. Test connection with simple PHP script
3. Check if database user has proper privileges

### Uploads Not Working

**Cause**: Directory permissions or PHP limits

**Solution**:
1. Set `storage/` permissions to 755 or 777
2. Check PHP `upload_max_filesize` and `post_max_size`
3. Verify `storage/uploads/` and `storage/chunks/` exist

### CSS/JS Not Loading

**Cause**: Asset cache issue or wrong paths

**Solution**:
1. Clear asset cache: delete `storage/cache/assets/*`
2. Check browser console for 404 errors
3. Verify `base_url` in config is correct

### White Screen / 500 Error

**Cause**: PHP error

**Solution**:
1. Enable debug mode temporarily in config
2. Check PHP error logs
3. Verify PHP version is 8.0+

### PWA Not Installing

**Cause**: Manifest or service worker issue

**Solution**:
1. Check `/manifest.json` loads correctly
2. Verify `/sw.js` loads without errors
3. Ensure site is served over HTTPS
4. Check browser console for errors

---

## Performance Tips

### Enable Compression

In `.htaccess` (already included):
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript
</IfModule>
```

### Enable Caching

In `.htaccess` (already included):
```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 week"
    ExpiresByType application/javascript "access plus 1 week"
</IfModule>
```

### Optimize Images

- Use WebP format where possible
- Compress images before uploading
- Use appropriate sizes for icons

---

## Security Checklist

- [ ] Change default database credentials
- [ ] Use strong passwords
- [ ] Enable HTTPS
- [ ] Set proper file permissions (not 777)
- [ ] Keep PHP and server software updated
- [ ] Disable debug mode in production
- [ ] Configure proper error reporting
- [ ] Set up regular backups

---

## Support

For deployment issues:
1. Check this documentation
2. Review error logs
3. Consult hosting provider documentation
4. Open an issue on GitHub

---

*Last updated: 2024*
