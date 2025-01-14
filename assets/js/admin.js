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

    // Preview content
    function updatePreview() {
        const form = $('#pseo-generator-form');
        const previewButton = $('#preview-content');
        const previewContainer = $('#content-preview');
        
        if (!form[0].checkValidity()) {
            showMessage('Please fill in all required fields before generating preview.', 'error');
            return;
        }

        // Get the first location and skill set for preview
        const location = $('#location').val().split(',')[0].trim();
        const skillSet = $('#skill_set').val().split(',')[0].trim();

        if (!location || !skillSet) {
            showMessage('Please enter at least one location and one skill set.', 'error');
            return;
        }

        previewButton.prop('disabled', true).text('Generating Preview...');
        previewContainer.html('<div class="loading">Generating preview...</div>');

        $.ajax({
            url: pseoAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'pseo_preview_content',
                nonce: pseoAjax.nonce,
                template_page: $('#template_page').val(),
                location: location,
                keyword: $('#keyword').val(),
                skill_set: skillSet
            },
            success: function(response) {
                if (response.success) {
                    previewContainer.html(
                        '<div class="preview-content">' + 
                        '<div class="preview-info">Preview showing content for first location and skill set combination.</div>' +
                        response.data.content +
                        '</div>' +
                        '<div class="preview-meta">' +
                        '<h4>Meta Description Preview:</h4>' +
                        '<div class="meta-preview">' + response.data.meta_description + '</div>' +
                        '</div>'
                    );
                    showMessage('Preview generated successfully!', 'success');
                } else {
                    showMessage(response.data.message, 'error');
                    previewContainer.html('<div class="error">Failed to generate preview</div>');
                }
            },
            error: function() {
                showMessage('Error generating preview. Please try again.', 'error');
                previewContainer.html('<div class="error">Error generating preview</div>');
            },
            complete: function() {
                previewButton.prop('disabled', false).text('Preview Content');
            }
        });
    }

    // Add preview button and container to the form
    $('#pseo-generator-form').append(
        '<div class="preview-actions">' +
        '<button type="button" id="preview-content" class="button button-secondary">Preview Content</button>' +
        '<div id="content-preview" class="preview-container"></div>' +
        '</div>'
    );

    // Handle preview button click
    $('#preview-content').on('click', function(e) {
        e.preventDefault();
        updatePreview();
    });

    // Generate Page
    $('#pseo-generator-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        
        // Validate form
        if (!form[0].checkValidity()) {
            return;
        }

        // Get form values
        const templatePage = $('#template_page').val();
        const keyword = $('#keyword').val();
        const locations = $('#location').val().split(',').map(item => item.trim()).filter(Boolean);
        const skillSets = $('#skill_set').val().split(',').map(item => item.trim()).filter(Boolean);

        if (!locations.length || !skillSets.length) {
            showMessage('Please enter at least one location and one skill set.', 'error');
            return;
        }

        const totalPages = locations.length * skillSets.length;
        if (!confirm(`This will generate ${totalPages} pages (${locations.length} locations × ${skillSets.length} skills). Do you want to continue?`)) {
            return;
        }

        submitButton.prop('disabled', true).text('Generating Pages...');
        let completedPages = 0;
        let errors = [];

        // Function to generate a single page
        function generatePage(location, skillSet) {
            return $.ajax({
                url: pseoAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'pseo_generate_page',
                    nonce: pseoAjax.nonce,
                    template_page: templatePage,
                    location: location,
                    keyword: keyword,
                    skill_set: skillSet
                }
            }).always(function() {
                completedPages++;
                const progress = Math.round((completedPages / totalPages) * 100);
                submitButton.text(`Generating... ${progress}%`);
            });
        }

        // Generate pages for each combination
        const promises = [];
        locations.forEach(location => {
            skillSets.forEach(skillSet => {
                promises.push(generatePage(location, skillSet));
            });
        });

        // Handle all completions
        Promise.all(promises.map(p => p.catch(e => e)))
            .then(results => {
                const successCount = results.filter(r => r.success).length;
                const errorCount = results.filter(r => !r.success).length;

                if (errorCount === 0) {
                    showMessage(`Successfully generated ${successCount} pages!`, 'success');
                } else {
                    showMessage(`Generated ${successCount} pages with ${errorCount} errors.`, 'warning');
                }
            })
            .catch(error => {
                showMessage('Error generating pages. Please try again.', 'error');
            })
            .finally(() => {
                submitButton.prop('disabled', false).text('Generate Page');
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
}); 