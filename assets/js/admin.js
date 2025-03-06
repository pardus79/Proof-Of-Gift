/**
 * Admin JavaScript for the Proof Of Gift plugin.
 *
 * @package ProofOfGift
 */

(function($) {
    'use strict';

    /**
     * Handle single token generation.
     */
    function handleSingleTokenGeneration() {
        $('#pog-generate-single-token').on('click', function() {
            var amount = $('#pog-single-token-amount').val();

            if (!amount || isNaN(amount) || amount <= 0) {
                alert(pog_admin_vars.strings.error + ': ' + 'Please enter a valid amount.');
                return;
            }

            // Show loading state.
            $(this).prop('disabled', true).text('Generating...');

            // Send the request.
            $.ajax({
                url: pog_admin_vars.ajax_url,
                method: 'POST',
                data: {
                    action: 'pog_generate_token',
                    nonce: pog_admin_vars.nonce,
                    amount: amount
                },
                success: function(response) {
                    // Reset button state.
                    $('#pog-generate-single-token').prop('disabled', false).text('Generate Token');

                    if (response.success) {
                        // Display the token.
                        $('#pog-single-token-result').text(response.data.token);
                        $('.pog-token-result').show();
                    } else {
                        alert(pog_admin_vars.strings.error + ': ' + response.data.message);
                    }
                },
                error: function() {
                    // Reset button state.
                    $('#pog-generate-single-token').prop('disabled', false).text('Generate Token');
                    alert(pog_admin_vars.strings.error);
                }
            });
        });
    }

    /**
     * Handle batch token generation.
     */
    function handleBatchTokenGeneration() {
        var denominations = [];

        // Add denomination to the list.
        $('#pog-add-denomination').on('click', function() {
            var amount = $('#pog-batch-token-amount').val();
            var quantity = $('#pog-batch-token-quantity').val();

            if (!amount || isNaN(amount) || amount <= 0) {
                alert('Please enter a valid amount.');
                return;
            }

            if (!quantity || isNaN(quantity) || quantity <= 0) {
                alert('Please enter a valid quantity.');
                return;
            }

            // Add to the list.
            denominations.push({
                amount: amount,
                quantity: quantity
            });

            // Display the denomination.
            var html = '<div class="pog-denomination-item">';
            html += '<span class="pog-denomination-amount">' + amount + '</span> ';
            html += '<span class="pog-denomination-quantity">(' + quantity + ' tokens)</span>';
            html += '<button type="button" class="button pog-remove-denomination" data-index="' + (denominations.length - 1) + '">Remove</button>';
            html += '</div>';

            $('#pog-denominations-list').append(html);

            // Reset the form.
            $('#pog-batch-token-amount').val('');
            $('#pog-batch-token-quantity').val('');
        });

        // Remove denomination from the list.
        $(document).on('click', '.pog-remove-denomination', function() {
            var index = $(this).data('index');
            denominations.splice(index, 1);
            $(this).closest('.pog-denomination-item').remove();

            // Update the indices.
            $('.pog-remove-denomination').each(function(i) {
                $(this).data('index', i);
            });
        });

        // Generate tokens.
        $('#pog-generate-batch-tokens').on('click', function() {
            if (denominations.length === 0) {
                alert('Please add at least one denomination.');
                return;
            }

            // Show loading state.
            $(this).prop('disabled', true).text('Generating...');

            // Send the request.
            $.ajax({
                url: pog_admin_vars.ajax_url,
                method: 'POST',
                data: {
                    action: 'pog_generate_tokens_batch',
                    nonce: pog_admin_vars.nonce,
                    denominations: JSON.stringify(denominations)
                },
                success: function(response) {
                    // Reset button state.
                    $('#pog-generate-batch-tokens').prop('disabled', false).text('Generate Tokens');

                    if (response.success) {
                        // Display the tokens.
                        var html = '';
                        response.data.tokens.forEach(function(token) {
                            html += '<tr>';
                            html += '<td>' + token.token + '</td>';
                            html += '<td>' + token.amount + '</td>';
                            html += '</tr>';
                        });

                        $('#pog-batch-tokens-result').html(html);
                        $('.pog-batch-result').show();
                    } else {
                        alert(pog_admin_vars.strings.error + ': ' + response.data.message);
                    }
                },
                error: function() {
                    // Reset button state.
                    $('#pog-generate-batch-tokens').prop('disabled', false).text('Generate Tokens');
                    alert(pog_admin_vars.strings.error);
                }
            });
        });

        // Export as CSV.
        $('#pog-export-csv').on('click', function() {
            // Get the tokens from the table.
            var tokens = [];
            $('#pog-batch-tokens-result tr').each(function() {
                var token = $(this).find('td:first').text();
                var amount = $(this).find('td:last').text();

                tokens.push({
                    token: token,
                    amount: amount
                });
            });

            if (tokens.length === 0) {
                alert('No tokens to export.');
                return;
            }

            // Send the request.
            $.ajax({
                url: pog_admin_vars.ajax_url,
                method: 'POST',
                data: {
                    action: 'pog_export_tokens_csv',
                    nonce: pog_admin_vars.nonce,
                    tokens: JSON.stringify(tokens)
                },
                success: function(response) {
                    if (response.success) {
                        // Create a blob and download the file.
                        var blob = new Blob([response.data.csv], { type: 'text/csv' });
                        var link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = 'proof-of-gift-tokens.csv';
                        link.click();
                    } else {
                        alert(pog_admin_vars.strings.error + ': ' + response.data.message);
                    }
                },
                error: function() {
                    alert(pog_admin_vars.strings.error);
                }
            });
        });

        // Export as PDF.
        $('#pog-export-pdf').on('click', function() {
            // Get the tokens from the table.
            var tokens = [];
            $('#pog-batch-tokens-result tr').each(function() {
                var token = $(this).find('td:first').text();
                var amount = $(this).find('td:last').text();

                tokens.push({
                    token: token,
                    amount: amount
                });
            });

            if (tokens.length === 0) {
                alert('No tokens to export.');
                return;
            }

            // Send the request.
            $.ajax({
                url: pog_admin_vars.ajax_url,
                method: 'POST',
                data: {
                    action: 'pog_export_tokens_pdf',
                    nonce: pog_admin_vars.nonce,
                    tokens: JSON.stringify(tokens)
                },
                success: function(response) {
                    if (response.success) {
                        // In future: open the PDF in a new window
                        // window.open(response.data.pdf_url, '_blank');
                        alert('PDF export will be available in a future release. Please use CSV export for now.');
                    } else {
                        alert(pog_admin_vars.strings.error + ': ' + response.data.message);
                    }
                },
                error: function() {
                    alert('PDF export is not yet implemented. Please use CSV export instead.');
                }
            });
        });
    }

    /**
     * Handle token verification.
     */
    function handleTokenVerification() {
        $('#pog-verify-token-btn').on('click', function() {
            var token = $('#pog-verify-token').val();

            if (!token) {
                alert('Please enter a token.');
                return;
            }

            // Show loading state.
            $(this).prop('disabled', true).text('Verifying...');

            // Send the request.
            $.ajax({
                url: pog_admin_vars.ajax_url,
                method: 'POST',
                data: {
                    action: 'pog_verify_token',
                    nonce: pog_admin_vars.nonce,
                    token: token
                },
                success: function(response) {
                    // Reset button state.
                    $('#pog-verify-token-btn').prop('disabled', false).text('Verify Token');

                    if (response.success) {
                        // Build the verification result HTML.
                        var html = '<div class="pog-verify-result">';
                        html += '<h3>' + (response.data.valid ? 'Valid Token' : 'Invalid Token') + '</h3>';
                        
                        html += '<div class="pog-verify-details">';
                        html += '<p><strong>Token:</strong> ' + response.data.token + '</p>';
                        html += '<p><strong>Amount:</strong> ';
                        
                        if (response.data.mode === 'direct_satoshi') {
                            html += response.data.amount + ' satoshis';
                        } else if (response.data.mode === 'satoshi_conversion') {
                            html += response.data.amount + ' satoshis (' + pog_admin_vars.symbol + response.data.store_currency_amount + ')';
                        } else {
                            html += pog_admin_vars.symbol + response.data.amount;
                        }
                        
                        html += '</p>';
                        
                        if (response.data.redeemed) {
                            html += '<p><strong>Status:</strong> Redeemed</p>';
                            
                            if (response.data.redeemed_at) {
                                html += '<p><strong>Redeemed At:</strong> ' + response.data.redeemed_at + '</p>';
                            }
                            
                            if (response.data.order_id) {
                                html += '<p><strong>Order:</strong> ';
                                
                                if (response.data.order_number) {
                                    html += '#' + response.data.order_number;
                                    
                                    if (response.data.order_status) {
                                        html += ' (' + response.data.order_status + ')';
                                    }
                                } else {
                                    html += response.data.order_id;
                                }
                                
                                html += '</p>';
                            }
                        } else {
                            html += '<p><strong>Status:</strong> ' + (response.data.valid ? 'Valid' : 'Invalid') + '</p>';
                        }
                        
                        html += '</div>';
                        html += '</div>';
                        
                        $('#pog-verify-result').html(html).show();
                    } else {
                        $('#pog-verify-result').html('<div class="pog-verify-error">' + response.data.message + '</div>').show();
                    }
                },
                error: function() {
                    // Reset button state.
                    $('#pog-verify-token-btn').prop('disabled', false).text('Verify Token');
                    $('#pog-verify-result').html('<div class="pog-verify-error">' + pog_admin_vars.strings.error + '</div>').show();
                }
            });
        });
    }

    /**
     * Handle operational mode changes.
     */
    function handleOperationalModeChanges() {
        $('#pog_operational_mode').on('change', function() {
            var mode = $(this).val();
            
            // Hide all mode descriptions.
            $('.pog-mode-description').hide();
            
            // Show the selected mode description.
            $('.' + mode + '-mode').show();
        });
    }

    /**
     * Handle BTCPay Server connection test.
     */
    function handleBTCPayConnectionTest() {
        $('#pog-test-btcpay-connection').on('click', function() {
            // Show loading state.
            $(this).prop('disabled', true).text('Testing...');
            $('#pog-btcpay-connection-result').text('');
            
            // Send the request.
            $.ajax({
                url: pog_admin_vars.ajax_url,
                method: 'POST',
                data: {
                    action: 'pog_btcpay_test_connection',
                    nonce: pog_admin_vars.nonce
                },
                success: function(response) {
                    // Reset button state.
                    $('#pog-test-btcpay-connection').prop('disabled', false).text('Test Connection');
                    
                    if (response.success) {
                        $('#pog-btcpay-connection-result').text(pog_admin_vars.strings.connection_success).css('color', 'green');
                    } else {
                        $('#pog-btcpay-connection-result').text(pog_admin_vars.strings.connection_error + ': ' + response.data.message).css('color', 'red');
                    }
                },
                error: function() {
                    // Reset button state.
                    $('#pog-test-btcpay-connection').prop('disabled', false).text('Test Connection');
                    $('#pog-btcpay-connection-result').text(pog_admin_vars.strings.connection_error).css('color', 'red');
                }
            });
        });
    }

    /**
     * Initialize clipboard.js for copying tokens.
     */
    function initializeClipboard() {
        if (typeof ClipboardJS !== 'undefined') {
            var clipboard = new ClipboardJS('.pog-copy-token');
            
            clipboard.on('success', function(e) {
                // Show a success message.
                var $button = $(e.trigger);
                var originalText = $button.text();
                
                $button.text('Copied!');
                
                setTimeout(function() {
                    $button.text(originalText);
                }, 1500);
                
                e.clearSelection();
            });
        }
    }

    /**
     * Handle exchange rate refresh.
     */
    function handleExchangeRateRefresh() {
        $('#pog-refresh-exchange-rate').on('click', function() {
            // Clear the exchange rate field.
            $('input[name="pog_settings[satoshi_exchange_rate]"]').val('');
            
            // Submit the form.
            $(this).closest('form').submit();
        });
    }

    /**
     * Initialize all admin functionality.
     */
    function init() {
        handleSingleTokenGeneration();
        handleBatchTokenGeneration();
        handleTokenVerification();
        handleOperationalModeChanges();
        handleBTCPayConnectionTest();
        initializeClipboard();
        handleExchangeRateRefresh();
    }

    // Initialize when the DOM is ready.
    $(document).ready(init);

})(jQuery);