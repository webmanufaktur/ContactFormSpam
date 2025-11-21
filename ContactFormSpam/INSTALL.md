# Installation Guide

## Quick Start

1. **Copy Module Files**
   ```bash
   cp -r ContactFormSpam /path/to/processwire/site/modules/
   ```

2. **Install in ProcessWire**
   - Admin → Modules → Site → Install
   - Find "Contact Form Spam Protection"
   - Click Install

3. **Create Contact Page**
   - Create new page with "contact-form" template
   - Set contact email in page settings

4. **Test the Form**
   - Visit the contact page
   - Verify all protection layers are working

## Detailed Installation

### Prerequisites
- ProcessWire 3.0+
- PHP 8.2+
- Write permissions for `/site/assets/`

### File Permissions
```bash
chmod 755 /site/modules/ContactFormSpam/
chmod 755 /site/modules/ContactFormSpam/logs/
chmod 644 /site/modules/ContactFormSpam/assets/css/spam-protection.css
chmod 644 /site/modules/ContactFormSpam/assets/js/form-protection.js
```

### Template Setup
1. Copy `contact-form.php` to your templates directory
2. Create template in ProcessWire admin
3. Assign template to contact page

### Email Configuration
Add to `/site/config.php`:
```php
$config->adminEmail = 'your-email@example.com';
```

Or set `contact_email` field on contact page.

## Verification

### Test Spam Protection
1. Try submitting form too quickly (should be blocked)
2. Fill honeypot fields (should be blocked)
3. Submit without JavaScript (should be blocked)
4. Use legitimate submission (should succeed)

### Check Admin Interface
Visit `/admin-contact-spam/` to monitor:
- Spam statistics
- Recent attempts
- Protection status

## Troubleshooting

### Module Not Installing
- Check PHP version (8.2+ required)
- Verify file permissions
- Check ProcessWire version compatibility

### Form Not Working
- Verify JavaScript is enabled
- Check browser console for errors
- Ensure CSS files are loading
- Verify module is installed and active

### Emails Not Sending
- Check ProcessWire email configuration
- Verify SMTP settings if used
- Check spam folder
- Test with simple WireMail call

### Logs Not Writing
- Check directory permissions
- Verify log directory exists
- Check disk space
- Test file write permissions