/**
 * JavaScript Protection Layer for Contact Form Spam Prevention
 * 
 * This script provides multiple layers of client-side protection:
 * - Form timing validation
 * - Keyboard interaction tracking
 * - Mouse movement detection
 * - Tab navigation tracking
 * - Field focus/blur monitoring
 * - Submit button visibility control
 */

(function() {
    'use strict';
    
    // Configuration
    const config = {
        minFormTime: 3000, // Minimum time before form can be submitted (ms)
        maxFormTime: 3600000, // Maximum time before form expires (ms)
        requiredInteractions: 3, // Minimum number of user interactions
        mathRevealDelay: 1000, // Delay before revealing math challenge
        submitButtonRevealDelay: 2000 // Delay before showing submit button
    };
    
    // Form state tracking
    let formState = {
        startTime: null,
        interactions: 0,
        mouseMovements: 0,
        keyPresses: 0,
        tabPresses: 0,
        fieldFocuses: 0,
        hasScrolled: false,
        hasInteracted: false,
        mathRevealed: false,
        submitRevealed: false
    };
    
    // Initialize form protection
    function initFormProtection() {
        const form = document.getElementById('contactForm');
        if (!form) return;
        
        formState.startTime = Date.now();
        
        // Hide bot fallback button
        const botButton = form.querySelector('.bot-fallback');
        if (botButton) {
            botButton.style.display = 'none';
        }
        
        // Setup event listeners
        setupEventListeners(form);
        
        // Start reveal timers
        setTimeout(revealMathChallenge, config.mathRevealDelay);
        setTimeout(revealSubmitButton, config.submitButtonRevealDelay);
        
        // Setup form submission validation
        form.addEventListener('submit', validateFormSubmission);
        
        // Setup math digit interaction
        setupMathDigits();
    }
    
    // Setup event listeners for interaction tracking
    function setupEventListeners(form) {
        // Mouse movement tracking
        document.addEventListener('mousemove', throttle(function() {
            formState.mouseMovements++;
            formState.interactions++;
        }, 100));
        
        // Keyboard tracking
        document.addEventListener('keydown', function(e) {
            formState.keyPresses++;
            formState.interactions++;
            
            // Track tab navigation
            if (e.key === 'Tab') {
                formState.tabPresses++;
            }
        });
        
        // Scroll tracking
        window.addEventListener('scroll', throttle(function() {
            formState.hasScrolled = true;
            formState.interactions++;
        }, 100));
        
        // Field focus tracking
        const inputs = form.querySelectorAll('input:not([type="hidden"]), textarea');
        inputs.forEach(function(input) {
            input.addEventListener('focus', function() {
                formState.fieldFocuses++;
                formState.interactions++;
                formState.hasInteracted = true;
            });
            
            // Track typing patterns
            input.addEventListener('input', function() {
                if (this.value.length > 0) {
                    this.dataset.lastTyped = Date.now();
                }
            });
        });
        
        // Form field interaction timing
        inputs.forEach(function(input) {
            input.addEventListener('focus', function() {
                this.dataset.focusTime = Date.now();
            });
            
            input.addEventListener('blur', function() {
                if (this.dataset.focusTime) {
                    const focusDuration = Date.now() - parseInt(this.dataset.focusTime);
                    this.dataset.focusDuration = focusDuration;
                }
            });
        });
    }
    
    // Reveal math challenge after delay
    function revealMathChallenge() {
        const mathChallenge = document.querySelector('.math-challenge');
        if (mathChallenge && !formState.mathRevealed) {
            mathChallenge.style.opacity = '0';
            mathChallenge.style.transform = 'translateY(20px)';
            mathChallenge.style.transition = 'all 0.5s ease';
            
            setTimeout(function() {
                mathChallenge.style.opacity = '1';
                mathChallenge.style.transform = 'translateY(0)';
            }, 100);
            
            formState.mathRevealed = true;
        }
    }
    
    // Reveal submit button after delay and interactions
    function revealSubmitButton() {
        if (formState.interactions < config.requiredInteractions) {
            setTimeout(revealSubmitButton, 500);
            return;
        }
        
        const submitButton = document.querySelector('.js-required');
        if (submitButton && !formState.submitRevealed) {
            submitButton.style.display = 'inline-block';
            submitButton.style.opacity = '0';
            submitButton.style.transform = 'translateY(20px)';
            submitButton.style.transition = 'all 0.5s ease';
            
            setTimeout(function() {
                submitButton.style.opacity = '1';
                submitButton.style.transform = 'translateY(0)';
            }, 100);
            
            formState.submitRevealed = true;
        }
    }
    
    // Setup math digit interaction for obfuscated numbers
    function setupMathDigits() {
        const mathDigits = document.querySelectorAll('.math-digit');
        mathDigits.forEach(function(digit) {
            digit.style.cursor = 'pointer';
            digit.style.transition = 'all 0.2s ease';
            
            digit.addEventListener('click', function() {
                const value = this.dataset.value;
                const answerInput = document.getElementById('math_answer');
                if (answerInput) {
                    answerInput.value += value;
                    answerInput.focus();
                }
                
                // Visual feedback
                this.style.transform = 'scale(1.2)';
                this.style.backgroundColor = '#007cba';
                this.style.color = 'white';
                
                setTimeout(function() {
                    digit.style.transform = 'scale(1)';
                    digit.style.backgroundColor = '';
                    digit.style.color = '';
                }, 200);
            });
            
            digit.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.1)';
                this.style.backgroundColor = '#f0f0f0';
            });
            
            digit.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
                this.style.backgroundColor = '';
            });
        });
    }
    
    // Validate form submission
    function validateFormSubmission(e) {
        const form = e.target;
        const currentTime = Date.now();
        const formTime = currentTime - formState.startTime;
        
        // Check minimum form time
        if (formTime < config.minFormTime) {
            e.preventDefault();
            showError('Please take your time to fill out the form properly.');
            return false;
        }
        
        // Check maximum form time
        if (formTime > config.maxFormTime) {
            e.preventDefault();
            showError('Form has expired. Please refresh the page and try again.');
            return false;
        }
        
        // Check interaction requirements
        if (formState.interactions < config.requiredInteractions) {
            e.preventDefault();
            showError('Please interact with the form before submitting.');
            return false;
        }
        
        // Check for bot-like behavior
        if (formState.mouseMovements < 5 && formState.keyPresses < 10) {
            e.preventDefault();
            showError('Please fill out the form manually.');
            return false;
        }
        
        // Check field focus patterns
        const inputs = form.querySelectorAll('input:not([type="hidden"]), textarea');
        let validFieldInteractions = 0;
        
        inputs.forEach(function(input) {
            if (input.dataset.focusDuration && parseInt(input.dataset.focusDuration) > 500) {
                validFieldInteractions++;
            }
        });
        
        if (validFieldInteractions < 2) {
            e.preventDefault();
            showError('Please take time to fill in each field properly.');
            return false;
        }
        
        // Add timing data to form for server-side validation
        const timingInput = document.createElement('input');
        timingInput.type = 'hidden';
        timingInput.name = 'client_timing';
        timingInput.value = JSON.stringify({
            formTime: formTime,
            interactions: formState.interactions,
            mouseMovements: formState.mouseMovements,
            keyPresses: formState.keyPresses,
            tabPresses: formState.tabPresses,
            fieldFocuses: formState.fieldFocuses,
            hasScrolled: formState.hasScrolled
        });
        form.appendChild(timingInput);
        
        return true;
    }
    
    // Show error message
    function showError(message) {
        // Remove existing error messages
        const existingError = document.querySelector('.js-error-message');
        if (existingError) {
            existingError.remove();
        }
        
        // Create new error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message js-error-message';
        errorDiv.textContent = message;
        errorDiv.style.backgroundColor = '#f8d7da';
        errorDiv.style.color = '#721c24';
        errorDiv.style.padding = '15px';
        errorDiv.style.borderRadius = '4px';
        errorDiv.style.marginBottom = '20px';
        
        // Insert at the top of the form
        const form = document.getElementById('contactForm');
        form.insertBefore(errorDiv, form.firstChild);
        
        // Scroll to error message
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            if (errorDiv.parentNode) {
                errorDiv.remove();
            }
        }, 5000);
    }
    
    // Throttle function for event handlers
    function throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
    
    // Anti-debugging techniques
    function setupAntiDebugging() {
        // Disable right-click on form
        document.addEventListener('contextmenu', function(e) {
            if (e.target.closest('#contactForm')) {
                e.preventDefault();
            }
        });
        
        // Disable text selection in form
        const form = document.getElementById('contactForm');
        if (form) {
            form.addEventListener('selectstart', function(e) {
                if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                }
            });
        }
        
        // Detect developer tools
        let devtools = {
            open: false,
            orientation: null
        };
        
        const threshold = 160;
        
        setInterval(function() {
            if (window.outerHeight - window.innerHeight > threshold || 
                window.outerWidth - window.innerWidth > threshold) {
                if (!devtools.open) {
                    devtools.open = true;
                    // Add subtle form interference when devtools open
                    const submitButton = document.querySelector('.js-required');
                    if (submitButton && Math.random() > 0.7) {
                        submitButton.disabled = true;
                        setTimeout(() => {
                            submitButton.disabled = false;
                        }, 1000);
                    }
                }
            } else {
                devtools.open = false;
            }
        }, 500);
    }
    
    // Initialize everything when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initFormProtection();
            setupAntiDebugging();
        });
    } else {
        initFormProtection();
        setupAntiDebugging();
    }
    
})();