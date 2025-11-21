<?php namespace ProcessWire;

/**
 * Contact Form Template with Advanced Spam Protection
 */

// Get the spam protection module
$spamProtection = $modules->get('ContactFormSpam');

// Generate protection tokens and fields
$csrfToken = $spamProtection->generateCSRFToken();
$honeypotFields = $spamProtection->generateHoneypotFields();
$mathChallenge = generateMathChallenge($session);

// Form submission handling
$success = false;
$error = '';
$formData = array();

if ($input->post->submit_contact) {
    $formData = array(
        'name' => $sanitizer->text($input->post->name),
        'email' => $sanitizer->email($input->post->email),
        'subject' => $sanitizer->text($input->post->subject),
        'message' => $sanitizer->textarea($input->post->message),
        'csrf_token' => $input->post->csrf_token,
        'math_answer' => $input->post->math_answer,
        'form_timestamp' => $input->post->form_timestamp
    );
    
    // Add honeypot field data
    foreach ($honeypotFields as $fieldName => $fieldConfig) {
        $formData[$fieldName] = $input->post->{$fieldName};
    }
    
    // Validate math challenge
    if (!validateMathChallenge($session, $formData['math_answer'])) {
        $error = 'Incorrect answer to the math question. Please try again.';
    } else {
        // Validate with spam protection
        $validation = $spamProtection->validateSubmission($formData);
        
        if ($validation['success']) {
            // Process the legitimate submission
            processContactSubmission($page, $config, $log, $session, $formData);
            $success = true;
        } else {
            $error = $validation['reason'];
        }
    }
}

// Helper function to generate math challenge
function generateMathChallenge($session) {
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $operators = array('+', '-');
    $operator = $operators[array_rand($operators)];
    
    if ($operator === '+') {
        $answer = $num1 + $num2;
    } else {
        $answer = $num1 - $num2;
    }
    
    // Store answer in session with obfuscation
    $session->set('math_answer', $answer);
    $session->set('math_challenge_time', time());
    
    return array(
        'question' => "What is {$num1} {$operator} {$num2}?",
        'obfuscated_question' => obfuscateMathQuestion("What is {$num1} {$operator} {$num2}?")
    );
}

// Helper function to validate math challenge
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

// Helper function to obfuscate math question
function obfuscateMathQuestion($question) {
    // Split question and obfuscate numbers
    return preg_replace_callback('/(\d+)/', function($matches) {
        $num = (int)$matches[1];
        $obfuscated = '';
        $digits = str_split($num);
        
        foreach ($digits as $digit) {
            $obfuscated .= '<span class="math-digit" data-value="' . $digit . '">' . 
                          str_repeat('●', rand(2, 4)) . '</span>';
        }
        
        return $obfuscated;
    }, $question);
}

// Helper function to process legitimate submission
function processContactSubmission($page, $config, $log, $session, $data) {
    // Send email notification
    $mail = $page->wire('mail');
    $mail->to($page->contact_email ?: $config->adminEmail);
    $mail->from($data['email']);
    $mail->subject('Contact Form: ' . $data['subject']);
    $mail->body("Name: {$data['name']}\nEmail: {$data['email']}\n\nMessage:\n{$data['message']}");
    $mail->send();
    
    // Log legitimate submission
    $log->save('contact-form', "Contact form submission from {$data['name']} ({$data['email']})");
    
    // Clear form data
    $session->remove('math_answer');
    $session->remove('math_challenge_time');
    $session->remove('honeypot_fields');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page->title; ?></title>
    <style>
        .contact-form {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .form-group textarea {
            height: 150px;
            resize: vertical;
        }
        .btn-submit {
            background-color: #007cba;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-submit:hover {
            background-color: #005a87;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .math-challenge {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .math-question {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .required {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="contact-form">
        <h1><?php echo $page->title; ?></h1>
        
        <?php if ($page->summary): ?>
            <p><?php echo $page->summary; ?></p>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message">
                Thank you for your message! We'll get back to you soon.
            </div>
        <?php else: ?>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form id="contactForm" method="post" action="<?php echo $page->url; ?>">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="form_timestamp" value="<?php echo time(); ?>">
                
                <!-- Honeypot Fields (will be hidden by CSS) -->
                <?php foreach ($honeypotFields as $fieldName => $fieldConfig): ?>
                    <div class="form-group hp-field <?php echo $fieldConfig['css_class']; ?>">
                        <label for="<?php echo $fieldName; ?>"><?php echo $fieldConfig['label']; ?></label>
                        <input type="<?php echo $fieldConfig['type']; ?>" 
                               id="<?php echo $fieldName; ?>" 
                               name="<?php echo $fieldName; ?>" 
                               value=""
                               autocomplete="off"
                               tabindex="-1">
                    </div>
                <?php endforeach; ?>
                
                <!-- Legitimate Form Fields -->
                <div class="form-group">
                    <label for="name">Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" required 
                           value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>"
                           autocomplete="name">
                </div>
                
                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                           autocomplete="email">
                </div>
                
                <div class="form-group">
                    <label for="subject">Subject <span class="required">*</span></label>
                    <input type="text" id="subject" name="subject" required 
                           value="<?php echo htmlspecialchars($formData['subject'] ?? ''); ?>"
                           autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="message">Message <span class="required">*</span></label>
                    <textarea id="message" name="message" required 
                              autocomplete="off"><?php echo htmlspecialchars($formData['message'] ?? ''); ?></textarea>
                </div>
                
                <!-- Math Challenge -->
                <div class="math-challenge">
                    <div class="math-question">
                        <?php echo $mathChallenge['obfuscated_question']; ?>
                    </div>
                    <div class="form-group">
                        <label for="math_answer">Answer <span class="required">*</span></label>
                        <input type="text" id="math_answer" name="math_answer" required 
                               autocomplete="off">
                    </div>
                </div>
                
                <!-- JavaScript-dependent submit button -->
                <noscript>
                    <div class="error-message">
                        JavaScript is required to submit this form. Please enable JavaScript in your browser.
                    </div>
                </noscript>
                
                <button type="submit" name="submit_contact" class="btn-submit js-required" style="display: none;">
                    Send Message
                </button>
                
                <!-- Fallback button for bots (will be hidden by JavaScript) -->
                <button type="submit" name="submit_bot" class="btn-submit bot-fallback">
                    Send Message
                </button>
            </form>
            
        <?php endif; ?>
    </div>
    
    <!-- JavaScript will be loaded by the module -->
</body>
</html>