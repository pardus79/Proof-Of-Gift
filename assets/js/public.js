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
        $('#pog-apply-token').on('click', function() {
            var token = $('#pog_token').val();

            if (!token) {
                alert('Please enter a token.');
                return;
            }

            // Show loading state.
            $(this).prop('disabled', true).text('Applying...');
            $('#pog-token-message').html('').hide();

            // Send the request.
            $.ajax({
                url: pog_public_vars.ajax_url,
                method: 'POST',
                data: {
                    action: 'pog_apply_token_to_cart',
                    nonce: pog_public_vars.nonce,
                    token: token
                },
                success: function(response) {
                    // Reset button state.
                    $('#pog-apply-token').prop('disabled', false).text('Apply Token');

                    if (response.success) {
                        // Display success message.
                        $('#pog-token-message').html('<div class="pog-token-success">' + response.data.message + '</div>').show();
                        
                        // Clear the token field.
                        $('#pog_token').val('');
                        
                        // Add to applied tokens.
                        var html = '<div class="pog-applied-token" data-token="' + response.data.token + '">';
                        html += '<span class="pog-token-amount">' + response.data.message + '</span>';
                        html += '<button type="button" class="pog-remove-token" data-token="' + response.data.token + '">&times;</button>';
                        html += '</div>';
                        
                        $('#pog-applied-tokens').append(html);
                        
                        // Refresh the page to update cart totals.
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        $('#pog-token-message').html('<div class="pog-token-error">' + response.data.message + '</div>').show();
                    }
                },
                error: function() {
                    // Reset button state.
                    $('#pog-apply-token').prop('disabled', false).text('Apply Token');
                    $('#pog-token-message').html('<div class="pog-token-error">' + pog_public_vars.strings.error + '</div>').show();
                }
            });
        });
    }

    /**
     * Handle token removal.
     */
    function handleTokenRemoval() {
        $(document).on('click', '.pog-remove-token', function() {
            var token = $(this).data('token');
            
            // Remove from the cart.
            // In a real implementation, this would call an AJAX endpoint.
            // For now, we'll just refresh the page.
            window.location.reload();
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