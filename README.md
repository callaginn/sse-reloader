# SSE Reloader
This project shows multiple ways you can auto-refresh your app in real time using Server-Sent Events.

## 🎯 Quick Start

1. **Setup**: Follow [SETUP.md](SETUP.md) to configure Apache
2. **Test**: See [TESTING.md](TESTING.md) for detailed testing instructions
3. **Access**:
   - Option 1: http://option1.sse.test
   - Option 2: http://option2.sse.test
   - Option 3: http://option3.sse.test
   - Option 4: http://option4.sse.test

## 📁 Project Structure

```
dashboard-sse2/
├── option1/              # Version.txt polling
│   ├── public/
│   │   ├── index.php
│   │   └── sse-server.php
│   ├── version.txt
│   └── watcher.php
├── option2/              # APCu push (instant)
│   ├── public/
│   │   ├── index.php
│   │   ├── sse-server.php
│   │   └── trigger.php
│   └── watcher.php
├── option3/              # Directory snapshot
│   ├── public/
│   │   ├── index.php
│   │   └── sse-server.php
│   ├── content/
│   └── watcher.php
├── option4/              # Real-time messenger
│   └── public/
│       ├── index.php
│       ├── sse-server.php
│       ├── receiver.php
│       └── data/
│           └── messages.json
├── HOMEBREW_SETUP.md     # Setup instructions
└── TESTING.md            # Testing guide
```

## 🚀 Four Implementation Options

### Option 1: Version.txt Polling

**How it works:**
- Polls `version.txt` file every 0.5 seconds
- Detects changes via file modification time
- Broadcasts update to all connected browsers

**Pros:**
- ✅ No dependencies required
- ✅ Simple and reliable
- ✅ Easy to understand
- ✅ Works everywhere

**Cons:**
- ❌ 0.5s polling delay
- ❌ File I/O overhead

**Best for:** Simple deployments, no additional dependencies needed

**Test:**
```bash
yarn watch1
```

---

### Option 2: Instant APCu Reloader

**How it works:**
- Uses in-memory APCu cache for version storage
- Deployment script updates APCu directly
- SSE server detects change instantly (no polling delay)

**Pros:**
- ✅ Instant updates (< 10ms)
- ✅ Very fast (in-memory)
- ✅ Low CPU usage
- ✅ Clean architecture

**Cons:**
- ❌ Requires APCu extension
- ❌ CLI and web APCu are separate (uses HTTP trigger workaround)

**Best for:** Production environments, when instant updates are critical

**Test:**
```bash
yarn watch2
```

---

### Option 3: Directory Watcher

**How it works:**
- Maintains in-memory snapshot of all file modification times
- Scans directory every 1 second
- Automatically detects any file changes, additions, or deletions

**Pros:**
- ✅ Auto-detection (no manual version management)
- ✅ Shows exactly what changed
- ✅ Multi-directory support
- ✅ Detailed statistics

**Cons:**
- ❌ Higher CPU usage (directory scanning)
- ❌ 1-second scan interval

**Best for:** Development environments, automatic change detection

**Test:**
```bash
yarn watch3
```

---

### Option 4: Real-time Messenger

**How it works:**
- Multi-user chat application using SSE
- Messages stored in `messages.json` with full history
- Each tab has unique identity (via sessionStorage)
- Real-time message broadcasting to all connected clients

**Pros:**
- ✅ Real-time updates (< 100ms)
- ✅ Persistent message history
- ✅ Multiple users/tabs support
- ✅ Modern, sleek UI with gradients
- ✅ Automatic reconnection
- ✅ Message delivery tracking

**Cons:**
- ❌ File-based storage (not database)
- ❌ Limited to 100 messages in history

**Best for:** Real-time chat, collaboration tools, live messaging demos

**Test:**
```bash
# No watcher needed - just open multiple tabs!
open http://option4.sse.test
```

## 📊 Performance Comparison

| Feature | Option 1 | Option 2 | Option 3 | Option 4 |
|---------|----------|----------|----------|----------|
| **Update Speed** | ~500ms | < 10ms | ~1000ms | ~100ms |
| **CPU Usage** | Low | Very Low | Medium | Low |
| **Memory** | ~5MB | ~3MB + APCu | ~8MB | ~6MB |
| **Dependencies** | None | APCu | None | None |
| **Auto-detect** | No | No | Yes | N/A |
| **Multi-user** | No | No | No | Yes |
| **Complexity** | Simple | Medium | Medium | Medium |

## 🔧 How SSE Works

All three options use the same SSE (Server-Sent Events) mechanism:

1. **Client connects** to `sse-server.php` via `EventSource`
2. **Server sends events** using `text/event-stream` format
3. **Browser receives** real-time updates without polling
4. **Heartbeat** keeps connection alive (every 2.5 seconds)
5. **Auto-reload** when version changes detected

### SSE Event Types

- `connected` - Initial connection established
- `version` - Version update detected (triggers reload)
- `heartbeat` - Keep-alive signal

### Heartbeat Explained

The heartbeat (every 2.5 seconds) serves multiple purposes:
- Keeps connection alive through proxies
- Detects disconnections
- Prevents network timeouts
- Confirms server is responsive

## 🛠️ Integration Examples

### Option 1: File-based

```bash
#!/bin/bash
# Your deployment script
rsync -av /src/ /dest/

# Update version
echo "v2.1.0" > /path/to/version.txt
```

### Option 2: APCu Push

```bash
#!/bin/bash
# Your deployment script
rsync -av /src/ /dest/

# Trigger instant update via HTTP
curl http://option2.sse.test/trigger.php?version=v2.1.0
```

### Option 3: Automatic

```bash
#!/bin/bash
# Just deploy - changes detected automatically
rsync -av /src/ /dest/
# That's it! SSE server detects changes automatically
```

## 🎨 Customization

### Adjust Update Frequency

**Option 1** (`option1/public/sse-server.php`):
```php
usleep(500000); // 0.5 seconds (change to 1000000 for 1 second)
```

**Option 2** (`option2/public/sse-server.php`):
```php
usleep(500000); // 0.5 seconds (faster checks for instant detection)
```

**Option 3** (`option3/public/sse-server.php`):
```php
sleep(1); // 1 second (change to 2 for slower scans)
```

### Watch Additional File Types (Option 3)

Edit `option3/public/sse-server.php`:
```php
$extensions = ['php', 'html', 'css', 'js', 'json', 'txt']; // Add more
```

### Monitor Multiple Directories (Option 3)

Edit `option3/public/sse-server.php`:
```php
$watchDirs = [
    __DIR__ . '/../content',
    __DIR__ . '/../templates',
    __DIR__ . '/../config',
];
```
