# Kaushik Sannidhi's Certificate Plugin

A comprehensive WordPress plugin for creating, managing, and verifying PDF certificates with a powerful template builder and API webhook functionality.

## Features

- **Visual Template Builder**: Drag-and-drop interface for creating certificate templates
- **PDF Generation**: Automatic PDF creation with custom styling
- **Certificate Verification Portal**: Public portal for certificate validation
- **REST API**: Webhook endpoint for programmatic certificate generation
- **Certificate Management**: Admin interface for viewing and managing certificates
- **Responsive Design**: Works on desktop and mobile devices

## Installation

### Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- TCPDF library (included separately)

### Step 1: Download TCPDF Library
1. Download TCPDF from https://tcpdf.org/
2. Extract the files and place the `tcpdf` folder in your plugin directory
3. The structure should be: `wp-content/plugins/ks-certificate-plugin/tcpdf/`

### Step 2: Install Plugin Files
1. Create a new folder in your WordPress plugins directory: `wp-content/plugins/ks-certificate-plugin/`
2. Copy the main plugin file (`ks-certificate-plugin.php`) to this folder
3. Create the following folder structure:
   ```
   ks-certificate-plugin/
   ├── ks-certificate-plugin.php
   ├── js/
   │   ├── admin.js
   │   └── frontend.js
   ├── css/
   │   ├── admin.css
   │   └── frontend.css
   └── tcpdf/
       └── (TCPDF library files)
   ```

### Step 3: Activate Plugin
1. Go to WordPress Admin → Plugins
2. Find "Kaushik Sannidhi's Certificate Plugin"
3. Click "Activate"

## Usage

### Creating Certificate Templates

1. Go to **Certificates → Template Builder** in WordPress admin
2. Use the drag-and-drop interface to add elements:
    - **Text**: Static text or dynamic placeholders
    - **Image**: Logos, backgrounds, or decorative elements
    - **Signature**: Signature placeholders
    - **Date**: Date fields with formatting
    - **QR Code**: QR codes for verification

3. Configure element properties:
    - Position and size
    - Font styles and colors
    - Content and placeholders

4. Save your template with a descriptive name

### Dynamic Placeholders
Use these placeholders in your templates:
- `{{recipient_name}}` - Certificate recipient's name
- `{{course_name}}` - Course or achievement name
- `{{issue_date}}` - Certificate issue date
- `{{certificate_id}}` - Unique certificate ID
- `{{custom_field_name}}` - Any custom field passed via API

### Setting Up Verification Portal

1. Create a new page in WordPress
2. Add the shortcode: `[certificate_verification]`
3. Users can now verify certificates by entering the certificate ID

### Using the API

The plugin provides a REST API endpoint for generating certificates programmatically.

**Endpoint:** `POST /wp-json/ks-cert/v1/generate`

**Headers:**
```
Content-Type: application/json
```

**Request Body:**
```json
{
    "api_key": "your-api-key-here",
    "recipient_name": "John Doe",
    "course_name": "WordPress Development",
    "issue_date": "2025-06-05",
    "template_id": 1,
    "custom_fields": {
        "instructor": "Jane Smith",
        "grade": "A+",
        "duration": "40 hours"
    }
}
```

**Response:**
```json
{
    "success": true,
    "certificate_id": "CERT-A1B2C3D4",
    "pdf_url": "https://yoursite.com/wp-content/uploads/certificates/cert-a1b2c3d4.pdf",
    "verification_url": "https://yoursite.com/verify/?id=CERT-A1B2C3D4"
}
```

### API Integration Examples

#### PHP (cURL)
```php
$data = array(
    'api_key' => 'your-api-key',
    'recipient_name' => 'John Doe',
    'course_name' => 'Advanced WordPress',
    'issue_date' => date('Y-m-d'),
    'template_id' => 1
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://yoursite.com/wp-json/ks-cert/v1/generate');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
echo "Certificate ID: " . $result['certificate_id'];
```

#### JavaScript (Fetch API)
```javascript
const generateCertificate = async (recipientData) => {
    const response = await fetch('/wp-json/ks-cert/v1/generate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            api_key: 'your-api-key',
            recipient_name: recipientData.name,
            course_name: recipientData.course,
            issue_date: new Date().toISOString().split('T')[0],
            template_id: 1
        })
    });
    
    const result = await response.json();
    console.log('Certificate generated:', result.certificate_id);
    return result;
};
```

#### Python (requests)
```python
import requests
import json
from datetime import date

url = 'https://yoursite.com/wp-json/ks-cert/v1/generate'
data = {
    'api_key': 'your-api-key',
    'recipient_name': 'John Doe',
    'course_name': 'Python Programming',
    'issue_date': str(date.today()),
    'template_id': 1,
    'custom_fields': {
        'instructor': 'Jane Smith',
        'score': '95%'
    }
}

response = requests.post(url, json=data)
result = response.json()
print(f"Certificate ID: {result['certificate_id']}")
```

## Configuration

### API Key Management
1. Go to **Certificates → API Settings**
2. Copy your API key
3. Use this key in all API requests
4. Regenerate the key if needed for security

### Template Management
1. View all templates in **Certificates → Template Builder**
2. Edit existing templates by loading them
3. Delete unused templates to keep things organized

### Certificate Management
1. View all generated certificates in **Certificates → Certificate List**
2. Download PDFs directly from the admin interface
3. Monitor certificate generation activity

## Security Features

- **API Key Authentication**: All API requests require a valid key
- **Unique Certificate IDs**: Each certificate gets a unique, non-guessable ID
- **Input Sanitization**: All user inputs are properly sanitized
- **Nonce Protection**: WordPress nonces protect against CSRF attacks

## Troubleshooting

### Common Issues

**PDF Generation Fails**
- Ensure TCPDF library is properly installed
- Check PHP memory limits (increase if needed)
- Verify write permissions on uploads/certificates directory

**API Returns 403 Error**
- Check API key is correct
- Ensure API key is passed in request body, not headers

**Template Builder Not Loading**
- Check browser console for JavaScript errors
- Ensure jQuery is loaded
- Verify admin scripts are enqueued properly

**Verification Portal Not Working**
- Check shortcode is properly placed: `[certificate_verification]`
- Ensure AJAX requests are working
- Verify database tables were created during activation

### File Permissions
Ensure these directories have write permissions:
- `wp-content/uploads/certificates/`
- Plugin directory for temporary files

### Database Tables
The plugin creates these tables:
- `wp_ks_certificates` - Stores certificate data
- `wp_ks_certificate_templates` - Stores template configurations

## Support

For issues or feature requests, please check:
1. WordPress error logs for PHP errors
2. Browser console for JavaScript errors
3. Database for missing tables or data

## Changelog

### Version 1.0.0
- Initial release with full functionality
- Template builder with drag-and-drop interface
- PDF generation with TCPDF
- Certificate verification portal
- REST API for programmatic generation
- Admin management interface