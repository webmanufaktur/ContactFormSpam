<?php namespace ProcessWire;

/**
 * Contact Form Handler Template
 * 
 * This template handles AJAX form submissions with comprehensive spam validation
 * and returns JSON responses for frontend processing.
 */

// Set content type to JSON
header('Content-Type: application/json');

// Get the spam protection module
$spamProtection = $modules->get('ContactFormSpam');

// Initialize response array
$response = array(
    'success' => false,
    'message' => '',
    'errors' => array(),
    'data' => array()
);

try {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new \Exception('Invalid request method');
    }
    
    // Verify CSRF token first
    $csrfToken = $input->post->csrf_token;
    if (!$spamProtection->validateCSRFToken($csrfToken)) {
        throw new \Exception('Security token validation failed');
    }
    
    // Collect and sanitize form data
    $formData = array(
        'name' => $sanitizer->text($input->post->name),
        'email' => $sanitizer->email($input->post->email),
        'subject' => $sanitizer->text($input->post->subject),
        'message' => $sanitizer->textarea($input->post->message),
        'csrf_token' => $csrfToken,
        'math_answer' => $input->post->math_answer,
        'form_timestamp' => $input->post->form_timestamp,
        'client_timing' => $input->post->client_timing
    );
    
    // Add honeypot field data
    $honeypotFields = $session->get('honeypot_fields');
    if ($honeypotFields) {
        foreach ($honeypotFields as $fieldName) {
            $formData[$fieldName] = $input->post->{$fieldName};
        }
    }
    
    // Validate required fields
    $requiredFields = array('name', 'email', 'subject', 'message', 'math_answer');
    foreach ($requiredFields as $field) {
        if (empty($formData[$field])) {
            $response['errors'][$field] = ucfirst($field) . ' is required';
        }
    }
    
    // Validate email format
    if (!empty($formData['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $response['errors']['email'] = 'Please enter a valid email address';
    }
    
    // Validate name length
    if (!empty($formData['name']) && strlen($formData['name']) < 2) {
        $response['errors']['name'] = 'Name must be at least 2 characters long';
    }
    
    // Validate message length
    if (!empty($formData['message'])) {
        if (strlen($formData['message']) < 10) {
            $response['errors']['message'] = 'Message must be at least 10 characters long';
        } elseif (strlen($formData['message']) > 5000) {
            $response['errors']['message'] = 'Message must be less than 5000 characters';
        }
    }
    
    // If there are validation errors, return them
    if (!empty($response['errors'])) {
        $response['message'] = 'Please correct the errors below';
        echo json_encode($response);
        exit;
    }
    
    // Validate math challenge
    if (!validateMathChallenge($session, $formData['math_answer'])) {
        throw new \Exception('Incorrect answer to the math question');
    }
    
    // Validate client-side timing data
    if (!validateClientTiming($formData['client_timing'])) {
        throw new \Exception('Form submission appears to be automated');
    }
    
    // Run comprehensive spam validation
    $spamValidation = $spamProtection->validateSubmission($formData);
    if (!$spamValidation['success']) {
        throw new \Exception($spamValidation['reason']);
    }
    
    // Process the legitimate submission
    processContactSubmission($page, $config, $log, $session, $formData);
    
    // Success response
    $response['success'] = true;
    $response['message'] = 'Thank you for your message! We will get back to you soon.';
    $response['data'] = array(
        'submission_id' => generateSubmissionId(),
        'timestamp' => date('Y-m-d H:i:s')
    );
    
} catch (\Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    $response['errors']['general'] = $e->getMessage();
    
    // Log the error for debugging
    $log->save('contact-handler-error', $e->getMessage() . ' - IP: ' . $_SERVER['REMOTE_ADDR']);
}

// Return JSON response
echo json_encode($response);
exit;

/**
 * Validate math challenge answer
 */
function validateMathChallenge($session, $userAnswer) {
    $correctAnswer = $session->get('math_answer');
    $challengeTime = $session->get('math_challenge_time');
    
    if (!$correctAnswer || !$challengeTime) {
        return false;
    }
    
    // Check if challenge was answered too quickly (bot behavior)
    if (time() - $challengeTime < 2) {
        return false;
    }
    
    return (int)$userAnswer === $correctAnswer;
}

/**
 * Validate client-side timing data
 */
function validateClientTiming($timingData) {
    if (empty($timingData)) {
        return false;
    }
    
    $timing = json_decode($timingData, true);
    if (!$timing) {
        return false;
    }
    
    // Check minimum form time
    if (!isset($timing['formTime']) || $timing['formTime'] < 3000) {
        return false;
    }
    
    // Check maximum form time
    if ($timing['formTime'] > 3600000) {
        return false;
    }
    
    // Check for reasonable interaction patterns
    if (!isset($timing['interactions']) || $timing['interactions'] < 3) {
        return false;
    }
    
    // Check for bot-like behavior
    if (isset($timing['mouseMovements']) && isset($timing['keyPresses'])) {
        if ($timing['mouseMovements'] < 5 && $timing['keyPresses'] < 10) {
            return false;
        }
    }
    
    return true;
}

/**
 * Process legitimate contact submission
 */
function processContactSubmission($page, $config, $log, $session, $data) {
    // Send email notification
    $mail = $page->wire('mail');
    $mail->to($page->contact_email ?: $config->adminEmail);
    $mail->from($data['email']);
    $mail->subject('Contact Form: ' . $data['subject']);
    
    // Create email body
    $emailBody = "You have received a new contact form submission:\n\n";
    $emailBody .= "Name: {$data['name']}\n";
    $emailBody .= "Email: {$data['email']}\n";
    $emailBody .= "Subject: {$data['subject']}\n\n";
    $emailBody .= "Message:\n{$data['message']}\n\n";
    
    // Add submission details
    $emailBody .= "--- Submission Details ---\n";
    $emailBody .= "IP Address: {$_SERVER['REMOTE_ADDR']}\n";
    $emailBody .= "User Agent: {$_SERVER['HTTP_USER_AGENT']}\n";
    $emailBody .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    
    // Add timing information if available
    if (!empty($data['client_timing'])) {
        $timing = json_decode($data['client_timing'], true);
        if ($timing) {
            $emailBody .= "Form Time: " . round($timing['formTime'] / 1000, 2) . " seconds\n";
            $emailBody .= "Interactions: {$timing['interactions']}\n";
        }
    }
    
    $mail->body($emailBody);
    
    // Send email
    if (!$mail->send()) {
        throw new \Exception('Failed to send email notification');
    }
    
    // Log legitimate submission
    $log->save('contact-form', "Contact form submission from {$data['name']} ({$data['email']}) - IP: {$_SERVER['REMOTE_ADDR']}");
    
    // Store submission in database (optional)
    storeSubmission($page, $data);
    
    // Clear session data
    $session->remove('math_answer');
    $session->remove('math_challenge_time');
    $session->remove('honeypot_fields');
    $session->remove('contact_form_token');
    $session->remove('contact_form_time');
}

/**
 * Store submission in ProcessWire database
 */
function storeSubmission($page, $data) {
    try {
        // Create a new page for the submission (optional)
        $submissionPage = $page->wire('pages')->newPage();
        $submissionPage->template = 'contact-submission';
        $submissionPage->parent = $page;
        $submissionPage->title = "Submission from {$data['name']} - " . date('Y-m-d H:i:s');
        
        // Set field values (assuming these fields exist in the template)
        if ($submissionPage->fields->get('submission_name')) {
            $submissionPage->submission_name = $data['name'];
        }
        if ($submissionPage->fields->get('submission_email')) {
            $submissionPage->submission_email = $data['email'];
        }
        if ($submissionPage->fields->get('submission_subject')) {
            $submissionPage->submission_subject = $data['subject'];
        }
        if ($submissionPage->fields->get('submission_message')) {
            $submissionPage->submission_message = $data['message'];
        }
        if ($submissionPage->fields->get('submission_ip')) {
            $submissionPage->submission_ip = $_SERVER['REMOTE_ADDR'];
        }
        if ($submissionPage->fields->get('submission_status')) {
            $submissionPage->submission_status = 'new';
        }
        
        $submissionPage->addStatus($page->wire('pages')->statusHidden);
        $submissionPage->save();
        
    } catch (\Exception $e) {
        // Log error but don't fail the submission
        $page->wire('log')->save('contact-submission-error', $e->getMessage());
    }
}

/**
 * Generate unique submission ID
 */
function generateSubmissionId() {
    return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
}