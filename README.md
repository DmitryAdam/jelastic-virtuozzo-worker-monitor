# Laravel Queue Monitor for Jelastic Virtuozzo

🎯 **Elegant, minimalist Laravel queue worker monitoring with auto-restart capability**

A lightweight, mobile-friendly queue monitoring solution with real-time AJAX updates, system metrics, and automated worker management - specifically designed for Jelastic Virtuozzo hosting environments.

![Queue Monitor](https://img.shields.io/badge/Laravel-Queue%20Monitor-red)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue)
![Tailwind](https://img.shields.io/badge/Tailwind-CSS-green)
![Jelastic](https://img.shields.io/badge/Jelastic-Virtuozzo-orange)

## 🤔 Why This Project?

**The Problem:** When using Jelastic Virtuozzo hosting for Laravel applications, monitoring queue workers becomes a hassle:

- 😫 **Panel Access Required**: Need to log into Jelastic panel just to check if workers are running
- 🔒 **Security Concerns**: Multiple people accessing hosting panel for simple monitoring
- ⏰ **Time Consuming**: Constant panel switching interrupts development workflow
- 📱 **No Mobile Access**: Jelastic panel isn't always mobile-friendly for quick checks

**The Solution:** A simple, web-based monitoring interface that:

- ✅ **Direct Access**: Check worker status from any browser without panel access
- ✅ **Secure**: No hosting credentials needed - runs within your application
- ✅ **Fast**: Real-time status updates without leaving your development environment
- ✅ **Mobile-Ready**: Monitor from phone/tablet during off-hours
- ✅ **Auto-Restart**: Intelligent worker recovery without manual intervention

Perfect for **Jelastic Virtuozzo users** who want simple, secure worker monitoring without the overhead of complex infrastructure tools.

## 📸 Screenshot

![Queue Monitor Interface](screenshoots/preview.webp)

*Clean, minimalist interface showing real-time worker status, system metrics, and live logs*

## ✨ Features

- 🔄 **Real-time monitoring** with smooth AJAX refresh (no page reloads)
- 📱 **Mobile-friendly** responsive design with Tailwind CSS
- 🎯 **Smart health detection** - monitors both processes and screen sessions
- 🔧 **Auto-restart capability** via cron job automation
- 📊 **System metrics** - CPU load and memory usage
- 📝 **Live logs viewer** with newest-first ordering
- 🎨 **Minimalist design** - clean, elegant interface
- 🔍 **Built-in debugging** with worker name consistency checking
- ⚡ **Lightweight** - minimal dependencies, maximum performance

## 🚀 Installation

### 1. Copy Files

```bash
# Controller
cp QueueMonitorController.php app/Http/Controllers/

# View  
cp queue-monitor.blade.php resources/views/

# Worker script
cp jelastic-worker-start.sh ./
chmod +x jelastic-worker-start.sh
```

### 2. Add Routes

Add to your `routes/web.php`:

```php
// Queue Monitor Routes
Route::get('/queue-monitor', [App\Http\Controllers\QueueMonitorController::class, 'index']);
Route::get('/queue-monitor/status', [App\Http\Controllers\QueueMonitorController::class, 'status']);
Route::get('/queue-monitor/periodic-check', [App\Http\Controllers\QueueMonitorController::class, 'periodicCheck']);
```

### 3. Setup Automation (Optional)

Add to your crontab for automatic monitoring:

```bash
# Edit crontab
crontab -e

# Add this line (replace with your domain)
* * * * * wget -q -O /dev/null http://yourdomain.com/queue-monitor/periodic-check
```

## 🎯 Usage

### Web Interface
Visit: `http://yourdomain.com/queue-monitor`

### API Endpoints
- **Status Check**: `GET /queue-monitor/status`
- **Periodic Check**: `GET /queue-monitor/periodic-check` (for cron)

### Manual Worker Management

```bash
# Start worker
./jelastic-worker-start.sh

# Check status only (no restart)
./jelastic-worker-start.sh --check-only

# Force restart
./jelastic-worker-start.sh --force

# Stop worker
screen -S your-worker-name -X quit
pkill -f "queue:work"
```

## 🔧 Configuration

### Environment Variables
The monitor automatically reads your `APP_NAME` from `.env` to generate worker names:

```env
APP_NAME="Your Laravel App"
APP_URL=http://yourdomain.com
```

### Worker Script
The `jelastic-worker-start.sh` script supports multiple modes:

- **Normal**: Start if unhealthy `./jelastic-worker-start.sh`
- **Check Only**: Status check `./jelastic-worker-start.sh --check-only`  
- **Force**: Always restart `./jelastic-worker-start.sh --force`

## 🎨 Customization

### UI Theme
The interface uses Tailwind CSS with a minimal gray/white theme. Customize by modifying the view file:

- Primary: Light gray background (`bg-gray-50`)
- Cards: White with subtle borders
- Status: Green (healthy) / Red (offline)
- Typography: Small, clean, professional

### System Metrics
Customize the system monitoring by modifying `getSystemInfo()` in the controller:

```php
private function getSystemInfo()
{
    // Add your custom metrics here
    return [
        'cpu_load' => $cpuUsage,
        'memory_usage' => $memUsage,
        // 'disk_usage' => $diskUsage,
        // 'custom_metric' => $customValue
    ];
}
```

## 🛡️ Security

- ✅ No sensitive data exposed
- ✅ Basic input sanitization
- ✅ Path traversal protection in logs
- ✅ Command injection prevention
- ⚠️ Ensure your web server has appropriate permissions
- ⚠️ Consider IP restrictions for monitoring endpoints in production

## 🔍 Troubleshooting

### Common Issues

**1. Sessions count shows 0 but screen exists**
- Check worker name consistency between PHP and bash script
- Use the built-in debug section to verify name matching

**2. Worker keeps restarting**
- Check log file permissions
- Verify `screen` is installed: `which screen`
- Test manual start: `php artisan queue:work --help`

**3. System metrics not showing**
- Verify `/proc/loadavg` and `/proc/meminfo` are readable
- Check server permissions for system file access

### Debug Information
The interface includes a collapsible debug section showing:
- APP_NAME extraction from .env
- Worker name generation
- Environment file status

## 📋 Requirements

- **PHP**: 8.0 or higher
- **Laravel**: 8.x, 9.x, 10.x, 11.x
- **System**: Linux/Unix with `/proc` filesystem
- **Tools**: `screen`, `ps`, `grep`, `tac` (or `tail`)
- **Optional**: `wget` or `curl` for cron automation

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

### Development Setup
1. Fork the repository
2. Create your feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

## 📝 License

This project is open source and available under the [MIT License](LICENSE).

## 🙏 Acknowledgments

- Built with Laravel and Tailwind CSS
- Inspired by the need for simple, elegant queue monitoring
- Designed for production environments with minimal overhead
- Using ChatGPT and Claude to code it. 

---

**⭐ Star this repo if it helped you!**