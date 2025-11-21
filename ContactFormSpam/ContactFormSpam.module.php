<?php namespace ProcessWire;

/**
 * ContactFormSpam - Advanced spam protection for ProcessWire contact forms
 * 
 * ProcessWire 3.x module
 * Copyright 2025 by Your Name
 * Licensed under MIT
 */

class ContactFormSpam extends WireData implements Module {

    public static function getModuleInfo() {
        return array(
            'title' => 'Contact Form Spam Protection',
            'version' => '1.0.0',
            'summary' => 'Advanced spam protection for contact forms without third-party services',
            'author' => 'Your Name',
            'href' => '',
            'singular' => true,
            'autoload' => true,
            'requires' => array(
                'ProcessWire>=3.0.0',
                'PHP>=8.2.0'
            ),
        );
    }

    private $logFile;
    private $rateLimitFile;
    private $config;

    public function init() {
        parent::init();
        
        $this->logFile = $this->config->paths->assets . 'ContactFormSpam/logs/spam-attempts.log';
        $this->rateLimitFile = $this->config->paths->assets . 'ContactFormSpam/logs/rate-limits.json';
        
        // Ensure log directory exists
        if (!is_dir(dirname($this->logFile))) {
            wireMkdir(dirname($this->logFile));
        }
        
        // Initialize default configuration
        $this->config = array(
            'rate_limit' => 5, // submissions per hour
            'rate_window' => 3600, // 1 hour in seconds
            'min_form_time' => 3, // minimum seconds to complete form
            'max_form_time' => 3600, // maximum seconds before form expires
            'honeypot_count' => 3, // number of honeypot fields
            'spam_keywords' => array(
                'viagra', 'cialis', 'lottery', 'winner', 'congratulations',
                'click here', 'free money', 'make money', 'work from home',
                'seo', 'backlinks', 'pr0n', 'casino', 'poker', 'blackjack'
            ),
            'blocked_countries' => array(), // ISO country codes
            'log_level' => 'info' // debug, info, warning, error
        );
        
        // Add hooks
        $this->addHookBefore('Page::render', $this, 'addProtectionAssets');
    }

    /**
     * Generate CSRF token for form protection
     */
    public function generateCSRFToken() {
        $token = bin2hex(random_bytes(32));
        $this->session->set('contact_form_token', $token);
        $this->session->set('contact_form_time', time());
        return $token;
    }

    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token) {
        $sessionToken = $this->session->get('contact_form_token');
        $formTime = $this->session->get('contact_form_time');
        
        if (!$sessionToken || !$formTime) {
            return false;
        }
        
        if (!hash_equals($sessionToken, $token)) {
            return false;
        }
        
        $elapsed = time() - $formTime;
        if ($elapsed < $this->config['min_form_time'] || $elapsed > $this->config['max_form_time']) {
            return false;
        }
        
        return true;
    }

    /**
     * Generate random honeypot field names
     */
    public function generateHoneypotFields() {
        $fields = array();
        $baseNames = array('website', 'email2', 'phone2', 'address', 'company', 'comment2', 'name2', 'subject2');
        
        for ($i = 0; $i < $this->config['honeypot_count']; $i++) {
            $randomName = $baseNames[array_rand($baseNames)] . '_' . bin2hex(random_bytes(4));
            $fields[$randomName] = array(
                'type' => $i % 2 === 0 ? 'text' : 'email',
                'label' => ucfirst(str_replace('_', ' ', $randomName)),
                'css_class' => $i % 3 === 0 ? 'hp-hidden' : ($i % 3 === 1 ? 'hp-offscreen' : 'hp-display-none')
            );
        }
        
        $this->session->set('honeypot_fields', array_keys($fields));
        return $fields;
    }

    /**
     * Validate honeypot fields
     */
    public function validateHoneypots($input) {
        $honeypotFields = $this->session->get('honeypot_fields');
        if (!$honeypotFields) {
            return false;
        }
        
        foreach ($honeypotFields as $field) {
            if (isset($input[$field]) && !empty($input[$field])) {
                return false; // Honeypot field was filled
            }
        }
        
        return true;
    }

    /**
     * Check rate limiting by IP
     */
    public function checkRateLimit($ip) {
        $rateData = array();
        
        if (file_exists($this->rateLimitFile)) {
            $rateData = json_decode(file_get_contents($this->rateLimitFile), true) ?: array();
        }
        
        $now = time();
        $windowStart = $now - $this->config['rate_window'];
        
        // Clean old entries
        if (isset($rateData[$ip])) {
            $rateData[$ip] = array_filter($rateData[$ip], function($timestamp) use ($windowStart) {
                return $timestamp > $windowStart;
            });
        }
        
        // Check current rate
        if (isset($rateData[$ip]) && count($rateData[$ip]) >= $this->config['rate_limit']) {
            return false;
        }
        
        // Add current submission
        if (!isset($rateData[$ip])) {
            $rateData[$ip] = array();
        }
        $rateData[$ip][] = $now;
        
        // Save updated data
        file_put_contents($this->rateLimitFile, json_encode($rateData));
        
        return true;
    }

    /**
     * Analyze content for spam patterns
     */
    public function analyzeContent($name, $email, $message) {
        $content = strtolower($name . ' ' . $email . ' ' . $message);
        
        // Check for spam keywords
        foreach ($this->config['spam_keywords'] as $keyword) {
            if (strpos($content, strtolower($keyword)) !== false) {
                return false;
            }
        }
        
        // Check for excessive links
        $linkCount = preg_match_all('/https?:\/\//', $content);
        if ($linkCount > 2) {
            return false;
        }
        
        // Check for excessive capitalization
        $upperRatio = preg_match_all('/[A-Z]/', $message) / max(strlen($message), 1);
        if ($upperRatio > 0.5) {
            return false;
        }
        
        // Check for repetitive characters
        if (preg_match('/(.)\1{4,}/', $message)) {
            return false;
        }
        
        return true;
    }

    /**
     * Validate HTTP headers
     */
    public function validateHeaders() {
        // Check User-Agent
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }
        
        // Check Referer (should be from same domain)
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $refererHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
            $currentHost = $_SERVER['HTTP_HOST'];
            if ($refererHost !== $currentHost) {
                return false;
            }
        }
        
        // Check request method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return false;
        }
        
        return true;
    }

    /**
     * Generate browser fingerprint
     */
    public function generateFingerprint() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        
        return hash('sha256', $userAgent . $acceptLanguage . $acceptEncoding);
    }

    /**
     * Log spam attempts
     */
    public function logSpamAttempt($reason, $data = array()) {
        $logEntry = array(
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'reason' => $reason,
            'data' => $data
        );
        
        $logLine = json_encode($logEntry) . "\n";
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Also log to ProcessWire if level is appropriate
        if ($this->config['log_level'] === 'debug' || in_array($reason, array('rate_limit', 'csrf_invalid'))) {
            $this->log->save('contact-spam', "Spam attempt blocked: {$reason} from {$_SERVER['REMOTE_ADDR']}");
        }
    }

    /**
     * Main validation function
     */
    public function validateSubmission($data) {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Check rate limiting
        if (!$this->checkRateLimit($ip)) {
            $this->logSpamAttempt('rate_limit', $data);
            return array('success' => false, 'reason' => 'Rate limit exceeded');
        }
        
        // Validate CSRF token
        if (!$this->validateCSRFToken($data['csrf_token'] ?? '')) {
            $this->logSpamAttempt('csrf_invalid', $data);
            return array('success' => false, 'reason' => 'Invalid security token');
        }
        
        // Validate honeypots
        if (!$this->validateHoneypots($data)) {
            $this->logSpamAttempt('honeypot_triggered', $data);
            return array('success' => false, 'reason' => 'Form validation failed');
        }
        
        // Validate headers
        if (!$this->validateHeaders()) {
            $this->logSpamAttempt('invalid_headers', $data);
            return array('success' => false, 'reason' => 'Invalid request headers');
        }
        
        // Analyze content
        if (!$this->analyzeContent($data['name'] ?? '', $data['email'] ?? '', $data['message'] ?? '')) {
            $this->logSpamAttempt('spam_content', $data);
            return array('success' => false, 'reason' => 'Content appears to be spam');
        }
        
        return array('success' => true);
    }

    /**
     * Add protection assets to page
     */
    public function addProtectionAssets(HookEvent $event) {
        $page = $event->object;
        
        // Only add to contact form pages
        if ($page->template->name === 'contact-form') {
            $config = $this->wire('config');
            $urls = $config->urls;
            
            // Add CSS
            $config->styles->add($urls->modules . 'ContactFormSpam/assets/css/spam-protection.css');
            
            // Add JavaScript
            $config->scripts->add($urls->modules . 'ContactFormSpam/assets/js/form-protection.js');
        }
    }

    /**
     * Get spam statistics
     */
    public function getSpamStats($hours = 24) {
        if (!file_exists($this->logFile)) {
            return array('total' => 0, 'by_reason' => array(), 'by_hour' => array());
        }
        
        $stats = array('total' => 0, 'by_reason' => array(), 'by_hour' => array());
        $cutoff = time() - ($hours * 3600);
        
        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!$entry) continue;
            
            $timestamp = strtotime($entry['timestamp']);
            if ($timestamp < $cutoff) continue;
            
            $stats['total']++;
            $reason = $entry['reason'];
            $hour = date('H', $timestamp);
            
            $stats['by_reason'][$reason] = ($stats['by_reason'][$reason] ?? 0) + 1;
            $stats['by_hour'][$hour] = ($stats['by_hour'][$hour] ?? 0) + 1;
        }
        
        return $stats;
    }

    /**
     * Clean up old log files
     */
    public function cleanupLogs($days = 30) {
        $cutoff = time() - ($days * 24 * 3600);
        
        if (file_exists($this->logFile)) {
            $tempFile = $this->logFile . '.tmp';
            $input = fopen($this->logFile, 'r');
            $output = fopen($tempFile, 'w');
            
            while (($line = fgets($input)) !== false) {
                $entry = json_decode(trim($line), true);
                if ($entry && strtotime($entry['timestamp']) > $cutoff) {
                    fwrite($output, $line);
                }
            }
            
            fclose($input);
            fclose($output);
            rename($tempFile, $this->logFile);
        }
        
        // Clean up rate limit data
        if (file_exists($this->rateLimitFile)) {
            $rateData = json_decode(file_get_contents($this->rateLimitFile), true) ?: array();
            $windowStart = $cutoff;
            
            foreach ($rateData as $ip => $timestamps) {
                $rateData[$ip] = array_filter($timestamps, function($timestamp) use ($windowStart) {
                    return $timestamp > $windowStart;
                });
                
                if (empty($rateData[$ip])) {
                    unset($rateData[$ip]);
                }
            }
            
            file_put_contents($this->rateLimitFile, json_encode($rateData));
        }
    }
}