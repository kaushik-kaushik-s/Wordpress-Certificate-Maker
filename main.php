<?php
/**
 * Plugin Name: Kaushik Sannidhi's Certificate Plugin
 * Description: Complete certificate generation and validation system with drag-and-drop editor, API, and QR codes
 * Version: 1.0.0
 * Author: Kaushik Sannidhi
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

define('KS_CERT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KS_CERT_PLUGIN_PATH', plugin_dir_path(__FILE__));

class KS_Certificate_Plugin {

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_ajax_save_certificate_template', array($this, 'save_certificate_template'));
        add_action('wp_ajax_nopriv_validate_certificate', array($this, 'validate_certificate'));
        add_action('wp_ajax_validate_certificate', array($this, 'validate_certificate'));
        add_action('rest_api_init', array($this, 'register_api_endpoints'));
        add_shortcode('certificate_validator', array($this, 'certificate_validator_shortcode'));
        register_activation_hook(__FILE__, array($this, 'activate'));
    }

    public function init() {
        $this->create_tables();
    }

    public function activate() {
        $this->create_tables();
        // Create upload directory for certificates
        $upload_dir = wp_upload_dir();
        $cert_dir = $upload_dir['basedir'] . '/certificates';
        if (!file_exists($cert_dir)) {
            wp_mkdir_p($cert_dir);
        }

        // Create certificates page
        $page_check = get_page_by_title('Certificate Validation');
        if (!$page_check) {
            wp_insert_post(array(
                'post_title' => 'Certificate Validation',
                'post_content' => '[certificate_validator]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_slug' => 'certificate-validation'
            ));
        }
    }

    public function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Certificates table
        $table_name = $wpdb->prefix . 'ks_certificates';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            certificate_id varchar(50) NOT NULL UNIQUE,
            template_id mediumint(9) NOT NULL,
            recipient_name varchar(255) NOT NULL,
            recipient_email varchar(255),
            course_name varchar(255),
            completion_date date,
            issue_date datetime DEFAULT CURRENT_TIMESTAMP,
            certificate_data longtext,
            pdf_path varchar(500),
            qr_code_path varchar(500),
            status varchar(20) DEFAULT 'active',
            PRIMARY KEY (id),
            KEY certificate_id (certificate_id)
        ) $charset_collate;";

        // Templates table
        $templates_table = $wpdb->prefix . 'ks_certificate_templates';
        $sql2 = "CREATE TABLE $templates_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            template_html longtext,
            background_image varchar(500),
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'active',
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Logs table
        $logs_table = $wpdb->prefix . 'ks_certificate_logs';
        $sql3 = "CREATE TABLE $logs_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            action varchar(100),
            certificate_id varchar(50),
            user_ip varchar(45),
            user_agent text,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            details text,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql2);
        dbDelta($sql3);
    }

    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('ks-cert-validator', KS_CERT_PLUGIN_URL . 'assets/validator.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('ks-cert-styles', KS_CERT_PLUGIN_URL . 'assets/styles.css', array(), '1.0.0');
        wp_localize_script('ks-cert-validator', 'ks_cert_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ks_cert_nonce')
        ));
    }

    public function admin_enqueue_scripts() {
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-resizable');
        wp_enqueue_script('ks-cert-editor', KS_CERT_PLUGIN_URL . 'assets/editor.js', array('jquery', 'jquery-ui-draggable', 'jquery-ui-resizable'), '1.0.0', true);
        wp_enqueue_style('ks-cert-admin', KS_CERT_PLUGIN_URL . 'assets/admin.css', array(), '1.0.0');
        wp_enqueue_media();
    }

    public function admin_menu() {
        add_menu_page(
            'Certificate Manager',
            'Certificates',
            'manage_options',
            'ks-certificates',
            array($this, 'admin_page'),
            'dashicons-awards',
            30
        );

        add_submenu_page(
            'ks-certificates',
            'Certificate Templates',
            'Templates',
            'manage_options',
            'ks-cert-templates',
            array($this, 'templates_page')
        );

        add_submenu_page(
            'ks-certificates',
            'Certificate List',
            'All Certificates',
            'manage_options',
            'ks-cert-list',
            array($this, 'certificates_list_page')
        );

        add_submenu_page(
            'ks-certificates',
            'Certificate Logs',
            'Logs',
            'manage_options',
            'ks-cert-logs',
            array($this, 'logs_page')
        );
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Certificate Manager Dashboard</h1>
            <div class="ks-cert-dashboard">
                <div class="ks-cert-stats">
                    <?php
                    global $wpdb;
                    $total_certs = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ks_certificates");
                    $active_certs = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ks_certificates WHERE status = 'active'");
                    $total_templates = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ks_certificate_templates");
                    ?>
                    <div class="stat-box">
                        <h3><?php echo $total_certs; ?></h3>
                        <p>Total Certificates</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo $active_certs; ?></h3>
                        <p>Active Certificates</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo $total_templates; ?></h3>
                        <p>Templates</p>
                    </div>
                </div>

                <div class="ks-cert-api-info">
                    <h2>API Endpoints</h2>
                    <p><strong>Generate Certificate:</strong> POST <?php echo home_url('/wp-json/ks-cert/v1/generate'); ?></p>
                    <p><strong>Validate Certificate:</strong> GET <?php echo home_url('/wp-json/ks-cert/v1/validate/{certificate_id}'); ?></p>
                    <p><strong>Validation Portal:</strong> <a href="<?php echo home_url('/certificate-validation/'); ?>" target="_blank"><?php echo home_url('/certificate-validation/'); ?></a></p>
                </div>
            </div>
        </div>
        <?php
    }

    public function templates_page() {
        if (isset($_POST['save_template'])) {
            $this->save_template();
        }

        $template_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $template = null;

        if ($template_id) {
            global $wpdb;
            $template = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ks_certificate_templates WHERE id = %d",
                $template_id
            ));
        }
        ?>
        <div class="wrap">
            <h1>Certificate Template Editor</h1>
            <form method="post" enctype="multipart/form-data">
                <table class="form-table">
                    <tr>
                        <th scope="row">Template Name</th>
                        <td>
                            <input type="text" name="template_name" value="<?php echo $template ? esc_attr($template->name) : ''; ?>" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Background Image</th>
                        <td>
                            <input type="hidden" name="background_image" id="background_image" value="<?php echo $template ? esc_attr($template->background_image) : ''; ?>">
                            <button type="button" id="select_bg_image" class="button">Select Background Image</button>
                            <div id="bg_preview">
                                <?php if ($template && $template->background_image): ?>
                                    <img src="<?php echo esc_url($template->background_image); ?>" style="max-width: 200px; height: auto;">
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                </table>

                <h2>Template Editor</h2>
                <div id="certificate-editor">
                    <div id="editor-toolbar">
                        <button type="button" id="add-text" class="button">Add Text</button>
                        <button type="button" id="add-image" class="button">Add Image</button>
                        <button type="button" id="add-qr" class="button">Add QR Code</button>
                        <button type="button" id="preview-cert" class="button">Preview</button>
                    </div>

                    <div id="certificate-canvas" style="position: relative; width: 800px; height: 600px; border: 1px solid #ccc; background: white; margin: 20px 0;">
                        <?php if ($template && $template->template_html): ?>
                            <?php echo $template->template_html; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <input type="hidden" name="template_html" id="template_html" value="<?php echo $template ? esc_attr($template->template_html) : ''; ?>">
                <input type="hidden" name="template_id" value="<?php echo $template_id; ?>">

                <p class="submit">
                    <input type="submit" name="save_template" class="button-primary" value="Save Template">
                </p>
            </form>
        </div>

        <script>
            jQuery(document).ready(function($) {
                // Media uploader for background image
                $('#select_bg_image').click(function(e) {
                    e.preventDefault();
                    var mediaUploader = wp.media({
                        title: 'Select Background Image',
                        button: { text: 'Use this image' },
                        multiple: false
                    });

                    mediaUploader.on('select', function() {
                        var attachment = mediaUploader.state().get('selection').first().toJSON();
                        $('#background_image').val(attachment.url);
                        $('#bg_preview').html('<img src="' + attachment.url + '" style="max-width: 200px; height: auto;">');
                        $('#certificate-canvas').css('background-image', 'url(' + attachment.url + ')');
                        $('#certificate-canvas').css('background-size', 'cover');
                    });

                    mediaUploader.open();
                });

                // Set background if exists
                var bgImage = $('#background_image').val();
                if (bgImage) {
                    $('#certificate-canvas').css('background-image', 'url(' + bgImage + ')');
                    $('#certificate-canvas').css('background-size', 'cover');
                }

                // Add text element
                $('#add-text').click(function() {
                    var textElement = $('<div class="cert-element cert-text" style="position: absolute; top: 50px; left: 50px; padding: 10px; border: 1px dashed #ccc; cursor: move;">Sample Text</div>');
                    $('#certificate-canvas').append(textElement);
                    makeElementDraggable(textElement);
                });

                // Add image element
                $('#add-image').click(function() {
                    var imageUploader = wp.media({
                        title: 'Select Image',
                        button: { text: 'Use this image' },
                        multiple: false
                    });

                    imageUploader.on('select', function() {
                        var attachment = imageUploader.state().get('selection').first().toJSON();
                        var imgElement = $('<div class="cert-element cert-image" style="position: absolute; top: 100px; left: 100px; border: 1px dashed #ccc; cursor: move;"><img src="' + attachment.url + '" style="max-width: 200px; height: auto;"></div>');
                        $('#certificate-canvas').append(imgElement);
                        makeElementDraggable(imgElement);
                    });

                    imageUploader.open();
                });

                // Add QR code placeholder
                $('#add-qr').click(function() {
                    var qrElement = $('<div class="cert-element cert-qr" style="position: absolute; top: 150px; left: 150px; padding: 20px; border: 1px dashed #ccc; cursor: move; background: #f0f0f0;">QR Code</div>');
                    $('#certificate-canvas').append(qrElement);
                    makeElementDraggable(qrElement);
                });

                function makeElementDraggable(element) {
                    element.draggable({
                        containment: '#certificate-canvas'
                    }).resizable({
                        containment: '#certificate-canvas'
                    });

                    // Double click to edit text
                    element.dblclick(function() {
                        if ($(this).hasClass('cert-text')) {
                            var currentText = $(this).text();
                            var newText = prompt('Enter text:', currentText);
                            if (newText !== null) {
                                $(this).text(newText);
                            }
                        }
                    });

                    // Right click to delete
                    element.contextmenu(function(e) {
                        e.preventDefault();
                        if (confirm('Delete this element?')) {
                            $(this).remove();
                        }
                    });
                }

                // Make existing elements draggable
                $('.cert-element').each(function() {
                    makeElementDraggable($(this));
                });

                // Save template HTML before submit
                $('form').submit(function() {
                    $('#template_html').val($('#certificate-canvas').html());
                });
            });
        </script>
        <?php
    }

    public function save_template() {
        global $wpdb;

        $template_id = intval($_POST['template_id']);
        $name = sanitize_text_field($_POST['template_name']);
        $html = wp_kses_post($_POST['template_html']);
        $background = esc_url_raw($_POST['background_image']);

        if ($template_id) {
            $wpdb->update(
                $wpdb->prefix . 'ks_certificate_templates',
                array(
                    'name' => $name,
                    'template_html' => $html,
                    'background_image' => $background,
                    'updated_date' => current_time('mysql')
                ),
                array('id' => $template_id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
            echo '<div class="notice notice-success"><p>Template updated successfully!</p></div>';
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'ks_certificate_templates',
                array(
                    'name' => $name,
                    'template_html' => $html,
                    'background_image' => $background
                ),
                array('%s', '%s', '%s')
            );
            echo '<div class="notice notice-success"><p>Template created successfully!</p></div>';
        }
    }

    public function certificates_list_page() {
        global $wpdb;

        $certificates = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ks_certificates ORDER BY issue_date DESC");
        ?>
        <div class="wrap">
            <h1>All Certificates</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th>Certificate ID</th>
                    <th>Recipient Name</th>
                    <th>Email</th>
                    <th>Course</th>
                    <th>Issue Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($certificates as $cert): ?>
                    <tr>
                        <td><?php echo esc_html($cert->certificate_id); ?></td>
                        <td><?php echo esc_html($cert->recipient_name); ?></td>
                        <td><?php echo esc_html($cert->recipient_email); ?></td>
                        <td><?php echo esc_html($cert->course_name); ?></td>
                        <td><?php echo esc_html($cert->issue_date); ?></td>
                        <td><?php echo esc_html($cert->status); ?></td>
                        <td>
                            <?php if ($cert->pdf_path): ?>
                                <a href="<?php echo esc_url($cert->pdf_path); ?>" target="_blank" class="button button-small">View PDF</a>
                            <?php endif; ?>
                            <a href="<?php echo home_url('/certificate-validation/?cert_id=' . $cert->certificate_id); ?>" target="_blank" class="button button-small">Validate</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function logs_page() {
        global $wpdb;

        $logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ks_certificate_logs ORDER BY timestamp DESC LIMIT 100");
        ?>
        <div class="wrap">
            <h1>Certificate Logs</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Action</th>
                    <th>Certificate ID</th>
                    <th>IP Address</th>
                    <th>Details</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log->timestamp); ?></td>
                        <td><?php echo esc_html($log->action); ?></td>
                        <td><?php echo esc_html($log->certificate_id); ?></td>
                        <td><?php echo esc_html($log->user_ip); ?></td>
                        <td><?php echo esc_html($log->details); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function register_api_endpoints() {
        register_rest_route('ks-cert/v1', '/generate', array(
            'methods' => 'POST',
            'callback' => array($this, 'api_generate_certificate'),
            'permission_callback' => array($this, 'api_permission_check')
        ));

        register_rest_route('ks-cert/v1', '/validate/(?P<cert_id>[a-zA-Z0-9]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_validate_certificate'),
            'permission_callback' => '__return_true'
        ));
    }

    public function api_permission_check() {
        // Add your API key validation here
        return true; // For now, allow all requests
    }

    public function api_generate_certificate($request) {
        $params = $request->get_json_params();

        $template_id = intval($params['template_id']);
        $recipient_name = sanitize_text_field($params['recipient_name']);
        $recipient_email = sanitize_email($params['recipient_email']);
        $course_name = sanitize_text_field($params['course_name']);
        $completion_date = sanitize_text_field($params['completion_date']);

        // Generate unique certificate ID
        $certificate_id = $this->generate_certificate_id();

        // Get template
        global $wpdb;
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ks_certificate_templates WHERE id = %d",
            $template_id
        ));

        if (!$template) {
            return new WP_Error('template_not_found', 'Template not found', array('status' => 404));
        }

        // Generate certificate
        $pdf_path = $this->generate_certificate_pdf($template, array(
            'certificate_id' => $certificate_id,
            'recipient_name' => $recipient_name,
            'recipient_email' => $recipient_email,
            'course_name' => $course_name,
            'completion_date' => $completion_date
        ));

        // Generate QR code
        $qr_path = $this->generate_qr_code($certificate_id);

        // Save to database
        $wpdb->insert(
            $wpdb->prefix . 'ks_certificates',
            array(
                'certificate_id' => $certificate_id,
                'template_id' => $template_id,
                'recipient_name' => $recipient_name,
                'recipient_email' => $recipient_email,
                'course_name' => $course_name,
                'completion_date' => $completion_date,
                'certificate_data' => json_encode($params),
                'pdf_path' => $pdf_path,
                'qr_code_path' => $qr_path
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        // Log the action
        $this->log_action('certificate_generated', $certificate_id, 'Certificate generated via API');

        return array(
            'success' => true,
            'certificate_id' => $certificate_id,
            'pdf_url' => $pdf_path,
            'validation_url' => home_url('/certificate-validation/?cert_id=' . $certificate_id)
        );
    }

    public function api_validate_certificate($request) {
        $cert_id = $request['cert_id'];

        global $wpdb;
        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ks_certificates WHERE certificate_id = %s AND status = 'active'",
            $cert_id
        ));

        $this->log_action('certificate_validated', $cert_id, 'Certificate validation via API');

        if ($certificate) {
            return array(
                'valid' => true,
                'certificate_id' => $certificate->certificate_id,
                'recipient_name' => $certificate->recipient_name,
                'course_name' => $certificate->course_name,
                'completion_date' => $certificate->completion_date,
                'issue_date' => $certificate->issue_date
            );
        } else {
            return array(
                'valid' => false,
                'message' => 'Certificate not found or inactive'
            );
        }
    }

    public function certificate_validator_shortcode($atts) {
        $cert_id = isset($_GET['cert_id']) ? sanitize_text_field($_GET['cert_id']) : '';

        ob_start();
        ?>
        <div id="certificate-validator">
            <h2>Certificate Validation</h2>
            <form id="cert-validation-form">
                <label for="cert_id">Enter Certificate ID:</label>
                <input type="text" id="cert_id" name="cert_id" value="<?php echo esc_attr($cert_id); ?>" required>
                <button type="submit">Validate Certificate</button>
            </form>

            <div id="validation-result" style="margin-top: 20px;"></div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                // Auto-validate if cert_id is provided
                if ($('#cert_id').val()) {
                    $('#cert-validation-form').submit();
                }

                $('#cert-validation-form').submit(function(e) {
                    e.preventDefault();

                    var certId = $('#cert_id').val();
                    if (!certId) return;

                    $('#validation-result').html('<p>Validating...</p>');

                    $.ajax({
                        url: ks_cert_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'validate_certificate',
                            cert_id: certId,
                            nonce: ks_cert_ajax.nonce
                        },
                        success: function(response) {
                            if (response.success && response.data.valid) {
                                var cert = response.data;
                                $('#validation-result').html(
                                    '<div class="cert-valid">' +
                                    '<h3>✓ Valid Certificate</h3>' +
                                    '<p><strong>Certificate ID:</strong> ' + cert.certificate_id + '</p>' +
                                    '<p><strong>Recipient:</strong> ' + cert.recipient_name + '</p>' +
                                    '<p><strong>Course:</strong> ' + cert.course_name + '</p>' +
                                    '<p><strong>Completion Date:</strong> ' + cert.completion_date + '</p>' +
                                    '<p><strong>Issue Date:</strong> ' + cert.issue_date + '</p>' +
                                    '</div>'
                                );
                            } else {
                                $('#validation-result').html(
                                    '<div class="cert-invalid">' +
                                    '<h3>✗ Invalid Certificate</h3>' +
                                    '<p>The certificate ID you entered is not valid or has been revoked.</p>' +
                                    '</div>'
                                );
                            }
                        },
                        error: function() {
                            $('#validation-result').html('<p>Error validating certificate. Please try again.</p>');
                        }
                    });
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }

    public function validate_certificate() {
        check_ajax_referer('ks_cert_nonce', 'nonce');

        $cert_id = sanitize_text_field($_POST['cert_id']);

        global $wpdb;
        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ks_certificates WHERE certificate_id = %s AND status = 'active'",
            $cert_id
        ));

        $this->log_action('certificate_validated', $cert_id, 'Certificate validation via portal');

        if ($certificate) {
            wp_send_json_success(array(
                'valid' => true,
                'certificate_id' => $certificate->certificate_id,
                'recipient_name' => $certificate->recipient_name,
                'course_name' => $certificate->course_name,
                'completion_date' => $certificate->completion_date,
                'issue_date' => $certificate->issue_date
            ));
        } else {
            wp_send_json_success(array(
                'valid' => false
            ));
        }
    }

    private function generate_certificate_id() {
        return 'CERT-' . strtoupper(wp_generate_password(8, false));
    }

    private function generate_certificate_pdf($template, $data) {
        $html = $template->template_html;

        // Replace placeholders
        $html = str_replace('{{recipient_name}}', $data['recipient_name'], $html);
        $html = str_replace('{{course_name}}', $data['course_name'], $html);
        $html = str_replace('{{completion_date}}', $data['completion_date'], $html);
        $html = str_replace('{{certificate_id}}', $data['certificate_id'], $html);
        $html = str_replace('{{issue_date}}', date('Y-m-d'), $html);

        // Generate QR code first and get local path
        $qr_path = $this->generate_qr_code($data['certificate_id']);

        // Convert QR URL to local file path for dompdf
        if (strpos($qr_path, home_url()) === 0) {
            $qr_local_path = str_replace(home_url(), ABSPATH, $qr_path);
            $qr_local_path = str_replace('/wp-content/', 'wp-content/', $qr_local_path);
        } else {
            $qr_local_path = $qr_path;
        }

        // Replace QR code placeholder with actual image
        $html = str_replace('QR Code', '<img src="' . $qr_local_path . '" style="width: 100px; height: 100px;">', $html);

        // Convert background image URL to local path for dompdf
        $background_image = '';
        if ($template->background_image) {
            if (strpos($template->background_image, home_url()) === 0) {
                $bg_local_path = str_replace(home_url(), '', $template->background_image);
                $bg_local_path = ABSPATH . ltrim($bg_local_path, '/');
                $background_image = 'url(' . $bg_local_path . ')';
            } else {
                $background_image = 'url(' . $template->background_image . ')';
            }
        }

        // Wrap in full HTML with improved styling for PDF
        $full_html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                @page {
                    margin: 0;
                    size: A4 landscape;
                }
                body { 
                    margin: 0; 
                    padding: 0; 
                    font-family: Arial, Helvetica, sans-serif;
                    width: 297mm;
                    height: 210mm;
                }
                .certificate-container {
                    width: 100%;
                    height: 100%;
                    position: relative;
                    background-image: ' . $background_image . ';
                    background-size: cover;
                    background-position: center;
                    background-repeat: no-repeat;
                }
                .cert-element { 
                    position: absolute; 
                    word-wrap: break-word;
                }
                .cert-text { 
                    font-family: Arial, Helvetica, sans-serif;
                    line-height: 1.4;
                }
                .cert-image img { 
                    max-width: 100%; 
                    height: auto; 
                    display: block;
                }
                .cert-qr {
                    text-align: center;
                }
                .cert-qr img {
                    max-width: 150px;
                    max-height: 150px;
                }
            </style>
        </head>
        <body>
            <div class="certificate-container">' . $html . '</div>
        </body>
        </html>';

        $upload_dir = wp_upload_dir();
        $pdf_filename = 'certificate_' . $data['certificate_id'] . '.pdf';
        $pdf_path = $upload_dir['basedir'] . '/certificates/' . $pdf_filename;
        $pdf_url = $upload_dir['baseurl'] . '/certificates/' . $pdf_filename;

        // Check if dompdf is available
        $dompdf_path = KS_CERT_PLUGIN_PATH . 'vendor/autoload.php';
        if (file_exists($dompdf_path)) {
            try {
                // Use dompdf for PDF generation
                require_once $dompdf_path;

                $options = new \Dompdf\Options();
                $options->set('isRemoteEnabled', true);
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isFontSubsettingEnabled', true);
                $options->set('defaultFont', 'Arial');

                $dompdf = new \Dompdf\Dompdf($options);
                $dompdf->loadHtml($full_html);
                $dompdf->setPaper('A4', 'landscape');
                $dompdf->render();

                // Save the PDF
                $pdf_content = $dompdf->output();
                if (file_put_contents($pdf_path, $pdf_content)) {
                    // Also save HTML version for debugging
                    file_put_contents(str_replace('.pdf', '.html', $pdf_path), $full_html);
                    return $pdf_url;
                } else {
                    throw new Exception('Failed to write PDF file');
                }

            } catch (Exception $e) {
                // Log the error
                error_log('Certificate PDF generation error: ' . $e->getMessage());

                // Fallback to HTML file
                file_put_contents(str_replace('.pdf', '.html', $pdf_path), $full_html);
                return str_replace('.pdf', '.html', $pdf_url);
            }
        } else {
            // Fallback: Create HTML file if dompdf is not available
            file_put_contents(str_replace('.pdf', '.html', $pdf_path), $full_html);

            // Log warning
            error_log('DOMPDF not found. Please install dompdf to generate PDF certificates.');

            return str_replace('.pdf', '.html', $pdf_url);
        }
    }

    private function generate_qr_code($certificate_id) {
        // Generate QR code URL pointing to validation page
        $validation_url = home_url('/certificate-validation/?cert_id=' . $certificate_id);

        // Use Google Charts API for QR code generation
        $qr_url = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode($validation_url);

        // Save QR code image locally
        $upload_dir = wp_upload_dir();
        $qr_filename = 'qr_' . $certificate_id . '.png';
        $qr_path = $upload_dir['basedir'] . '/certificates/' . $qr_filename;
        $qr_url_local = $upload_dir['baseurl'] . '/certificates/' . $qr_filename;

        // Download and save QR code
        $qr_data = file_get_contents($qr_url);
        if ($qr_data) {
            file_put_contents($qr_path, $qr_data);
            return $qr_url_local;
        }

        return $qr_url; // Fallback to Google Charts URL
    }

    private function log_action($action, $certificate_id = '', $details = '') {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'ks_certificate_logs',
            array(
                'action' => $action,
                'certificate_id' => $certificate_id,
                'user_ip' => $this->get_user_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'details' => $details
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }

    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
}

// Initialize the plugin
new KS_Certificate_Plugin();

// CSS Styles
function ks_cert_add_inline_styles() {
    ?>
    <style>
        .ks-cert-dashboard {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 20px 0;
        }

        .ks-cert-stats {
            display: flex;
            gap: 20px;
            flex: 1;
        }

        .stat-box {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
            min-width: 120px;
        }

        .stat-box h3 {
            margin: 0 0 10px 0;
            font-size: 2em;
            color: #0073aa;
        }

        .ks-cert-api-info {
            flex: 1;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
        }

        #certificate-editor {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }

        #editor-toolbar {
            margin-bottom: 20px;
        }

        #editor-toolbar .button {
            margin-right: 10px;
        }

        #certificate-canvas {
            background-color: white;
            border: 2px solid #ddd;
            border-radius: 5px;
            position: relative;
            overflow: hidden;
        }

        .cert-element {
            border: 1px dashed #007cba !important;
            min-width: 50px;
            min-height: 30px;
        }

        .cert-element:hover {
            border-color: #00a0d2 !important;
            background-color: rgba(0, 160, 210, 0.1);
        }

        .cert-text {
            padding: 5px;
            background: rgba(255, 255, 255, 0.8);
            font-size: 16px;
        }

        .cert-image {
            background: rgba(255, 255, 255, 0.8);
            padding: 5px;
        }

        .cert-qr {
            background: rgba(240, 240, 240, 0.9);
            text-align: center;
            padding: 10px;
            font-size: 12px;
        }

        #certificate-validator {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 10px;
        }

        #certificate-validator h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        #cert-validation-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: center;
            align-items: end;
        }

        #cert-validation-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        #cert-validation-form input[type="text"] {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 250px;
        }

        #cert-validation-form button {
            padding: 10px 20px;
            background: #007cba;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        #cert-validation-form button:hover {
            background: #005a87;
        }

        .cert-valid {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 20px;
            border-radius: 5px;
        }

        .cert-invalid {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 20px;
            border-radius: 5px;
        }

        .cert-valid h3,
        .cert-invalid h3 {
            margin-top: 0;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .ks-cert-dashboard {
                flex-direction: column;
            }

            .ks-cert-stats {
                flex-direction: column;
            }

            #certificate-canvas {
                width: 100%;
                max-width: 800px;
                height: auto;
                aspect-ratio: 4/3;
            }

            #cert-validation-form {
                flex-direction: column;
                align-items: stretch;
            }

            #cert-validation-form input[type="text"] {
                width: 100%;
            }
        }
    </style>
    <?php
}
add_action('admin_head', 'ks_cert_add_inline_styles');
add_action('wp_head', 'ks_cert_add_inline_styles');

// JavaScript for frontend validation
function ks_cert_add_inline_scripts() {
    if (!is_admin()) {
        ?>
        <script>
            var ks_cert_ajax = {
                ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
                nonce: '<?php echo wp_create_nonce('ks_cert_nonce'); ?>'
            };
        </script>
        <?php
    }
}
add_action('wp_head', 'ks_cert_add_inline_scripts');

// Admin JavaScript for template editor
function ks_cert_admin_inline_scripts() {
    if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'ks-cert-templates') {
        ?>
        <script>
            jQuery(document).ready(function($) {
                // Enhanced template editor functionality
                var elementCounter = 0;

                // Template variables that can be used
                var templateVars = [
                    '{{recipient_name}}',
                    '{{course_name}}',
                    '{{completion_date}}',
                    '{{certificate_id}}',
                    '{{issue_date}}'
                ];

                // Add template variable buttons
                $('#editor-toolbar').append('<div style="margin-top: 10px;"><strong>Template Variables:</strong></div>');
                templateVars.forEach(function(variable) {
                    $('#editor-toolbar').append('<button type="button" class="button template-var" data-var="' + variable + '">' + variable + '</button>');
                });

                // Insert template variables
                $('.template-var').click(function() {
                    var variable = $(this).data('var');
                    var textElement = $('<div class="cert-element cert-text" style="position: absolute; top: 50px; left: 50px; padding: 10px; border: 1px dashed #ccc; cursor: move;">' + variable + '</div>');
                    $('#certificate-canvas').append(textElement);
                    makeElementDraggable(textElement);
                });

                // Enhanced element creation with better positioning
                $('#add-text').off('click').click(function() {
                    elementCounter++;
                    var textElement = $('<div class="cert-element cert-text" style="position: absolute; top: ' + (50 + elementCounter * 20) + 'px; left: ' + (50 + elementCounter * 20) + 'px; padding: 10px; border: 1px dashed #ccc; cursor: move;">Sample Text</div>');
                    $('#certificate-canvas').append(textElement);
                    makeElementDraggable(textElement);
                });

                // Enhanced draggable function with more options
                function makeElementDraggable(element) {
                    element.draggable({
                        containment: '#certificate-canvas',
                        grid: [5, 5] // Snap to grid
                    }).resizable({
                        containment: '#certificate-canvas',
                        handles: 'all'
                    });

                    // Click to select
                    element.click(function(e) {
                        e.stopPropagation();
                        $('.cert-element').removeClass('selected');
                        $(this).addClass('selected');
                        showPropertyPanel($(this));
                    });

                    // Double click to edit
                    element.dblclick(function(e) {
                        e.stopPropagation();
                        if ($(this).hasClass('cert-text')) {
                            var currentText = $(this).text();
                            var newText = prompt('Enter text:', currentText);
                            if (newText !== null && newText !== '') {
                                $(this).text(newText);
                            }
                        }
                    });

                    // Right click context menu
                    element.contextmenu(function(e) {
                        e.preventDefault();
                        if (confirm('Delete this element?')) {
                            $(this).remove();
                        }
                    });
                }

                // Property panel for selected elements
                function showPropertyPanel(element) {
                    var panel = $('#property-panel');
                    if (panel.length === 0) {
                        panel = $('<div id="property-panel" style="position: fixed; right: 20px; top: 100px; width: 250px; background: white; border: 1px solid #ccc; padding: 15px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);"></div>');
                        $('body').append(panel);
                    }

                    var html = '<h4>Element Properties</h4>';

                    if (element.hasClass('cert-text')) {
                        html += '<label>Font Size:</label><input type="number" id="font-size" value="16" min="8" max="72"><br><br>';
                        html += '<label>Font Weight:</label><select id="font-weight"><option value="normal">Normal</option><option value="bold">Bold</option></select><br><br>';
                        html += '<label>Text Color:</label><input type="color" id="text-color" value="#000000"><br><br>';
                    }

                    html += '<label>Left:</label><input type="number" id="elem-left" value="' + parseInt(element.css('left')) + '"><br><br>';
                    html += '<label>Top:</label><input type="number" id="elem-top" value="' + parseInt(element.css('top')) + '"><br><br>';
                    html += '<button type="button" id="apply-props" class="button">Apply</button>';
                    html += '<button type="button" id="close-props" class="button" style="margin-left: 10px;">Close</button>';

                    panel.html(html);

                    // Apply properties
                    $('#apply-props').click(function() {
                        if (element.hasClass('cert-text')) {
                            element.css('font-size', $('#font-size').val() + 'px');
                            element.css('font-weight', $('#font-weight').val());
                            element.css('color', $('#text-color').val());
                        }
                        element.css('left', $('#elem-left').val() + 'px');
                        element.css('top', $('#elem-top').val() + 'px');
                    });

                    // Close panel
                    $('#close-props').click(function() {
                        panel.remove();
                    });
                }

                // Click outside to deselect
                $('#certificate-canvas').click(function(e) {
                    if (e.target === this) {
                        $('.cert-element').removeClass('selected');
                        $('#property-panel').remove();
                    }
                });

                // Keyboard shortcuts
                $(document).keydown(function(e) {
                    if (e.key === 'Delete') {
                        $('.cert-element.selected').remove();
                        $('#property-panel').remove();
                    }
                });

                // Save template with validation
                $('form').submit(function(e) {
                    var templateName = $('input[name="template_name"]').val().trim();
                    if (!templateName) {
                        alert('Please enter a template name.');
                        e.preventDefault();
                        return false;
                    }

                    var canvasHtml = $('#certificate-canvas').html();
                    $('#template_html').val(canvasHtml);

                    // Show loading
                    $(this).find('input[type="submit"]').val('Saving...').prop('disabled', true);
                });
            });
        </script>
        <?php
    }
}
add_action('admin_footer', 'ks_cert_admin_inline_scripts');

?>