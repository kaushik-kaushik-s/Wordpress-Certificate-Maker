jQuery(document).ready(function($) {
    // Template Builder Functionality
    let selectedElement = null;
    let elementCounter = 0;
    let isDraggingNew = false;

    // Initialize the canvas with a default size
    $('.ks-builder-canvas').css({
        'width': '794px',  // A4 width in pixels at 96dpi
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

    function addElementToCanvas(type, x, y) {
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

        switch(type) {
            case 'text':
                elementHtml = `
                <div class="canvas-element text-element" id="${elementId}" data-type="text">
                    <span class="element-content">Sample Text</span>
                    <div class="element-controls">
                        <button class="edit-btn" title="Edit">✏️</button>
                        <button class="delete-btn" title="Delete">🗑️</button>
                    </div>
                    <div class="ui-resizable-handle ui-resizable-se ui-icon ui-icon-gripsmall-diagonal-se"></div>
                </div>`;
                break;
            case 'image':
                elementHtml = `
                <div class="canvas-element image-element" id="${elementId}" data-type="image">
                    <img src="https://via.placeholder.com/${dims.width}x${dims.height}" alt="Image" class="element-content">
                    <div class="element-controls">
                        <button class="edit-btn" title="Edit">✏️</button>
                        <button class="delete-btn" title="Delete">🗑️</button>
                    </div>
                    <div class="ui-resizable-handle ui-resizable-se ui-icon ui-icon-gripsmall-diagonal-se"></div>
                </div>`;
                break;
            case 'signature':
                elementHtml = `
                <div class="canvas-element signature-element" id="${elementId}" data-type="signature">
                    <span class="element-content">{{signature}}</span>
                    <div class="element-controls">
                        <button class="edit-btn" title="Edit">✏️</button>
                        <button class="delete-btn" title="Delete">🗑️</button>
                    </div>
                    <div class="ui-resizable-handle ui-resizable-se ui-icon ui-icon-gripsmall-diagonal-se"></div>
                </div>`;
                break;
            case 'date':
                elementHtml = `
                <div class="canvas-element date-element" id="${elementId}" data-type="date">
                    <span class="element-content">{{issue_date}}</span>
                    <div class="element-controls">
                        <button class="edit-btn" title="Edit">✏️</button>
                        <button class="delete-btn" title="Delete">🗑️</button>
                    </div>
                    <div class="ui-resizable-handle ui-resizable-se ui-icon ui-icon-gripsmall-diagonal-se"></div>
                </div>`;
                break;
            case 'qr':
                elementHtml = `
                <div class="canvas-element qr-element" id="${elementId}" data-type="qr">
                    <div class="element-content qr-placeholder">QR Code</div>
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
            'touchAction': 'none' // Important for touch devices
        });

        // Add to canvas
        $('#certificate-canvas').append($element);

        // Make element draggable within canvas
        $element.draggable({
            containment: 'parent',
            cursor: 'move',
            zIndex: 100,
            start: function() {
                isDraggingNew = false;
                $(this).css('z-index', 1000); // Bring to front when dragging
            },
            stop: function() {
                // Save position after drag
                saveElementPosition($(this));
            }
        });

        // Make element resizable with aspect ratio for images and QR codes
        const aspectRatio = (type === 'image' || type === 'qr') ? dims.width / dims.height : false;
        
        $element.resizable({
            handles: 'se, sw, ne, nw',
            aspectRatio: aspectRatio,
            minWidth: 30,
            minHeight: 30,
            stop: function() {
                // Save size after resize
                saveElementPosition($(this));
            }
        });

        // Add click event to select element
        $element.on('click', function(e) {
            e.stopPropagation();
            selectElement($(this));
        });
        
        // Select the newly added element
        selectElement($element);
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
        
        // Save position when selected (in case it was moved programmatically)
        saveElementPosition($element);
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
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'save_certificate_template',
                name: templateName,
                data: templateData,
                nonce: ks_cert_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Template saved successfully!');
                } else {
                    alert('Error saving template: ' + response.data);
                }
            },
            error: function() {
                alert('Error saving template. Please try again.');
            }
        });
    });

    function getTemplateData() {
        let elements = [];
        
        $('.canvas-element').each(function() {
            let $el = $(this);
            elements.push({
                id: $el.attr('id'),
                type: $el.data('type'),
                content: $el.find('.element-content').html(),
                position: {
                    x: $el.position().left,
                    y: $el.position().top
                },
                size: {
                    width: $el.width(),
                    height: $el.height()
                },
                styles: {
                    'font-size': $el.css('font-size'),
                    'color': $el.css('color'),
                    'font-family': $el.css('font-family'),
                    'text-align': $el.css('text-align')
                }
            });
        });
        
        return {
            elements: elements,
            canvas: {
                width: $('#certificate-canvas').width(),
                height: $('#certificate-canvas').height()
            }
        };
    }


    // Preview template
    $('#preview-template').click(function() {
        let templateData = getTemplateData();
        // Open preview window
        let previewWindow = window.open('', 'preview', 'width=900,height=700');
        
        // Create a simple HTML preview
        let previewHtml = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Certificate Preview</title>
                <style>
                    body { margin: 0; padding: 20px; }
                    .preview-container { 
                        width: ${templateData.canvas.width}px; 
                        height: ${templateData.canvas.height}px; 
                        margin: 0 auto;
                        position: relative;
                        border: 1px solid #ddd;
                        background: white;
                        box-shadow: 0 0 10px rgba(0,0,0,0.1);
                    }
                    .preview-element {
                        position: absolute;
                        box-sizing: border-box;
                    }
                </style>
            </head>
            <body>
                <h1>Certificate Preview</h1>
                <div class="preview-container" id="preview-canvas">
                    <!-- Elements will be added here -->
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <button onclick="window.print()">Print Certificate</button>
                    <button onclick="window.close()">Close</button>
                </div>
                <script>
                    // Add elements to preview
                    let elements = ${JSON.stringify(templateData.elements)};
                    let container = document.getElementById('preview-canvas');
                    
                    elements.forEach(function(el) {
                        let div = document.createElement('div');
                        div.className = 'preview-element';
                        div.innerHTML = el.content;
                        div.style.left = el.position.x + 'px';
                        div.style.top = el.position.y + 'px';
                        div.style.width = el.size.width + 'px';
                        div.style.height = el.size.height + 'px';
                        
                        // Apply styles
                        Object.keys(el.styles).forEach(function(prop) {
                            div.style[prop] = el.styles[prop];
                        });
                        
                        container.appendChild(div);
                    });
                </script>
            </body>
            </html>
        `;
        
        // Write the preview HTML to the new window
        previewWindow.document.open();
        previewWindow.document.write(previewHtml);
        previewWindow.document.close();
    });

    // Load template
    $('#load-template').click(function() {
        let templateId = $('#template-select').val();
        if (!templateId) return;
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'load_certificate_template',
                id: templateId,
                nonce: ks_cert_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Clear existing elements
                    $('.canvas-element').remove();
                    
                    // Load new elements
                    response.data.elements.forEach(function(el) {
                        addElementToCanvas(el.type, el.position.x, el.position.y);
                        
                        // Apply styles and content
                        let $el = $('#' + el.id);
                        if ($el.length) {
                            $el.find('.element-content').html(el.content);
                            $el.css(el.styles);
                            $el.css({
                                left: el.position.x + 'px',
                                top: el.position.y + 'px',
                                width: el.size.width + 'px',
                                height: el.size.height + 'px'
                            });
                        }
                    });
                } else {
                    alert('Error loading template: ' + response.data);
                }
            },
            error: function() {
                alert('Error loading template. Please try again.');
            }
        });
    });

    // Prevent drag events from propagating to parent elements
    $(document).on('mousedown', '.canvas-element', function(e) {
        e.stopPropagation();
        // Bring to front when clicked
        $('.canvas-element').css('z-index', '10');
        $(this).css('z-index', '100');
    });
    
    // Canvas background click to deselect
    $('#certificate-canvas').on('click', function(e) {
        if (e.target === this) {
            $('.canvas-element').removeClass('selected');
            selectedElement = null;
            $('#element-properties').html('<p>Select an element to edit properties</p>');
        }
    });
});
