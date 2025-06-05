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

class KS_Certificate_Plugin {

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_save_certificate_template', array($this, 'save_certificate_template'));
        add_action('wp_ajax_verify_certificate', array($this, 'verify_certificate'));
        add_action('wp_ajax_nopriv_verify_certificate', array($this, 'verify_certificate'));
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
            wp_enqueue_script('ks-cert-admin', KS_CERT_PLUGIN_URL . 'js/admin.js', array('jquery'), '1.0.0', true);
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
        $api_key = get_option('ks_cert_api_key', wp_generate_password(32, false));
        update_option('ks_cert_api_key', $api_key);

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

                <button id="regenerate-api-key" class="button">Regenerate API Key</button>
            </div>
        </div>
        <?php
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

        $template_name = sanitize_text_field($_POST['template_name']);
        $template_data = wp_kses_post($_POST['template_data']);

        $result = $wpdb->insert(
            $table_name,
            array(
                'template_name' => $template_name,
                'template_data' => $template_data
            )
        );

        if ($result) {
            wp_send_json_success(array('message' => 'Template saved successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to save template'));
        }
    }

    public function verify_certificate() {
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

        // Save to database
        $certificates_table = $wpdb->prefix . 'ks_certificates';
        $wpdb->insert(
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

        return rest_ensure_response(array(
            'success' => true,
            'certificate_id' => $certificate_id,
            'pdf_url' => wp_upload_dir()['baseurl'] . '/certificates/' . basename($pdf_path),
            'verification_url' => site_url('/certificate-verification/?id=' . $certificate_id)
        ));
    }

    private function generate_certificate_pdf($cert_id, $recipient_name, $course_name, $issue_date, $template_data, $custom_fields = array()) {
        // This would use a PDF library like TCPDF or mPDF
        // For now, we'll create a simple HTML-to-PDF conversion

        require_once('tcpdf/tcpdf.php');

        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->SetCreator('Kaushik Sannidhi Certificate Plugin');
        $pdf->SetTitle('Certificate - ' . $recipient_name);
        $pdf->SetSubject('Certificate');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->AddPage();

        // Process template data and replace placeholders
        $html = $template_data;
        $html = str_replace('{{recipient_name}}', $recipient_name, $html);
        $html = str_replace('{{course_name}}', $course_name, $html);
        $html = str_replace('{{issue_date}}', $issue_date, $html);
        $html = str_replace('{{certificate_id}}', $cert_id, $html);

        // Replace custom fields
        if (!empty($custom_fields['custom_fields'])) {
            foreach ($custom_fields['custom_fields'] as $key => $value) {
                $html = str_replace('{{' . $key . '}}', $value, $html);
            }
        }

        $pdf->writeHTML($html, true, false, true, false, '');

        $upload_dir = wp_upload_dir();
        $cert_dir = $upload_dir['basedir'] . '/certificates';
        $filename = 'cert-' . strtolower($cert_id) . '.pdf';
        $filepath = $cert_dir . '/' . $filename;

        $pdf->Output($filepath, 'F');

        return $filepath;
    }
}

// Initialize the plugin
new KS_Certificate_Plugin();

// Include TCPDF library (you would need to include this separately)
// You can download TCPDF from https://tcpdf.org/

?>