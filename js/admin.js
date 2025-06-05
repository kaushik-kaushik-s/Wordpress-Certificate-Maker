jQuery(document).ready(function($) {
    // Template Builder Functionality
    let selectedElement = null;
    let elementCounter = 0;

    // Make elements draggable from sidebar
    $('.ks-element').draggable({
        helper: 'clone',
        revert: 'invalid',
        cursor: 'move'
    });

    // Make canvas droppable
    $('#certificate-canvas').droppable({
        accept: '.ks-element',
        drop: function(event, ui) {
            let elementType = ui.draggable.data('type');
            addElementToCanvas(elementType, event.pageX - $(this).offset().left, event.pageY - $(this).offset().top);
        }
    });

    function addElementToCanvas(type, x, y) {
        elementCounter++;
        let elementId = 'element-' + elementCounter;
        let elementHtml = '';

        switch(type) {
            case 'text':
                elementHtml = `<div class="canvas-element text-element" id="${elementId}" data-type="text">
                    <span class="element-content">Sample Text</span>
                    <div class="element-controls">
                        <button class="edit-btn">✏️</button>
                        <button class="delete-btn">🗑️</button>
                    </div>
                </div>`;
                break;
            case 'image':
                elementHtml = `<div class="canvas-element image-element" id="${elementId}" data-type="image">
                    <img src="https://via.placeholder.com/100x50" alt="Image" class="element-content">
                    <div class="element-controls">
                        <button class="edit-btn">✏️</button>
                        <button class="delete-btn">🗑️</button>
                    </div>
                </div>`;
                break;
            case 'signature':
                elementHtml = `<div class="canvas-element signature-element" id="${elementId}" data-type="signature">
                    <span class="element-content">{{signature}}</span>
                    <div class="element-controls">
                        <button class="edit-btn">✏️</button>
                        <button class="delete-btn">🗑️</button>
                    </div>
                </div>`;
                break;
            case 'date':
                elementHtml = `<div class="canvas-element date-element" id="${elementId}" data-type="date">
                    <span class="element-content">{{issue_date}}</span>
                    <div class="element-controls">
                        <button class="edit-btn">✏️</button>
                        <button class="delete-btn">🗑️</button>
                    </div>
                </div>`;
                break;
            case 'qr':
                elementHtml = `<div class="canvas-element qr-element" id="${elementId}" data-type="qr">
                    <div class="element-content qr-placeholder">QR Code</div>
                    <div class="element-controls">
                        <button class="edit-btn">✏️</button>
                        <button class="delete-btn">🗑️</button>
                    </div>
                </div>`;
                break;
        }

        let $element = $(elementHtml);
        $element.css({
            position: 'absolute',
            left: x + 'px',
            top: y + 'px'
        });

        $('#certificate-canvas').append($element);

        // Make element draggable within canvas
        $element.draggable({
            containment: '#certificate-canvas',
            cursor: 'move'
        });

        // Make element resizable
        $element.resizable({
            handles: 'se'
        });

        // Add click event to select element
        $element.click(function(e) {
            e.stopPropagation();
            selectElement($(this));
        });
    }

    // Element selection
    function selectElement($element) {
        $('.canvas-element').removeClass('selected');
        $element.addClass('selected');
        selectedElement = $element;
        showElementProperties($element);
    }

    // Show element properties
    function showElementProperties($element) {
        let type = $element.data('type');
        let propertiesHtml = '';

        switch(type) {
            case 'text':
                propertiesHtml = `
                    <h4>Text Properties</h4>
                    <label>Text Content:</label>
                    <input type="text" id="text-content" value="${$element.find('.element-content').text()}">
                    <label>Font Size:</label>
                    <input type="number" id="font-size" value="16" min="8" max="72">
                    <label>Font Color:</label>
                    <input type="color" id="font-color" value="#000000">
                    <label>Font Family:</label>
                    <select id="font-family">
                        <option value="Arial">Arial</option>
                        <option value="Times New Roman">Times New Roman</option>
                        <option value="Helvetica">Helvetica</option>
                        <option value="Georgia">Georgia</option>
                    </select>
                    <button id="apply-text-props" class="button">Apply</button>
                `;
                break;
            case 'image':
                propertiesHtml = `
                    <h4>Image Properties</h4>
                    <label>Image URL:</label>
                    <input type="url" id="image-url" placeholder="https://example.com/image.jpg">
                    <label>Width:</label>
                    <input type="number" id="image-width" value="100" min="10" max="500">
                    <label>Height:</label>
                    <input type="number" id="image-height" value="50" min="10" max="500">
                    <button id="apply-image-props" class="button">Apply</button>
                `;
                break;
            default:
                propertiesHtml = `
                    <h4>${type.charAt(0).toUpperCase() + type.slice(1)} Properties</h4>
                    <p>Properties for ${type} element</p>
                `;
        }

        $('#element-properties').html(propertiesHtml);
    }

    // Apply text properties
    $(document).on('click', '#apply-text-props', function() {
        if (selectedElement) {
            let content = $('#text-content').val();
            let fontSize = $('#font-size').val();
            let fontColor = $('#font-color').val();
            let fontFamily = $('#font-family').val();

            selectedElement.find('.element-content').text(content);
            selectedElement.css({
                fontSize: fontSize + 'px',
                color: fontColor,
                fontFamily: fontFamily
            });
        }
    });

    // Apply image properties
    $(document).on('click', '#apply-image-props', function() {
        if (selectedElement) {
            let imageUrl = $('#image-url').val();
            let width = $('#image-width').val();
            let height = $('#image-height').val();

            if (imageUrl) {
                selectedElement.find('img').attr('src', imageUrl);
            }
            selectedElement.find('img').css({
                width: width + 'px',
                height: height + 'px'
            });
        }
    });

    // Delete element
    $(document).on('click', '.delete-btn', function(e) {
        e.stopPropagation();
        $(this).closest('.canvas-element').remove();
        $('#element-properties').html('<p>Select an element to edit properties</p>');
    });

    // Save template
    $('#save-template').click(function() {
        let templateName = $('#template-name').val();
        if (!templateName) {
            alert('Please enter a template name');
            return;
        }

        let templateData = getTemplateData();

        $.ajax({
            url: ks_cert_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'save_certificate_template',
                nonce: ks_cert_admin_ajax.nonce,
                template_name: templateName,
                template_data: templateData
            },
            success: function(response) {
                if (response.success) {
                    alert('Template saved successfully!');
                } else {
                    alert('Error saving template: ' + response.data.message);
                }
            },
            error: function() {
                alert('Error saving template');
            }
        });
    });

    function getTemplateData() {
        let template = {
            elements: [],
            canvas: {
                width: $('#certificate-canvas').width(),
                height: $('#certificate-canvas').height()
            }
        };

        $('.canvas-element').each(function() {
            let $element = $(this);
            let elementData = {
                id: $element.attr('id'),
                type: $element.data('type'),
                position: $element.position(),
                size: {
                    width: $element.width(),
                    height: $element.height()
                },
                content: $element.find('.element-content').text(),
                styles: {
                    fontSize: $element.css('font-size'),
                    color: $element.css('color'),
                    fontFamily: $element.css('font-family')
                }
            };
            template.elements.push(elementData);
        });

        return JSON.stringify(template);
    }

    // Preview template
    $('#preview-template').click(function() {
        let templateData = getTemplateData();
        // Open preview window
        let previewWindow = window.open('', 'preview', 'width=800,height=600');
        previewWindow.document.write(`
            <html>
                <head>
                    <title>Certificate Preview</title>
                    <style>
                        body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }
                        .preview-certificate { 
                            width: 800px; 
                            height: 600px; 
                            border: 2px solid #ccc; 
                            position: relative;
                            background: white;
                            margin: 0 auto;
                        }
                        .preview-element { position: absolute; }
                    </style>
                </head>
                <body>
                    <h2>Certificate Preview</h2>
                    <div class="preview-certificate" id="preview-canvas"></div>
                    <script>
                        let template = ${templateData};
                        template.elements.forEach(function(element) {
                            let div = document.createElement('div');
                            div.className = 'preview-element';
                            div.innerHTML = element.content;
                            div.style.left = element.position.left + 'px';
                            div.style.top = element.position.top + 'px';
                            div.style.fontSize = element.styles.fontSize;
                            div.style.color = element.styles.color;
                            div.style.fontFamily = element.styles.fontFamily;
                            document.getElementById('preview-canvas').appendChild(div);
                        });
                    </script>
                </body>
            </html>
        `);
    });

    // Regenerate API Key
    $('#regenerate-api-key').click(function() {
        if (confirm('Are you sure you want to regenerate the API key? This will invalidate the current key.')) {
            $.ajax({
                url: ks_cert_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'regenerate_api_key',
                    nonce: ks_cert_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        }
    });

    // Canvas background click to deselect
    $('#certificate-canvas').click(function(e) {
        if (e.target === this) {
            $('.canvas-element').removeClass('selected');
            selectedElement = null;
            $('#element-properties').html('<p>Select an element to edit properties</p>');
        }
    });
});