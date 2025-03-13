/**
 * Public JavaScript for the Proof Of Gift plugin.
 *
 * @package ProofOfGift
 */

(function($) {
    'use strict';

    /**
     * Handle token application.
     */
    function handleTokenApplication() {
        // Original token handler (backward compatibility)
        $('#pog-apply-token').on('click', function() {
            var token = $('#pog_token').val();
            applyTokenToCart(token, $(this), $('#pog-token-message'));
        });
        
        // Cart page token handler
        $('#pog-apply-token-cart').on('click', function() {
            var token = $('#pog-token-cart').val();
            applyTokenToCart(token, $(this), $('#pog-token-message-cart'));
        });
        
        // Checkout page token handler
        $('#pog-apply-token-checkout').on('click', function() {
            var token = $('#pog-token-checkout').val();
            applyTokenToCart(token, $(this), $('#pog-token-message-checkout'));
        });
    }
    
    /**
     * Apply a token to the cart via AJAX
     * 
     * @param {string} token The token to apply
     * @param {jQuery} $button The button element
     * @param {jQuery} $messageContainer The message container
     */
    function applyTokenToCart(token, $button, $messageContainer) {
        if (!token) {
            alert('Please enter a token.');
            return;
        }
        
        // Show loading state
        var originalText = $button.text();
        $button.prop('disabled', true).text('Applying...');
        $messageContainer.html('').hide();
        
        // Send the request
        $.ajax({
            url: pog_public_vars.ajax_url,
            method: 'POST',
            data: {
                action: 'pog_apply_token_to_cart',
                nonce: pog_public_vars.nonce,
                token: token
            },
            success: function(response) {
                // Reset button state
                $button.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    // Clear the token field
                    $button.closest('.pog-token-input').find('input').val('');
                    
                    // Show success message with auto-refresh
                    var successMsg = '<div class="pog-token-success">' + response.data.message + ' - Updating cart...</div>';
                    $messageContainer.html(successMsg).show();
                    
                    // Automatically reload the page to apply the token to totals
                    // We need to force a clean reload with cart recalculation
                    setTimeout(function() {
                        // Add a timestamp parameter to prevent caching
                        var reloadUrl = window.location.href;
                        if (reloadUrl.indexOf('?') !== -1) {
                            reloadUrl += '&pog_refresh=' + Date.now();
                        } else {
                            reloadUrl += '?pog_refresh=' + Date.now();
                        }
                        window.location.href = reloadUrl;
                    }, 1000);
                } else {
                    $messageContainer.html('<div class="pog-token-error">' + response.data.message + '</div>').show();
                }
            },
            error: function() {
                // Reset button state
                $button.prop('disabled', false).text(originalText);
                $messageContainer.html('<div class="pog-token-error">' + pog_public_vars.strings.error + '</div>').show();
            }
        });
    }

    /**
     * Handle token removal.
     */
    function handleTokenRemoval() {
        $(document).on('click', '.pog-remove-token', function(e) {
            e.preventDefault();
            
            var tokenCode = $(this).data('token');
            var $removeBtn = $(this);
            
            // Disable the button and show loading state
            $removeBtn.prop('disabled', true).text('Removing...');
            
            // Create or get a message container near the button
            var $messageContainer;
            var $container = $removeBtn.closest('.pog-applied-tokens-container');
            
            // Check if there's already a message container in this context
            if ($container.find('.pog-token-message-removal').length === 0) {
                // Create a new message container
                $messageContainer = $('<div class="pog-token-message-removal"></div>');
                $container.prepend($messageContainer);
            } else {
                $messageContainer = $container.find('.pog-token-message-removal');
            }
            
            // Send AJAX request to remove the token
            $.ajax({
                url: pog_public_vars.ajax_url,
                method: 'POST',
                data: {
                    action: 'pog_remove_token_from_cart',
                    nonce: pog_public_vars.nonce,
                    token_code: tokenCode
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message in this context
                        $messageContainer.html('<div class="pog-token-success">' + response.data.message + ' - Updating cart...</div>').show();
                        
                        // Force page reload to properly update cart
                        // Add a timestamp parameter to prevent caching
                        var reloadUrl = window.location.href;
                        if (reloadUrl.indexOf('?') !== -1) {
                            reloadUrl += '&pog_refresh=' + Date.now();
                        } else {
                            reloadUrl += '?pog_refresh=' + Date.now();
                        }
                        window.location.href = reloadUrl;
                    } else {
                        $removeBtn.prop('disabled', false).text('Remove');
                        $messageContainer.html('<div class="pog-token-error">' + response.data.message + '</div>').show();
                    }
                },
                error: function(xhr, status, error) {
                    console.log("AJAX Error:", status, error, xhr.responseText);
                    $removeBtn.prop('disabled', false).text('Remove');
                    $messageContainer.html('<div class="pog-token-error">' + pog_public_vars.strings.error + '</div>').show();
                }
            });
        });
    }

    /**
     * Initialize all public functionality.
     */
    function init() {
        handleTokenApplication();
        handleTokenRemoval();
    }

    // Initialize when the DOM is ready.
    $(document).ready(init);

})(jQuery);