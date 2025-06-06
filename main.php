<?php
/**
 * Plugin Name: Kaushik Sannidhi's Certificate Plugin
 * Plugin URI: https://yourwebsite.com
 * Description: A comprehensive PDF certificate maker with template builder, verification portal, and API webhook functionality
 * Version: 1.0.0
 * Author: Kaushik Sannidhi
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KS_CERT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KS_CERT_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include TCPDF library
require_once(KS_CERT_PLUGIN_PATH . 'tcpdf/tcpdf.php');

class KS_Certificate_Plugin {

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_save_certificate_template', array($this, 'save_certificate_template'));
        add_action('wp_ajax_verify_certificate', array($this, 'verify_certificate'));
        add_action('wp_ajax_nopriv_verify_certificate', array($this, 'verify_certificate'));
        add_action('wp_ajax_regenerate_api_key', array($this, 'regenerate_api_key'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_shortcode('certificate_verification', array($this, 'certificate_verification_shortcode'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function init() {
        // Initialize plugin
    }

    public function activate() {
        global $wpdb;

        // Create certificates table
        $table_name = $wpdb->prefix . 'ks_certificates';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            certificate_id varchar(100) NOT NULL UNIQUE,
            recipient_name varchar(255) NOT NULL,
            course_name varchar(255) NOT NULL,
            issue_date date NOT NULL,
            template_data longtext NOT NULL,
            pdf_path varchar(500) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY certificate_id (certificate_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Create templates table
        $templates_table = $wpdb->prefix . 'ks_certificate_templates';

        $sql2 = "CREATE TABLE $templates_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            template_name varchar(255) NOT NULL,
            template_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        dbDelta($sql2);

        // Create uploads directory for certificates
        $upload_dir = wp_upload_dir();
        $cert_dir = $upload_dir['basedir'] . '/certificates';
        if (!file_exists($cert_dir)) {
            wp_mkdir_p($cert_dir);
        }
    }

    public function deactivate() {
        // Cleanup if needed
    }

    public function enqueue_scripts() {
        wp_enqueue_script('ks-cert-frontend', KS_CERT_PLUGIN_URL . 'js/frontend.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('ks-cert-frontend', KS_CERT_PLUGIN_URL . 'css/frontend.css', array(), '1.0.0');
        wp_localize_script('ks-cert-frontend', 'ks_cert_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ks_cert_nonce')
        ));
    }

    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'ks-certificate') !== false) {
            wp_enqueue_script('jquery-ui-draggable');
            wp_enqueue_script('jquery-ui-droppable');
            wp_enqueue_script('jquery-ui-resizable');
            wp_enqueue_script('ks-cert-admin', KS_CERT_PLUGIN_URL . 'js/admin.js', array('jquery', 'jquery-ui-draggable', 'jquery-ui-droppable', 'jquery-ui-resizable'), '1.0.0', true);
            wp_enqueue_style('ks-cert-admin', KS_CERT_PLUGIN_URL . 'css/admin.css', array(), '1.0.0');
            wp_localize_script('ks-cert-admin', 'ks_cert_admin_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ks_cert_admin_nonce')
            ));
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'Certificate Manager',
            'Certificates',
            'manage_options',
            'ks-certificate-manager',
            array($this, 'admin_page'),
            'dashicons-awards',
            30
        );

        add_submenu_page(
            'ks-certificate-manager',
            'Template Builder',
            'Template Builder',
            'manage_options',
            'ks-certificate-builder',
            array($this, 'template_builder_page')
        );

        add_submenu_page(
            'ks-certificate-manager',
            'Certificate List',
            'Certificate List',
            'manage_options',
            'ks-certificate-list',
            array($this, 'certificate_list_page')
        );

        add_submenu_page(
            'ks-certificate-manager',
            'API Settings',
            'API Settings',
            'manage_options',
            'ks-certificate-api',
            array($this, 'api_settings_page')
        );
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Kaushik Sannidhi's Certificate Manager</h1>
            <div class="ks-cert-dashboard">
                <div class="ks-cert-card">
                    <h3>Template Builder</h3>
                    <p>Create and customize certificate templates with drag-and-drop functionality.</p>
                    <a href="<?php echo admin_url('admin.php?page=ks-certificate-builder'); ?>" class="button button-primary">Build Templates</a>
                </div>
                <div class="ks-cert-card">
                    <h3>Certificate List</h3>
                    <p>View and manage all generated certificates.</p>
                    <a href="<?php echo admin_url('admin.php?page=ks-certificate-list'); ?>" class="button button-primary">View Certificates</a>
                </div>
                <div class="ks-cert-card">
                    <h3>API Settings</h3>
                    <p>Configure API endpoints and webhook settings.</p>
                    <a href="<?php echo admin_url('admin.php?page=ks-certificate-api'); ?>" class="button button-primary">API Settings</a>
                </div>
                <div class="ks-cert-card">
                    <h3>Verification Portal</h3>
                    <p>Add verification portal to any page using shortcode: <code>[certificate_verification]</code></p>
                </div>
            </div>
        </div>
        <?php
    }

    public function template_builder_page() {
        ?>
        <div class="wrap">
            <h1>Certificate Template Builder</h1>
            <div id="ks-template-builder">
                <div class="ks-builder-toolbar">
                    <input type="text" id="template-name" placeholder="Template Name" />
                    <button id="save-template" class="button button-primary">Save Template</button>
                    <button id="preview-template" class="button">Preview</button>
                </div>

                <div class="ks-builder-container">
                    <div class="ks-builder-sidebar">
                        <h3>Elements</h3>
                        <div class="ks-element-list">
                            <div class="ks-element" data-type="text">📝 Text</div>
                            <div class="ks-element" data-type="image">🖼️ Image</div>
                            <div class="ks-element" data-type="signature">✍️ Signature</div>
                            <div class="ks-element" data-type="date">📅 Date</div>
                            <div class="ks-element" data-type="qr">📱 QR Code</div>
                        </div>

                        <h3>Properties</h3>
                        <div id="element-properties">
                            <p>Select an element to edit properties</p>
                        </div>
                    </div>

                    <div class="ks-builder-canvas">
                        <div id="certificate-canvas">
                            <div class="canvas-background">
                                <p>Drag elements here to build your certificate</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function certificate_list_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ks_certificates';
        $certificates = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
        ?>
        <div class="wrap">
            <h1>Certificate List</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th>Certificate ID</th>
                    <th>Recipient Name</th>
                    <th>Course Name</th>
                    <th>Issue Date</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($certificates as $cert): ?>
                    <tr>
                        <td><?php echo esc_html($cert->certificate_id); ?></td>
                        <td><?php echo esc_html($cert->recipient_name); ?></td>
                        <td><?php echo esc_html($cert->course_name); ?></td>
                        <td><?php echo esc_html($cert->issue_date); ?></td>
                        <td><?php echo esc_html($cert->created_at); ?></td>
                        <td>
                            <a href="<?php echo wp_upload_dir()['baseurl'] . '/certificates/' . basename($cert->pdf_path); ?>" target="_blank" class="button">View PDF</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function api_settings_page() {
        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['ks_cert_api_nonce'], 'ks_cert_api_settings')) {
            if (isset($_POST['regenerate_key'])) {
                $api_key = wp_generate_password(32, false);
                update_option('ks_cert_api_key', $api_key);
                echo '<div class="notice notice-success"><p>API Key regenerated successfully!</p></div>';
            }
        }

        $api_key = get_option('ks_cert_api_key', wp_generate_password(32, false));
        if (!get_option('ks_cert_api_key')) {
            update_option('ks_cert_api_key', $api_key);
        }

        ?>
        <div class="wrap">
            <h1>API Settings</h1>
            <div class="ks-api-info">
                <h3>API Endpoint</h3>
                <p><strong>URL:</strong> <code><?php echo site_url('/wp-json/ks-cert/v1/generate'); ?></code></p>
                <p><strong>Method:</strong> POST</p>
                <p><strong>API Key:</strong> <code><?php echo $api_key; ?></code></p>

                <h3>Request Format</h3>
                <pre><code>{
    "api_key": "<?php echo $api_key; ?>",
    "recipient_name": "John Doe",
    "course_name": "WordPress Development",
    "issue_date": "2025-06-05",
    "template_id": 1,
    "custom_fields": {
        "instructor": "Jane Smith",
        "grade": "A+"
    }
}</code></pre>

                <h3>Response Format</h3>
                <pre><code>{
    "success": true,
    "certificate_id": "CERT-123456",
    "pdf_url": "https://yoursite.com/wp-content/uploads/certificates/cert-123456.pdf",
    "verification_url": "https://yoursite.com/certificate-verification/?id=CERT-123456"
}</code></pre>

                <form method="post">
                    <?php wp_nonce_field('ks_cert_api_settings', 'ks_cert_api_nonce'); ?>
                    <input type="hidden" name="regenerate_key" value="1">
                    <button type="submit" name="submit" class="button">Regenerate API Key</button>
                </form>
            </div>
        </div>
        <?php
    }

    public function regenerate_api_key() {
        check_ajax_referer('ks_cert_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $new_key = wp_generate_password(32, false);
        update_option('ks_cert_api_key', $new_key);

        wp_send_json_success(array('new_key' => $new_key));
    }

    public function certificate_verification_shortcode($atts) {
        ob_start();
        ?>
        <div id="ks-certificate-verification">
            <h3>Certificate Verification</h3>
            <div class="verification-form">
                <input type="text" id="cert-id-input" placeholder="Enter Certificate ID" />
                <button id="verify-btn" class="button">Verify Certificate</button>
            </div>
            <div id="verification-result"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function save_certificate_template() {
        check_ajax_referer('ks_cert_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'ks_certificate_templates';

        $template_name = sanitize_text_field($_POST['name']);
        $template_data = $_POST['data']; // This should be JSON data

        // Validate JSON
        if (is_string($template_data)) {
            $decoded = json_decode($template_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(array('message' => 'Invalid JSON data'));
                return;
            }
            $template_data = $decoded;
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'template_name' => $template_name,
                'template_data' => wp_json_encode($template_data)
            )
        );

        if ($result) {
            wp_send_json_success(array('message' => 'Template saved successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to save template'));
        }
    }

    public function verify_certificate() {
        check_ajax_referer('ks_cert_nonce', 'nonce');
        
        $cert_id = sanitize_text_field($_POST['certificate_id']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'ks_certificates';

        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE certificate_id = %s",
            $cert_id
        ));

        if ($certificate) {
            wp_send_json_success(array(
                'valid' => true,
                'certificate' => array(
                    'id' => $certificate->certificate_id,
                    'recipient_name' => $certificate->recipient_name,
                    'course_name' => $certificate->course_name,
                    'issue_date' => $certificate->issue_date,
                    'pdf_url' => wp_upload_dir()['baseurl'] . '/certificates/' . basename($certificate->pdf_path)
                )
            ));
        } else {
            wp_send_json_success(array(
                'valid' => false,
                'message' => 'Certificate not found'
            ));
        }
    }

    public function register_rest_routes() {
        register_rest_route('ks-cert/v1', '/generate', array(
            'methods' => 'POST',
            'callback' => array($this, 'api_generate_certificate'),
            'permission_callback' => array($this, 'api_permission_check')
        ));
    }

    public function api_permission_check($request) {
        $api_key = $request->get_param('api_key');
        $stored_key = get_option('ks_cert_api_key');
        return $api_key === $stored_key;
    }

    public function api_generate_certificate($request) {
        $params = $request->get_params();

        $recipient_name = sanitize_text_field($params['recipient_name']);
        $course_name = sanitize_text_field($params['course_name']);
        $issue_date = sanitize_text_field($params['issue_date']);
        $template_id = intval($params['template_id']);

        // Generate unique certificate ID
        $certificate_id = 'CERT-' . strtoupper(wp_generate_password(8, false));

        // Get template
        global $wpdb;
        $templates_table = $wpdb->prefix . 'ks_certificate_templates';
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $templates_table WHERE id = %d",
            $template_id
        ));

        if (!$template) {
            return new WP_Error('template_not_found', 'Template not found', array('status' => 404));
        }

        // Generate PDF
        $pdf_path = $this->generate_certificate_pdf($certificate_id, $recipient_name, $course_name, $issue_date, $template->template_data, $params);

        if (!$pdf_path) {
            return new WP_Error('pdf_generation_failed', 'Failed to generate PDF', array('status' => 500));
        }

        // Save to database
        $certificates_table = $wpdb->prefix . 'ks_certificates';
        $result = $wpdb->insert(
            $certificates_table,
            array(
                'certificate_id' => $certificate_id,
                'recipient_name' => $recipient_name,
                'course_name' => $course_name,
                'issue_date' => $issue_date,
                'template_data' => $template->template_data,
                'pdf_path' => $pdf_path
            )
        );

        if (!$result) {
            return new WP_Error('database_error', 'Failed to save certificate to database', array('status' => 500));
        }

        return rest_ensure_response(array(
            'success' => true,
            'certificate_id' => $certificate_id,
            'pdf_url' => wp_upload_dir()['baseurl'] . '/certificates/' . basename($pdf_path),
            'verification_url' => site_url('/certificate-verification/?id=' . $certificate_id)
        ));
    }

    private function generate_certificate_pdf($cert_id, $recipient_name, $course_name, $issue_date, $template_data, $custom_fields = array()) {
        try {
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            $pdf->SetCreator('Kaushik Sannidhi Certificate Plugin');
            $pdf->SetTitle('Certificate - ' . $recipient_name);
            $pdf->SetSubject('Certificate');

            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            $pdf->AddPage();

            // Decode template data
            $template = json_decode($template_data, true);
            
            if (!$template || !isset($template['elements'])) {
                // Fallback to basic certificate if template parsing fails
                $this->generate_basic_certificate_pdf($pdf, $cert_id, $recipient_name, $course_name, $issue_date);
            } else {
                // Use template data to build certificate
                $this->build_certificate_from_template($pdf, $template, $cert_id, $recipient_name, $course_name, $issue_date, $custom_fields);
            }

            // Save PDF file
            $upload_dir = wp_upload_dir();
            $cert_dir = $upload_dir['basedir'] . '/certificates';
            if (!file_exists($cert_dir)) {
                wp_mkdir_p($cert_dir);
            }
            $filename = 'cert-' . strtolower($cert_id) . '.pdf';
            $filepath = $cert_dir . '/' . $filename;

            $pdf->Output($filepath, 'F');

            return $filepath;

        } catch (Exception $e) {
            error_log('Certificate PDF generation error: ' . $e->getMessage());
            return false;
        }
    }

    private function generate_basic_certificate_pdf($pdf, $cert_id, $recipient_name, $course_name, $issue_date) {
        // Basic certificate HTML template
        $html = '
        <div style="text-align: center; padding: 50px;">
            <h1 style="font-size: 36px; color: #2c3e50; margin-bottom: 30px;">CERTIFICATE OF COMPLETION</h1>
            <p style="font-size: 18px; margin-bottom: 40px;">This is to certify that</p>
            <h2 style="font-size: 32px; color: #3498db; margin-bottom: 40px; text-decoration: underline;">' . esc_html($recipient_name) . '</h2>
            <p style="font-size: 18px; margin-bottom: 20px;">has successfully completed the course</p>
            <h3 style="font-size: 24px; color: #e74c3c; margin-bottom: 40px;">' . esc_html($course_name) . '</h3>
            <p style="font-size: 16px; margin-bottom: 60px;">on ' . esc_html($issue_date) . '</p>
            <div style="margin-top: 80px;">
                <p style="font-size: 12px; color: #7f8c8d;">Certificate ID: ' . esc_html($cert_id) . '</p>
            </div>
        </div>';

        $pdf->writeHTML($html, true, false, true, false, '');
    }

    private function build_certificate_from_template($pdf, $template, $cert_id, $recipient_name, $course_name, $issue_date, $custom_fields) {
        // Set canvas dimensions if available
        $canvas_width = isset($template['canvas']['width']) ? $template['canvas']['width'] : 794;
        $canvas_height = isset($template['canvas']['height']) ? $template['canvas']['height'] : 1123;

        // Start building HTML
        $html = '<div style="position: relative; width: ' . $canvas_width . 'px; height: ' . $canvas_height . 'px;">';

        // Add background if specified
        if (isset($template['canvas']['backgroundUrl']) && !empty($template['canvas']['backgroundUrl'])) {
            $html .= '<div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: url(' . esc_url($template['canvas']['backgroundUrl']) . '); background-size: cover; background-position: center;"></div>';
        }

        // Process each element
        foreach ($template['elements'] as $element) {
            $html .= $this->render_template_element($element, $cert_id, $recipient_name, $course_name, $issue_date, $custom_fields);
        }

        $html .= '</div>';

        $pdf->writeHTML($html, true, false, true, false, '');
    }

    private function render_template_element($element, $cert_id, $recipient_name, $course_name, $issue_date, $custom_fields) {
        // Default values
        $type = isset($element['type']) ? $element['type'] : 'text';
        $content = isset($element['content']) ? $element['content'] : '';
        $left = isset($element['left']) ? $element['left'] : 0;
        $top = isset($element['top']) ? $element['top'] : 0;
        $width = isset($element['width']) ? $element['width'] : 100;
        $height = isset($element['height']) ? $element['height'] : 50;

        // Build styles
        $styles = 'position: absolute; left: ' . $left . 'px; top: ' . $top . 'px; width: ' . $width . 'px; height: ' . $height . 'px;';
        
        if (isset($element['styles'])) {
            foreach ($element['styles'] as $key => $value) {
                if ($value && $value !== 'initial' && $value !== 'auto') {
                    $styles .= $key . ': ' . $value . ';';
                }
            }
        }

        // Replace placeholders in content
        $content = str_replace('{{recipient_name}}', $recipient_name, $content);
        $content = str_replace('{{course_name}}', $course_name, $content);
        $content = str_replace('{{issue_date}}', $issue_date, $content);
        $content = str_replace('{{certificate_id}}', $cert_id, $content);

        // Replace custom fields
        if (isset($custom_fields['custom_fields']) && is_array($custom_fields['custom_fields'])) {
            foreach ($custom_fields['custom_fields'] as $key => $value) {
                $content = str_replace('{{' . $key . '}}', $value, $content);
            }
        }

        // Handle different element types
        switch ($type) {
            case 'image':
                if (strpos($content, 'src=') !== false) {
                    // Content already contains img tag
                    return '<div style="' . $styles . '">' . $content . '</div>';
                } else {
                    // Treat content as image URL
                    return '<div style="' . $styles . '"><img src="' . esc_url($content) . '" style="width: 100%; height: 100%; object-fit: contain;" /></div>';
                }
                break;

            case 'qr':
                // For QR codes, generate a simple placeholder or actual QR if library available
                $qr_content = 'QR: ' . $cert_id;
                return '<div style="' . $styles . ' border: 1px solid #ccc; text-align: center; display: flex; align-items: center; justify-content: center; font-size: 10px;">' . $qr_content . '</div>';
                break;

            default:
                // Text and other elements
                return '<div style="' . $styles . '">' . $content . '</div>';
        }
    }
}

// Initialize the plugin
new KS_Certificate_Plugin();
?>