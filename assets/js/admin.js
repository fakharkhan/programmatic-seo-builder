jQuery(document).ready(function($) {
    // Tab switching
    $('.pseo-tab-button').on('click', function(e) {
        e.preventDefault();
        const tabId = $(this).data('tab');
        
        // Update active states
        $('.pseo-tab-button').removeClass('active');
        $(this).addClass('active');
        
        $('.pseo-tab-content').removeClass('active');
        $(`#${tabId}`).addClass('active');
    });

    // Test API Connection
    $('#test-api').on('click', function(e) {
        e.preventDefault();
        const button = $(this);
        const apiKey = $('#pseo_api_key').val();

        if (!apiKey) {
            showMessage('Please enter an API key first.', 'error');
            return;
        }

        button.prop('disabled', true).text('Testing...');

        $.ajax({
            url: pseoAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'pseo_test_api',
                nonce: pseoAjax.nonce,
                api_key: apiKey
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('Error testing API connection. Please try again.', 'error');
            },
            complete: function() {
                button.prop('disabled', false).text('Test API Connection');
            }
        });
    });

    // Generate Page Form Handler
    $('#pseo-generator-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        
        // Get form values
        const templatePage = $('#template_page').val();
        const keyword = {
            find: $('#keyword_find').val().trim(),
            replace: $('#keyword').val().trim()
        };

        // Get dynamic rows values
        const dynamicReplacements = [];
        $('.dynamic-row').each(function() {
            const rowId = $(this).data('row');
            const find = $(`#dynamic_find_${rowId}`).val().trim();
            const replace = $(`#dynamic_replace_${rowId}`).val().trim();
            
            if (find && replace) {
                dynamicReplacements.push({ find, replace });
            }
        });

        if (!templatePage) {
            showMessage('Please select a template page.', 'error');
            return;
        }

        // Check if at least one find/replace pair is provided
        const hasKeywordPair = keyword.find && keyword.replace;
        const hasDynamicPairs = dynamicReplacements.length > 0;

        if (!hasKeywordPair && !hasDynamicPairs) {
            showMessage('Please provide at least one find and replace pair.', 'error');
            return;
        }

        // Validate keyword pair if provided
        if ((keyword.find && !keyword.replace) || (!keyword.find && keyword.replace)) {
            showMessage('Please provide both find and replace values for Primary Keyword.', 'error');
            return;
        }

        submitButton.prop('disabled', true).text('Generating Page...');

        // Prepare replacements object
        const replacements = {};
        if (hasKeywordPair) {
            replacements.keyword = keyword;
        }
        
        // Add dynamic replacements
        dynamicReplacements.forEach((item, index) => {
            replacements[`dynamic_${index}`] = item;
        });

        // Generate page
        $.ajax({
            url: pseoAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'pseo_clone_page',
                nonce: pseoAjax.nonce,
                template_id: templatePage,
                replacements: replacements
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Page generated successfully!', 'success');
                } else {
                    showMessage(response.data.message || 'Error generating page.', 'error');
                }
            },
            error: function() {
                showMessage('Error generating page. Please try again.', 'error');
            },
            complete: function() {
                submitButton.prop('disabled', false).text('Generate Page');
            }
        });
    });

    // Helper function to show messages
    function showMessage(message, type) {
        const messageDiv = $('<div>')
            .addClass('pseo-message')
            .addClass(type)
            .text(message);

        // Remove any existing messages
        $('.pseo-message').remove();

        // Add new message
        $('.wrap h1').after(messageDiv);

        // Auto-remove after 5 seconds
        setTimeout(function() {
            messageDiv.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Add this after the form validation code
    const formData = {
        keyword: {
            find: $('#keyword_find').val() || '[keyword]',
            replace: $('#keyword').val()
        },
        location: {
            find: $('#location_find').val() || '[location]',
            replace: $('#location').val()
        },
        skill_set: {
            find: $('#skill_find').val() || '[skill_set]',
            replace: $('#skill_set').val()
        }
    };

    // Add this after the existing document.ready code
    $('#template_page').on('change', function() {
        const templateId = $(this).val();
        const previewLink = $('#preview-template');
        const keywordFindInput = $('#keyword_find');
        
        if (templateId) {
            // Get the permalink and title via AJAX
            $.ajax({
                url: pseoAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'pseo_get_template_url',
                    nonce: pseoAjax.nonce,
                    template_id: templateId
                },
                success: function(response) {
                    if (response.success) {
                        previewLink.attr('href', response.data.url).show();
                        // Set the page title to keyword find input
                        keywordFindInput.val(response.data.title);
                    } else {
                        previewLink.hide();
                        keywordFindInput.val('');
                    }
                },
                error: function() {
                    previewLink.hide();
                    keywordFindInput.val('');
                }
            });
        } else {
            previewLink.hide();
            keywordFindInput.val('');
        }
    });

    // Make sure links with target="_blank" open in new tab
    $(document).on('click', 'a[target="_blank"]', function(e) {
        e.preventDefault();
        window.open($(this).attr('href'), '_blank');
    });

    // Add this after existing document.ready code
    let rowCounter = 0;

    // Function to create new row
    function createDynamicRow() {
        rowCounter++;
        const row = $(`
            <div class="dynamic-row" data-row="${rowCounter}">
                <div class="find-replace-group">
                    <div class="find-field">
                        <label for="dynamic_find_${rowCounter}">Find:</label>
                        <input type="text" id="dynamic_find_${rowCounter}" name="dynamic_find_${rowCounter}" 
                               placeholder="Text to find">
                    </div>
                    <div class="replace-field">
                        <label for="dynamic_replace_${rowCounter}">Replace with:</label>
                        <input type="text" id="dynamic_replace_${rowCounter}" name="dynamic_replace_${rowCounter}" 
                               placeholder="Text to replace with">
                    </div>
                </div>
                <button type="button" class="remove-row dashicons dashicons-no-alt" title="Remove row"></button>
            </div>
        `);
        
        $('#dynamic-rows').append(row);
        return row;
    }

    // Add row button handler
    $('.add-row').on('click', function() {
        createDynamicRow();
    });

    // Remove row handler
    $(document).on('click', '.remove-row', function() {
        $(this).closest('.dynamic-row').remove();
    });
}); 