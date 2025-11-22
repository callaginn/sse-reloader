# SSE Update System 🚀
Four ways to blast real-time updates into your app without melting your server (probably). 🙈

Unlike JS-PHP polling — which pokes your server every half second like a toddler on espresso 🤣 — SSE keeps one chill connection and only sends updates when something actually changes. Less bandwidth, lighter server load, faster updates, and zero nagging “Are we there yet?” messages. 😎

## Options

### 1. Version.txt Polling 🎸

The "classic rock" of SSE methods: simple, dependable, and does exactly what it says. It checks a `version.txt` file and broadcasts updates like a polite town crier.

- No dependencies
- Simple and reliable
- Works anywhere, including mysterious legacy environments

### 2. Instant APCu Reloader 🤯

For people who want updates *yesterday*. Uses APCu's in-memory cache so updates fire instantly, no polling needed. Think of it as a caffeine-powered change detector.

- Instant update delivery
- Extremely low CPU usage
- Clean, efficient architecture that sparks joy

### 3. File Watcher Reloader 👀

Your very own file detective. It keeps an in-memory snapshot of multiple directories and tattles immediately when something changes. Great when you want receipts.

- Automatic detection of changes
- Multi-directory monitoring
- Provides detailed change insights (a little nosy, in a good way)

### 4. Git-Based Trigger 🔀

The repo whisperer. It watches your HEAD like a hawk and shouts “Update!” whenever you commit, pull, merge, or checkout. No polling, no extra files, just pure version-control magic. Great for pipelines and anyone who lives in `git status`.

- Fires only on real code changes (no false drama)
- Plays nice with normal git workflows
- No temp watcher files cluttering your zen
- Deployment-friendly (CI gives it a high-five)
- Surprisingly low-maintenance for something this nosy

## Setup

1. Install DDEV (https://ddev.readthedocs.io)
2. Clone this repository
3. Start the project:
    ``` bash
    ddev start
    ```

## Quick Start

Choose your adventure:<br>
- **Option 1**: http://option1.ddev.site
- **Option 2**: http://option2.ddev.site
- **Option 3**: http://option3.ddev.site
- **Option 4**: http://option4.ddev.site

Fire up the watchers:

``` bash
# Option 1
ddev yarn watch1

# Option 2
ddev yarn watch2

# Option 3
ddev yarn watch3

# Option 4 (no watcher needed!)
git commit --allow-empty -m "test update"
```

## DDEV Troubleshooting
When something inevitably decides not to cooperate:

**Check DDEV status:**
``` bash
ddev describe
```

**Restart DDEV (the universal fix):**
``` bash
ddev restart
```

**View logs (a treasure hunt for errors):**
``` bash
ddev logs
```

**SSH into the container like a pro:**
``` bash
ddev ssh
```

**When all else fails, rebuild your life ... or your environment:**
``` bash
ddev poweroff && ddev start
```
