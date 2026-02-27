/**
 * Simple Web Marketing Attribution - Privacy & Consent Engine
 *
 * Handles the logic for detecting user consent choice and browser privacy signals.
 *
 * @package Simple_Web_Marketing_Attribution
 */

(function () {
	'use strict';

	window.swma_privacy = {
		/**
		 * Check if marketing attribution is allowed for the current visitor.
		 *
		 * @returns {boolean} True if tracking is allowed, False otherwise.
		 */
		hasMarketingConsent: function () {
			var settings = window.swma_settings || {};

			// Helper to check for truthy values (handles boolean, "1", "true").
			var isTruthy = function (val) {
				return val === true || val === "1" || val === "true";
			};

			var debug      = isTruthy( settings.debug_mode );
			var respectGpc = settings.respect_gpc !== false && settings.respect_gpc !== "0" && settings.respect_gpc !== "";

			// 1. Check Global Privacy Control.
			if (navigator.globalPrivacyControl === true) {
				if (respectGpc) {
					if (debug) {
						console.log( "SWMA: Tracking blocked by Global Privacy Control (GPC) signal." );
					}
					return false;
				} else {
					if (debug) {
						console.log( "SWMA: Global Privacy Control (GPC) signal detected but ignored per plugin settings." );
					}
				}
			}

			// 2. Check Admin Mode.
			var respectMarketingConsent = isTruthy( settings.respect_marketing_consent );

			if (respectMarketingConsent) {
				// If we respect analytics consent, we require explicit opt-in (handled via events).
				// Default is FALSE.
				return false;
			}

			// 3. Default Mode (Opt-out).
			return true;
		}
	};
})();
