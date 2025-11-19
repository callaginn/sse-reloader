## 🐛 Troubleshooting

### SSE Connection Failed

**Check Apache is running:**
```bash
brew services list | grep httpd
```

**Check virtual hosts:**
```bash
apachectl -S
```

**View error logs:**
```bash
tail -f /usr/local/var/log/httpd/error_log
```

### Option 2: APCu Not Working

**Verify APCu is installed:**
```bash
php -m | grep apcu
```

**Check APCu status via web:**
```bash
open http://option2.sse.test/apcu-test.php
```

**Install if missing:**
```bash
pecl install apcu
echo "extension=apcu.so" >> /usr/local/etc/php/8.2/php.ini
echo "apc.enabled=1" >> /usr/local/etc/php/8.2/php.ini
echo "apc.enable_cli=1" >> /usr/local/etc/php/8.2/php.ini
brew services restart httpd
```

### Updates Not Detected

**Check file permissions:**
```bash
ls -la /Users/stephen/Sites/dashboard-sse2/option1/version.txt
ls -la /Users/stephen/Sites/dashboard-sse2/option2/public/
ls -la /Users/stephen/Sites/dashboard-sse2/option3/content/
ls -la /Users/stephen/Sites/dashboard-sse2/option4/public/messages.json
```

**Test SSE server directly:**
```bash
curl http://option1.sse.test/sse-server.php
curl http://option4.sse.test/sse-server.php
```
