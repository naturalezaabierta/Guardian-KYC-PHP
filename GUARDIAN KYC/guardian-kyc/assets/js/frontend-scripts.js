(function($) {
    'use strict';
    $(function() {
        var verificationBtn = $('#guardian-kyc-start-verification-btn');
        if (!verificationBtn.length) { return; }
        
        var privacyCheckbox = $('#guardian_kyc_privacy_consent');
        var termsCheckbox = $('#guardian_kyc_terms_consent');

        function checkConsents() {
            var privacyOk = privacyCheckbox.length ? privacyCheckbox.is(':checked') : true;
            var termsOk = termsCheckbox.length ? termsCheckbox.is(':checked') : true;

            if (privacyOk && termsOk) {
                verificationBtn.prop('disabled', false).css('cursor', 'pointer');
            } else {
                verificationBtn.prop('disabled', true).css('cursor', 'not-allowed');
            }
        }
        checkConsents();
        $('#guardian_kyc_privacy_consent, #guardian_kyc_terms_consent').on('change', checkConsents);

        // Cuando el formulario se envía, desactivamos el botón para evitar doble clic.
        $('#guardian-kyc-form').on('submit', function() {
            verificationBtn.prop('disabled', true).text('Procesando...');
        });
    });
})(jQuery);