# Contact Form Spam Protection for ProcessWire

A comprehensive spam protection system for ProcessWire contact forms using only core ProcessWire and PHP 8.2 features, without any third-party services.

## Features

### Multi-Layer Protection
- **Dynamic Honeypots**: Randomized field names with 8 different hiding techniques
- **Rate Limiting**: IP-based submission limits with configurable windows
- **CSRF Protection**: Rotating security tokens
- **Content Analysis**: Pattern detection for spam keywords and behaviors
- **Timing Validation**: Minimum/maximum form completion time checks
- **Math Challenges**: Interactive arithmetic questions with obfuscation
- **JavaScript Dependency**: Form only works with JS enabled
- **Header Validation**: User-Agent and Referer verification

### Advanced Features
- **Browser Fingerprinting**: Request pattern analysis
- **Progressive Disclosure**: Multi-step form validation
- **Anti-Debugging**: Developer tools detection
- **Behavioral Analysis**: Mouse movement and keyboard tracking
- **Admin Interface**: Real-time monitoring and statistics
- **Comprehensive Logging**: Detailed attempt tracking
- **Export Functionality**: CSV export of spam logs

## Installation

### 1. Copy Module Files
Copy the entire `ContactFormSpam` directory to:
```
/site/modules/ContactFormSpam/
```

### 2. Install in ProcessWire Admin
1. Login to ProcessWire admin
2. Go to **Modules** → **Site** → **Install**
3. Find "Contact Form Spam Protection" and click **Install**
4. Configure the module settings as needed

### 3. Create Contact Form Template
1. Create a new template called `contact-form`
2. Set the template file to use the provided contact form template
3. Create a page using this template

### 4. Configure Email Settings
Add a `contact_email` field to your contact page or set the admin email in ProcessWire config.

## Usage

### Basic Contact Form
Create a page using the `contact-form` template. The form will automatically include all spam protection features.

### Custom Integration
```php
<?php
// Get the spam protection module
$spamProtection = $modules->get('ContactFormSpam');

// Generate protection tokens
$csrfToken = $spamProtection->generateCSRFToken();
$honeypotFields = $spamProtection->generateHoneypotFields();

// Validate submission
$validation = $spamProtection->validateSubmission($_POST);
if ($validation['success']) {
    // Process legitimate submission
    processContactSubmission($_POST);
} else {
    // Handle spam attempt
    echo "Blocked: " . $validation['reason'];
}
?>
```

### AJAX Form Handling
Use the provided `contact-handler.php` template for AJAX submissions:
```javascript
fetch('/contact-handler/', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams(formData)
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        // Handle success
    } else {
        // Handle errors
    }
});
```

## Configuration

### Module Settings
Configure these settings in the module configuration or admin interface:

- **Rate Limit**: Submissions per hour (default: 5)
- **Rate Window**: Time window in seconds (default: 3600)
- **Min Form Time**: Minimum seconds to complete form (default: 3)
- **Max Form Time**: Maximum seconds before form expires (default: 3600)
- **Honeypot Count**: Number of honeypot fields (default: 3)
- **Log Level**: Debug, Info, Warning, or Error (default: Info)

### Spam Keywords
Add custom spam keywords to the configuration:
```php
$spamProtection->config['spam_keywords'][] = 'custom-spam-term';
```

### Blocked Countries
Block submissions from specific countries:
```php
$spamProtection->config['blocked_countries'] = array('CN', 'RU', 'BR');
```

## Admin Interface

Access the admin interface at `/admin-contact-spam/` to:
- View real-time spam statistics
- Monitor recent spam attempts
- Export spam logs to CSV
- Configure protection settings
- Clean up old log files

## File Structure

```
/site/modules/ContactFormSpam/
├── ContactFormSpam.module              # Main module file
├── templates/
│   ├── contact-form.php               # Contact form template
│   ├── contact-handler.php            # AJAX form handler
│   └── admin-interface.php            # Admin monitoring interface
├── assets/
│   ├── css/spam-protection.css        # Protection styles
│   └── js/form-protection.js          # JavaScript protection
└── logs/
    ├── spam-attempts.log              # Spam attempt logs
    └── rate-limits.json               # Rate limiting data
```

## Security Features

### Honeypot Techniques
1. **Display None**: Basic CSS hiding
2. **Off-screen**: Position outside viewport
3. **Hidden Visibility**: Visibility hidden
4. **Transform**: CSS transform hiding
5. **Z-index**: Negative z-index
6. **Clip Path**: CSS clip rectangle
7. **Font Size**: Zero font size
8. **Opacity**: Near-zero opacity

### Validation Layers
1. **Client-side**: JavaScript behavioral analysis
2. **Server-side**: PHP pattern matching
3. **Timing**: Form completion speed
4. **Interaction**: Mouse/keyboard tracking
5. **Headers**: Request validation
6. **Rate**: IP-based limiting

## Performance Optimization

- **Minimal Database Queries**: Uses file-based storage
- **Efficient Logging**: JSON format with rotation
- **Lazy Loading**: Protection loads on-demand
- **Caching**: Validation rules cached in memory
- **Optimized Regex**: Efficient pattern matching

## Monitoring and Maintenance

### Log Management
- Automatic log rotation (30 days default)
- Configurable cleanup intervals
- Export functionality for analysis
- Real-time monitoring dashboard

### Statistics Tracking
- Hourly/daily/monthly statistics
- Block reason categorization
- IP address tracking
- Geographic analysis (optional)

## Troubleshooting

### Common Issues

**Form not submitting:**
- Check JavaScript is enabled
- Verify CSRF token is present
- Ensure all required fields are filled

**Legitimate submissions blocked:**
- Check rate limiting settings
- Verify timing validation
- Review spam keyword list

**Admin interface not accessible:**
- Verify user permissions
- Check module installation
- Ensure template files exist

### Debug Mode
Enable debug logging:
```php
$spamProtection->config['log_level'] = 'debug';
```

Check logs in:
- ProcessWire admin logs
- `/site/assets/ContactFormSpam/logs/spam-attempts.log`

## API Reference

### Main Methods

```php
// Generate CSRF token
$token = $spamProtection->generateCSRFToken();

// Generate honeypot fields
$fields = $spamProtection->generateHoneypotFields();

// Validate submission
$result = $spamProtection->validateSubmission($data);

// Get statistics
$stats = $spamProtection->getSpamStats(24); // Last 24 hours

// Clean up logs
$spamProtection->cleanupLogs(30); // 30 days
```

### Response Format

```php
// Successful validation
array(
    'success' => true
)

// Failed validation
array(
    'success' => false,
    'reason' => 'Rate limit exceeded'
)
```

## License

MIT License - feel free to use and modify for your projects.

## Support

For issues and questions:
1. Check the troubleshooting section
2. Review the ProcessWire logs
3. Enable debug mode for detailed information
4. Check the admin interface for statistics

## Version History

### v1.0.0
- Initial release
- Multi-layer spam protection
- Admin interface
- Comprehensive logging
- AJAX support
- Mobile responsive design