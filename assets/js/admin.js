jQuery(document).ready(function($) {
    // Function to set active tab
    function setActiveTab(tabId) {
        $('.pseo-tab-button').removeClass('active');
        $(`.pseo-tab-button[data-tab="${tabId}"]`).addClass('active');
        
        $('.pseo-tab-content').removeClass('active');
        $(`#${tabId}`).addClass('active');
        
        // Store active tab in localStorage
        localStorage.setItem('pseoActiveTab', tabId);
    }

    // On page load, check for stored tab
    const storedTab = localStorage.getItem('pseoActiveTab');
    if (storedTab) {
        setActiveTab(storedTab);
    }

    // Tab switching
    $('.pseo-tab-button').on('click', function(e) {
        e.preventDefault();
        const tabId = $(this).data('tab');
        setActiveTab(tabId);
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

    // CSV Generator functionality
    let csvData = null;
    let csvHeaders = null;

    // File upload handler
    $('#csv_file').on('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            const csv = e.target.result;
            processCSV(csv);
        };
        reader.readAsText(file);
    });

    function processCSV(csv) {
        // Parse CSV and remove empty lines
        const lines = csv.split('\n')
            .map(line => line.trim())
            .filter(line => line)
            .map(line => line.split(',').map(cell => cell.trim())); // Split by comma and trim cells
        
        if (lines.length < 2) {
            showMessage('CSV must contain at least 2 rows (find values and at least one replace row)', 'error');
            return;
        }

        // First row contains the "find" values
        const findValues = lines[0];
        
        // Rest of the rows contain "replace" values
        const replaceRows = lines.slice(1);

        // Validate that all rows have the same number of columns
        if (!replaceRows.every(row => row.length === findValues.length)) {
            showMessage('All rows must have the same number of columns', 'error');
            return;
        }

        csvHeaders = findValues;
        csvData = replaceRows.map(row => {
            const rowData = {};
            findValues.forEach((find, index) => {
                rowData[find] = row[index] || '';
            });
            return rowData;
        });

        // Update preview table
        showCSVPreview(findValues);
        
        // Enable generate button if template is selected
        updateGenerateButton();
    }

    function showCSVPreview(findValues) {
        let tableHTML = '<table class="csv-preview-table">';
        
        // Headers (Find Values)
        tableHTML += '<tr><th>Row</th>';
        findValues.forEach(value => {
            tableHTML += `<th>Find: "${value}"</th>`;
        });
        tableHTML += '</tr>';
        
        // Preview all replace rows
        csvData.forEach((row, index) => {
            tableHTML += `<tr><td>Replace ${index + 1}</td>`;
            findValues.forEach(find => {
                tableHTML += `<td>${row[find]}</td>`;
            });
            tableHTML += '</tr>';
        });
        
        tableHTML += '</table>';
        
        $('.csv-preview-content').html(tableHTML);
        $('#csv-preview').show();
    }

    function updateGenerateButton() {
        const templateSelected = $('#template_page_csv').val() !== '';
        const fileUploaded = csvData !== null;
        
        $('#generate-from-csv').prop('disabled', !(templateSelected && fileUploaded));
    }

    // Template selection handler for CSV generator
    $('#template_page_csv').on('change', function() {
        const templateId = $(this).val();
        const previewLink = $('#preview-template-csv');
        
        if (templateId) {
            // Get the permalink via AJAX
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
                    } else {
                        previewLink.hide();
                    }
                },
                error: function() {
                    previewLink.hide();
                }
            });
        } else {
            previewLink.hide();
        }
        
        updateGenerateButton();
    });

    // Add generate pages handler:
    $('#generate-from-csv').on('click', function() {
        const button = $(this);
        const templateId = $('#template_page_csv').val();
        const totalRows = csvData.length;
        let processedRows = 0;
        
        button.prop('disabled', true);
        $('.csv-progress').show();
        
        // Process each row sequentially
        function processRow(index) {
            if (index >= csvData.length) {
                showMessage(`Successfully generated ${processedRows} pages`, 'success');
                $('.csv-progress').hide();
                button.prop('disabled', false);
                return;
            }

            const row = csvData[index];
            const replacements = {};
            
            // Build replacements object from CSV data
            csvHeaders.forEach(find => {
                replacements[find] = {
                    find: find,
                    replace: row[find]
                };
            });

            // Update progress
            const progress = ((index + 1) / totalRows) * 100;
            $('.csv-progress-bar-inner').css('width', `${progress}%`);
            $('.csv-status').text(`Processing row ${index + 1} of ${totalRows}...`);

            // Generate page using existing AJAX function
            $.ajax({
                url: pseoAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'pseo_clone_page',
                    nonce: pseoAjax.nonce,
                    template_id: templateId,
                    replacements: replacements
                },
                success: function(response) {
                    if (response.success) {
                        processedRows++;
                    }
                    // Process next row
                    processRow(index + 1);
                },
                error: function() {
                    showMessage(`Error processing row ${index + 1}`, 'error');
                    processRow(index + 1); // Continue with next row despite error
                }
            });
        }

        // Start processing rows
        processRow(0);
    });

    // AI Content Generator Form Handler
    $('#pseo-ai-generator-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        
        // Get form values
        const title = $('#page_title').val().trim();
        const keywords = $('#page_keyword').val().trim();
        const tone = $('#content_tone').val() || 'professional';
        const wordCount = $('#word_count').val() || '1000';
        const pageBuilder = $('#page_builder').val();

        if (!title || !keywords) {
            showMessage('Please fill in all required fields.', 'error');
            return;
        }

        submitButton.prop('disabled', true).text('Generating Content...');

        // Make AJAX call to generate content
        $.ajax({
            url: pseoAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'pseo_generate_content',
                nonce: pseoAjax.nonce,
                title: title,
                keywords: keywords,
                tone: tone,
                word_count: wordCount,
                page_builder: pageBuilder
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Content generated successfully! Redirecting to editor...', 'success');
                    // Redirect to the edit page after 2 seconds
                    setTimeout(function() {
                        window.location.href = response.data.edit_url;
                    }, 2000);
                } else {
                    showMessage(response.data || 'Error generating content.', 'error');
                }
            },
            error: function() {
                showMessage('Error generating content. Please try again.', 'error');
            },
            complete: function() {
                submitButton.prop('disabled', false).text('Generate Content');
            }
        });
    });

    // Update the description in the admin page to show the correct format
    function updateCSVDescription() {
        const description = `
            <p class="description">Upload a CSV file where:<br>
            - First row contains the text to find<br>
            - Following rows contain the replacement values<br>
            - All rows must have the same number of columns<br><br>
            Example:<br>
            Laravel,Alabama<br>
            React,New York<br>
            Angular,New York<br>
            Open AI,New York</p>
        `;
        $('#csv_file').siblings('.description').html(description);
    }

    // Call this when document is ready
    $(document).ready(function() {
        updateCSVDescription();
    });
}); 