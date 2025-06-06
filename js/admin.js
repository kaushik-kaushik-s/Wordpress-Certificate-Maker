jQuery(document).ready(function($) {
    // Template Builder Functionality
    let selectedElement = null;
    let elementCounter = 0;
    let isDraggingNew = false;

    // Initialize the canvas with a default size
    $('#certificate-canvas').css({
        'width': '794px',   // A4 width in pixels at 96dpi
        'height': '1123px', // A4 height in pixels at 96dpi
        'margin': '0 auto',
        'background': 'white',
        'box-shadow': '0 0 10px rgba(0,0,0,0.1)',
        'position': 'relative',
        'overflow': 'hidden'
    });

    // Make elements draggable from sidebar
    $('.ks-element').draggable({
        helper: 'clone',
        revert: 'invalid',
        cursor: 'move',
        appendTo: 'body',
        zIndex: 100,
        start: function() {
            isDraggingNew = true;
        },
        stop: function() {
            isDraggingNew = false;
        }
    });

    // Make canvas droppable
    $('#certificate-canvas').droppable({
        accept: '.ks-element',
        hoverClass: 'canvas-hover',
        drop: function(event, ui) {
            if (isDraggingNew) {
                let elementType = $(ui.draggable).data('type');
                let offset = $(this).offset();
                let x = event.pageX - offset.left - $(this).scrollLeft();
                let y = event.pageY - offset.top - $(this).scrollTop();

                // Adjust for scroll position
                x = x - $(window).scrollLeft();
                y = y - $(window).scrollTop();

                addElementToCanvas(elementType, x, y);
            }
        }
    });

    function addElementToCanvas(type, x, y, elementData = null) {
        elementCounter++;
        let elementId = 'element-' + elementCounter;
        let elementHtml = '';

        // Default dimensions for elements
        const defaultDimensions = {
            text: { width: 200, height: 40 },
            image: { width: 200, height: 100 },
            signature: { width: 200, height: 40 },
            date: { width: 150, height: 30 },
            qr: { width: 100, height: 100 }
        };

        const dims = defaultDimensions[type] || { width: 100, height: 50 };

        // Use provided element data or defaults
        if (elementData) {
            x = elementData.left || x;
            y = elementData.top || y;
            dims.width = elementData.width || dims.width;
            dims.height = elementData.height || dims.height;
        }

        switch (type) {
            case 'text':
                let textContent = elementData ? elementData.content : 'Sample Text';
                elementHtml = `
<div class="canvas-element text-element" id="${elementId}" data-type="text">
    <span class="element-content">${textContent}</span>
    <div class="element-controls">
        <button class="edit-btn" title="Edit">✏️</button>
        <button class="delete-btn" title="Delete">🗑️</button>
    </div>
    <div class="ui-resizable-handle ui-resizable-se ui-icon ui-icon-gripsmall-diagonal-se"></div>
</div>`;
                break;

            case 'image':
                let imageContent = elementData ? elementData.content : `https://via.placeholder.com/${dims.width}x${dims.height}`;
                let imgSrc = imageContent;
                
                // Check if content contains img tag or is just URL
                if (imageContent.includes('<img')) {
                    // Extract src from img tag
                    let match = imageContent.match(/src="([^"]+)"/);
                    if (match) {
                        imgSrc = match[1];
                    }
                }
                
                elementHtml = `
<div class="canvas-element image-element" id="${elementId}" data-type="image">
    <img src="${imgSrc}" alt="Image" class="element-content">
    <div class="element-controls">
        <button class="edit-btn" title="Edit">✏️</button>
        <button class="delete-btn" title="Delete">🗑️</button>
    </div>
    <div class="ui-resizable-handle ui-resizable-se ui-icon ui-icon-gripsmall-diagonal-se"></div>
</div>`;
                break;

            case 'signature':
                let sigContent = elementData ? elementData.content : '{{recipient_name}}';
                elementHtml = `
<div class="canvas-element signature-element" id="${elementId}" data-type="signature">
    <span class="element-content">${sigContent}</span>
    <div class="element-controls">
        <button class="edit-btn" title="Edit">✏️</button>
        <button class="delete-btn" title="Delete">🗑️</button>
    </div>
    <div class="ui-resizable-handle ui-resizable-se ui-icon ui-icon-gripsmall-diagonal-se"></div>
</div>`;
                break;

            case 'date':
                let dateContent = elementData ? elementData.content : '{{issue_date}}';
                elementHtml = `
<div class="canvas-element date-element" id="${elementId}" data-type="date">
    <span class="element-content">${dateContent}</span>
    <div class="element-controls">
        <button class="edit-btn" title="Edit">✏️</button>
        <button class="delete-btn" title="Delete">🗑️</button>
    </div>
    <div class="ui-resizable-handle ui-resizable-se ui-icon ui-icon-gripsmall-diagonal-se"></div>
</div>`;
                break;

            case 'qr':
                let qrContent = elementData ? elementData.content : '{{certificate_id}}';
                elementHtml = `
<div class="canvas-element qr-element" id="${elementId}" data-type="qr">
    <div class="element-content qr-placeholder">${qrContent}</div>
    <div class="element-controls">
        <button class="edit-btn" title="Edit">✏️</button>
        <button class="delete-btn" title="Delete">🗑️</button>
    </div>
    <div class="ui-resizable-handle ui-resizable-se ui-icon ui-icon-gripsmall-diagonal-se"></div>
</div>`;
                break;
        }

        let $element = $(elementHtml);

        // Set initial position and size
        $element.css({
            'position': 'absolute',
            'left': x + 'px',
            'top': y + 'px',
            'width': dims.width + 'px',
            'height': dims.height + 'px',
            'zIndex': '10',
            'boxSizing': 'border-box',
            'cursor': 'move',
            'touchAction': 'none'
        });

        // Apply styles if provided
        if (elementData && elementData.styles) {
            Object.keys(elementData.styles).forEach(function(key) {
                if (elementData.styles[key] && elementData.styles[key] !== 'initial' && elementData.styles[key] !== 'auto') {
                    $element.css(key, elementData.styles[key]);
                }
            });
        }

        // Add to canvas
        $('#certificate-canvas').append($element);

        // Make element draggable within canvas
        $element.draggable({
            containment: 'parent',
            cursor: 'move',
            zIndex: 100,
            start: function() {
                isDraggingNew = false;
                $(this).css('z-index', 1000);
            },
            stop: function() {
                saveElementPosition($(this));
            }
        });

        // Make element resizable
        const aspectRatio = (type === 'image' || type === 'qr') ? dims.width / dims.height : false;

        $element.resizable({
            handles: 'se, sw, ne, nw',
            aspectRatio: aspectRatio,
            minWidth: 30,
            minHeight: 30,
            stop: function() {
                saveElementPosition($(this));
            }
        });

        // Add click event to select element
        $element.on('click', function(e) {
            e.stopPropagation();
            selectElement($(this));
        });

        // Select the newly added element if it's new (not from loading)
        if (!elementData) {
            selectElement($element);
        }
    }

    // Save element position to data attributes
    function saveElementPosition($element) {
        const position = $element.position();
        $element.attr('data-x', position.left);
        $element.attr('data-y', position.top);
        $element.attr('data-width', $element.width());
        $element.attr('data-height', $element.height());
    }

    // Element selection
    function selectElement($element) {
        $('.canvas-element').removeClass('selected');
        $element.addClass('selected');
        selectedElement = $element;
        showElementProperties($element);
        saveElementPosition($element);
    }

    // Show element properties
    function showElementProperties($element) {
        let type = $element.data('type');
        let propertiesHtml = '';

        switch (type) {
            case 'text':
                let currentText = $element.find('.element-content').text();
                let currentFontSize = parseInt($element.css('font-size')) || 16;
                let currentColor = rgb2hex($element.css('color')) || '#000000';
                let currentFamily = $element.css('font-family') || 'Arial';
                
                propertiesHtml = `
<h4>Text Properties</h4>
<label>Text Content:</label>
<textarea id="text-content" rows="3">${currentText}</textarea>
<label>Font Size (px):</label>
<input type="number" id="font-size" value="${currentFontSize}" min="8" max="72">
<label>Font Color:</label>
<input type="color" id="font-color" value="${currentColor}">
<label>Font Family:</label>
<select id="font-family">
    <option value="Arial" ${currentFamily.includes('Arial') ? 'selected' : ''}>Arial</option>
    <option value="Times New Roman" ${currentFamily.includes('Times') ? 'selected' : ''}>Times New Roman</option>
    <option value="Helvetica" ${currentFamily.includes('Helvetica') ? 'selected' : ''}>Helvetica</option>
    <option value="Georgia" ${currentFamily.includes('Georgia') ? 'selected' : ''}>Georgia</option>
</select>
<label>Text Align:</label>
<select id="text-align">
    <option value="left">Left</option>
    <option value="center">Center</option>
    <option value="right">Right</option>
</select>
<label>Font Weight:</label>
<select id="font-weight">
    <option value="normal">Normal</option>
    <option value="bold">Bold</option>
</select>
<button id="apply-text-props" class="button">Apply</button>
`;
                break;

            case 'image':
                let currentSrc = $element.find('img').attr('src') || '';
                propertiesHtml = `
<h4>Image Properties</h4>
<label>Image URL:</label>
<input type="url" id="image-url" value="${currentSrc}" placeholder="https://example.com/image.jpg">
<label>Width (px):</label>
<input type="number" id="image-width" value="${$element.width()}" min="10" max="800">
<label>Height (px):</label>
<input type="number" id="image-height" value="${$element.height()}" min="10" max="600">
<button id="apply-image-props" class="button">Apply</button>
`;
                break;

            case 'signature':
                let currentSigText = $element.find('.element-content').text();
                propertiesHtml = `
<h4>Signature Properties</h4>
<label>Signature Text/Placeholder:</label>
<input type="text" id="signature-content" value="${currentSigText}" placeholder="{{signature}}">
<p><small>Use placeholders like {{recipient_name}}, {{course_name}}, etc.</small></p>
<button id="apply-signature-props" class="button">Apply</button>
`;
                break;

            case 'date':
                let currentDateText = $element.find('.element-content').text();
                propertiesHtml = `
<h4>Date Properties</h4>
<label>Date Text/Placeholder:</label>
<input type="text" id="date-content" value="${currentDateText}" placeholder="{{issue_date}}">
<p><small>Use {{issue_date}} for dynamic date or enter custom text</small></p>
<button id="apply-date-props" class="button">Apply</button>
`;
                break;

            case 'qr':
                let currentQrText = $element.find('.element-content').text();
                propertiesHtml = `
<h4>QR Code Properties</h4>
<label>QR Content/Placeholder:</label>
<input type="text" id="qr-content" value="${currentQrText}" placeholder="{{certificate_id}}">
<p><small>Use {{certificate_id}} for certificate ID or enter custom content</small></p>
<button id="apply-qr-props" class="button">Apply</button>
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

    // Helper function to convert RGB to Hex
    function rgb2hex(rgb) {
        if (!rgb || rgb === 'rgba(0, 0, 0, 0)') return '#000000';
        
        rgb = rgb.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
        if (!rgb) return '#000000';
        
        function hex(x) {
            return ("0" + parseInt(x).toString(16)).slice(-2);
        }
        return "#" + hex(rgb[1]) + hex(rgb[2]) + hex(rgb[3]);
    }

    // Apply text properties
    $(document).on('click', '#apply-text-props', function() {
        if (selectedElement) {
            let content = $('#text-content').val();
            let fontSize = $('#font-size').val();
            let fontColor = $('#font-color').val();
            let fontFamily = $('#font-family').val();
            let textAlign = $('#text-align').val();
            let fontWeight = $('#font-weight').val();

            selectedElement.find('.element-content').text(content);
            selectedElement.css({
                fontSize: fontSize + 'px',
                color: fontColor,
                fontFamily: fontFamily,
                textAlign: textAlign,
                fontWeight: fontWeight
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
            selectedElement.css({
                width: width + 'px',
                height: height + 'px'
            });
            selectedElement.find('img').css({
                width: '100%',
                height: '100%',
                objectFit: 'contain'
            });
        }
    });

    // Apply signature properties
    $(document).on('click', '#apply-signature-props', function() {
        if (selectedElement) {
            let content = $('#signature-content').val();
            selectedElement.find('.element-content').text(content);
        }
    });

    // Apply date properties
    $(document).on('click', '#apply-date-props', function() {
        if (selectedElement) {
            let content = $('#date-content').val();
            selectedElement.find('.element-content').text(content);
        }
    });

    // Apply QR properties
    $(document).on('click', '#apply-qr-props', function() {
        if (selectedElement) {
            let content = $('#qr-content').val();
            selectedElement.find('.element-content').text(content);
        }
    });

    // Delete element
    $(document).on('click', '.delete-btn', function(e) {
        e.stopPropagation();
        $(this).closest('.canvas-element').remove();
        $('#element-properties').html('<p>Select an element to edit properties</p>');
        selectedElement = null;
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
                name: templateName,
                data: JSON.stringify(templateData),
                nonce: ks_cert_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Template saved successfully!');
                    $('#template-name').val(''); // Clear the name field
                } else {
                    alert('Error saving template: ' + (response.data ? response.data.message : 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Error saving template. Please try again.');
            }
        });
    });

    function getTemplateData() {
        let elements = [];
        $('.canvas-element').each(function() {
            let $el = $(this);
            let elementData = {
                type: $el.data('type'),
                left: parseInt($el.css('left')),
                top: parseInt($el.css('top')),
                width: $el.width(),
                height: $el.height()
            };

            // Get content based on element type
            switch ($el.data('type')) {
                case 'text':
                case 'signature':
                case 'date':
                    elementData.content = $el.find('.element-content').text();
                    break;
                case 'image':
                    elementData.content = $el.find('img').attr('src');
                    break;
                case 'qr':
                    elementData.content = $el.find('.element-content').text();
                    break;
            }

            // Get computed styles
            elementData.styles = {
                fontSize: $el.css('font-size'),
                fontFamily: $el.css('font-family'),
                color: $el.css('color'),
                fontWeight: $el.css('font-weight'),
                textAlign: $el.css('text-align')
            };

            elements.push(elementData);
        });

        return {
            elements: elements,
            canvas: {
                width: $('#certificate-canvas').width(),
                height: $('#certificate-canvas').height(),
                backgroundUrl: $('#certificate-canvas').data('background-url') || ''
            }
        };
    }

    // Preview template
    $('#preview-template').click(function() {
        let templateData = getTemplateData();
        
        // Create preview HTML
        let previewHtml = `
<!DOCTYPE html>
<html>
<head>
    <title>Certificate Preview</title>
    <style>
        body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }
        .preview-container {
            width: ${templateData.canvas.width}px;
            height: ${templateData.canvas.height}px;
            margin: 0 auto;
            position: relative;
            border: 1px solid #ddd;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            ${templateData.canvas.backgroundUrl ? `background-image: url(${templateData.canvas.backgroundUrl}); background-size: cover; background-position: center;` : ''}
        }
        .preview-element {
            position: absolute;
            box-sizing: border-box;
        }
        .preview-element img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .qr-placeholder {
            border: 1px solid #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <h1>Certificate Preview</h1>
    <div class="preview-container" id="preview-canvas"></div>
    <div style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()">Print Certificate</button>
        <button onclick="window.close()">Close</button>
    </div>
</body>
</html>
`;

        // Open preview window
        let previewWindow = window.open('', 'preview', 'width=900,height=700,scrollbars=yes');
        previewWindow.document.open();
        previewWindow.document.write(previewHtml);
        previewWindow.document.close();

        // Add elements to preview after window loads
        previewWindow.onload = function() {
            let container = previewWindow.document.getElementById('preview-canvas');
            
            templateData.elements.forEach(function(el) {
                let div = previewWindow.document.createElement('div');
                div.className = 'preview-element';
                
                // Set content based on element type
                switch (el.type) {
                    case 'image':
                        div.innerHTML = `<img src="${el.content}" alt="Image">`;
                        break;
                    case 'qr':
                        div.innerHTML = `<div class="qr-placeholder">QR: ${el.content}</div>`;
                        break;
                    default:
                        div.innerHTML = el.content;
                        break;
                }
                
                // Set position and size
                div.style.left = el.left + 'px';
                div.style.top = el.top + 'px';
                div.style.width = el.width + 'px';
                div.style.height = el.height + 'px';

                // Apply styles
                if (el.styles) {
                    Object.keys(el.styles).forEach(function(prop) {
                        if (el.styles[prop] && el.styles[prop] !== 'initial' && el.styles[prop] !== 'auto') {
                            div.style[prop] = el.styles[prop];
                        }
                    });
                }

                container.appendChild(div);
            });
        };
    });

    // Background image functionality
    $(document).on('click', '#set-background', function() {
        let url = prompt('Enter background image URL:');
        if (url) {
            $('#certificate-canvas').css('background-image', 'url(' + url + ')');
            $('#certificate-canvas').css('background-size', 'cover');
            $('#certificate-canvas').css('background-position', 'center');
            $('#certificate-canvas').data('background-url', url);
        }
    });

    // Add background button if it doesn't exist
    if ($('#set-background').length === 0) {
        $('.ks-builder-toolbar').append('<button id="set-background" class="button">Set Background</button>');
    }

    // Prevent drag events from propagating to parent elements
    $(document).on('mousedown', '.canvas-element', function(e) {
        e.stopPropagation();
        // Bring to front when clicked
        $('.canvas-element').css('z-index', '10');
        $(this).css('z-index', '100');
    });

    // Canvas background click to deselect
    $('#certificate-canvas').on('click', function(e) {
        if (e.target === this || $(e.target).hasClass('canvas-background')) {
            $('.canvas-element').removeClass('selected');
            selectedElement = null;
            $('#element-properties').html('<p>Select an element to edit properties</p>');
        }
    });

    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Delete selected element with Delete key
        if (e.key === 'Delete' && selectedElement) {
            selectedElement.remove();
            $('#element-properties').html('<p>Select an element to edit properties</p>');
            selectedElement = null;
        }
    });
});