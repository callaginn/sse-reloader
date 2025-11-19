# Homebrew Apache/PHP Setup Guide

## Prerequisites

Make sure you have Homebrew's Apache and PHP installed:

```bash
brew install httpd php
```

## Setup Steps

### 1. Add Virtual Host Configuration

Add the contents of `apache-vhosts.conf` to your Apache configuration:

```bash
# Open your Apache vhosts file
nano /usr/local/etc/httpd/extra/httpd-vhosts.conf
```

Append the contents of `apache-vhosts.conf` to that file.

### 2. Enable Virtual Hosts in Apache

Edit the main Apache config:

```bash
nano /usr/local/etc/httpd/httpd.conf
```

Uncomment this line (remove the `#`):
```apache
Include /usr/local/etc/httpd/extra/httpd-vhosts.conf
```

### 3. Add Hosts Entries

Add these lines to your `/etc/hosts` file:

```bash
sudo nano /etc/hosts
```

Add:
```
127.0.0.1 sse1.test
127.0.0.1 option1.sse.test
127.0.0.1 option2.sse.test
127.0.0.1 option3.sse.test
127.0.0.1 option4.sse.test
```

### 4. Restart Apache

```bash
brew services restart httpd
```

## Access Your Sites

- **Main Dashboard**: http://sse1.test
- **Option 1**: http://option1.sse.test
- **Option 2**: http://option2.sse.test
- **Option 3**: http://option3.sse.test
- **Option 4**: http://option4.sse.test

## Troubleshooting

### Apache won't start
```bash
# Check configuration syntax
apachectl configtest

# View error logs
tail -f /usr/local/var/log/httpd/error_log
```

### PHP not working
```bash
# Check PHP is loaded in Apache
httpd -M | grep php
```

If not, add to `/usr/local/etc/httpd/httpd.conf`:
```apache
LoadModule php_module /usr/local/opt/php/lib/httpd/modules/libphp.so

<FilesMatch \.php$>
    SetHandler application/x-httpd-php
</FilesMatch>
```

### APCu not available
```bash
# Install APCu
pecl install apcu

# Find your php.ini
php --ini

# Add to php.ini
echo "extension=apcu.so" >> /usr/local/etc/php/8.2/php.ini
echo "apc.enabled=1" >> /usr/local/etc/php/8.2/php.ini
echo "apc.enable_cli=1" >> /usr/local/etc/php/8.2/php.ini

# Restart Apache
brew services restart httpd
```

### Permissions Issues
```bash
# Ensure Apache can read the files
chmod -R 755 /Users/stephen/Sites/sse1
```

## Next Steps

For detailed testing instructions, see [TESTING.md](TESTING.md).

Quick test commands:
```bash
# Option 1
yarn watch1

# Option 2  
yarn watch2

# Option 3
yarn watch3

# Option 4
yarn watch4
```

## Useful Commands

```bash
# Start Apache
brew services start httpd

# Stop Apache
brew services stop httpd

# Restart Apache
brew services restart httpd

# Check Apache status
brew services list | grep httpd

# View error logs
tail -f /usr/local/var/log/httpd/error_log
tail -f /usr/local/var/log/httpd/sse1-error_log

# Test PHP
php -v
php -m  # List loaded modules
```
