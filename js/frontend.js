jQuery(document).ready(function($) {
    // Certificate Verification
    $('#verify-btn').click(function() {
        let certificateId = $('#cert-id-input').val().trim();

        if (!certificateId) {
            alert('Please enter a certificate ID');
            return;
        }

        // Show loading state
        $(this).prop('disabled', true).text('Verifying...');
        $('#verification-result').html('<div class="verification-loading">🔍 Checking certificate...</div>');

        $.ajax({
            url: ks_cert_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'verify_certificate',
                nonce: ks_cert_ajax.nonce,
                certificate_id: certificateId
            },
            success: function(response) {
                $('#verify-btn').prop('disabled', false).text('Verify Certificate');

                if (response.success) {
                    if (response.data.valid) {
                        let cert = response.data.certificate;
                        $('#verification-result').html(`
                            <div class="verification-success">
                                <h4>✅ Certificate Verified</h4>
                                <div class="certificate-details">
                                    <p><strong>Certificate ID:</strong> ${cert.id}</p>
                                    <p><strong>Recipient:</strong> ${cert.recipient_name}</p>
                                    <p><strong>Course:</strong> ${cert.course_name}</p>
                                    <p><strong>Issue Date:</strong> ${cert.issue_date}</p>
                                    <p><a href="${cert.pdf_url}" target="_blank" class="button download-cert">📄 Download Certificate</a></p>
                                </div>
                            </div>
                        `);
                    } else {
                        $('#verification-result').html(`
                            <div class="verification-error">
                                <h4>❌ Certificate Not Found</h4>
                                <p>The certificate ID "${certificateId}" could not be found in our records.</p>
                                <p>Please check the ID and try again.</p>
                            </div>
                        `);
                    }
                } else {
                    $('#verification-result').html(`
                        <div class="verification-error">
                            <h4>❌ Verification Error</h4>
                            <p>There was an error verifying the certificate. Please try again later.</p>
                        </div>
                    `);
                }
            },
            error: function() {
                $('#verify-btn').prop('disabled', false).text('Verify Certificate');
                $('#verification-result').html(`
                    <div class="verification-error">
                        <h4>❌ Connection Error</h4>
                        <p>Could not connect to verification service. Please try again later.</p>
                    </div>
                `);
            }
        });
    });

    // Allow verification by pressing Enter
    $('#cert-id-input').keypress(function(e) {
        if (e.which === 13) {
            $('#verify-btn').click();
        }
    });

    // Check for certificate ID in URL parameters
    let urlParams = new URLSearchParams(window.location.search);
    let urlCertId = urlParams.get('id');
    if (urlCertId) {
        $('#cert-id-input').val(urlCertId);
        $('#verify-btn').click();
    }

    // Format certificate ID input (auto-uppercase and add dashes)
    $('#cert-id-input').on('input', function() {
        let value = $(this).val().toUpperCase().replace(/[^A-Z0-9]/g, '');
        if (value.length > 4 && value.indexOf('-') === -1) {
            value = value.substring(0, 4) + '-' + value.substring(4);
        }
        $(this).val(value);
    });
});