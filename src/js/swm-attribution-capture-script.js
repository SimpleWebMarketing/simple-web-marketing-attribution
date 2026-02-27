/**
 * Simple Web Marketing Attribution - Capture Script
 *
 * This file handles capturing UTM parameters and referrer information,
 * storing it in LocalStorage, and populating form fields.
 *
 * @package Simple_Web_Marketing_Attribution
 */

/* eslint-disable no-console */
(function (global) {
	"use strict";

	var AttributionCore = {
		/**
		 * Internal buffer to hold attribution data before it's committed to localStorage.
		 * This ensures we don't write to the user's device without consent.
		 */
		_swma_memoryLog: null,

		swma_getQueryParams: function () {
			var params = {};
			var query  = window.location.search.substring( 1 );
			if ( ! query) {
				return params;
			}
			var pairs = query.split( '&' );
			for ( var i = 0, len = pairs.length; i < len; i++ ) {
				var pair = pairs[ i ].split( '=' );
				if ( pair.length !== 2 ) {
					continue;
				}
				var key       = decodeURIComponent( pair[ 0 ] );
				var value     = decodeURIComponent( pair[ 1 ].replace( /\+/g, ' ' ) );
				params[ key ] = value;
			}
			return params;
		},

		swma_logAttributionVisit: function () {
			var settings = window.swma_settings || {};
			var debug    = settings.debug_mode === true || settings.debug_mode === "1" || settings.debug_mode === "true";
			if (debug) {
				console.log( "SWMA: Initializing attribution log capture..." );
			}

			var params    = this.swma_getQueryParams();
			var now       = new Date().toISOString();
			var url       = window.location.href;
			var title     = document.title;
			var utmParams = {};
			var hasUtm    = false;

			for (var key in params) {
				if (
				Object.prototype.hasOwnProperty.call( params, key ) &&
				key.indexOf( "utm_" ) === 0
				) {
					utmParams[key] = params[key];
					hasUtm         = true;
				}
			}

			if (hasUtm && debug) {
				console.log( "SWMA: Detected UTM parameters:", utmParams );
			}

			var entry = { timestamp: now, url: url, title: title };

			var ref     = document.referrer;
			var curHost = window.location.hostname;
			var refHost = null;
			if (ref) {
				var a   = document.createElement( "a" );
				a.href  = ref;
				refHost = a.hostname;
			}

			if (hasUtm) {
				entry.utm_source = (utmParams.utm_source || "(none)").toLowerCase();
				entry.utm_medium = (utmParams.utm_medium || "(none)").toLowerCase();
				if (utmParams.utm_campaign) {
					entry.utm_campaign = utmParams.utm_campaign.toLowerCase();
				}
				if (utmParams.utm_term) {
					entry.utm_term = utmParams.utm_term.toLowerCase();
				}
				if (utmParams.utm_content) {
					entry.utm_content = utmParams.utm_content.toLowerCase();
				}
			} else if (refHost && refHost !== curHost) {
				if (debug) {
					console.log( "SWMA: Referrer detected:", refHost );
				}

				// Check for Webmail Providers.
				var webmailProviders = [
					"mail.google.", "googlemail.", "mail.yahoo.", "outlook.live.",
					"mail.live.", "hotmail.", "mail.aol.", "mail.proton.",
					"mail.zoho.", "gmx.", "mail.com", "icloud.com", "me.com", "mac.com",
					"tuta.", "fastmail.", "neo.com", "mail.ru", "mail.yandex.", "web.de",
					"rediffmail.", "163.com", "naver.com", "qq.com", "libero.it",
					"btinternet.", "orange.fr", "wanadoo.fr", "sbcglobal.", "att.net",
					"bellsouth.net", "cox.net", "verizon.net", "earthlink.net", "juno.com",
					"comcast.net", "optonline.net", "bluewin.ch", "alice.it", "wp.pl",
					"t-online.de", "uol.com.br", "shaw.ca", "rogers.com"
				];

				var isEmail = false;
				for ( var i = 0; i < webmailProviders.length; i++ ) {
					if (refHost.indexOf( webmailProviders[i] ) !== -1) {
						entry.utm_source = refHost;
						entry.utm_medium = "email";
						isEmail          = true;
						break;
					}
				}

				if ( ! isEmail) {
					// Check for Organic Search Engines.
					var searchEngines = {
						"google.": "google",
						"bing.": "bing",
						"yahoo.": "yahoo",
						"duckduckgo.": "duckduckgo",
						"baidu.": "baidu",
						"yandex.": "yandex",
						"ask.": "ask"
					};

					var isOrganic = false;
					for (var engine in searchEngines) {
						if (refHost.indexOf( engine ) !== -1) {
							entry.utm_source = searchEngines[engine];
							entry.utm_medium = "organic";
							isOrganic        = true;
							break;
						}
					}

					if ( ! isOrganic) {
						entry.utm_source = ref.toLowerCase();
						entry.utm_medium = "referral";
					}
				}
			} else {
				if (debug) {
					console.log( "SWMA: No external source detected, defaulting to (direct)." );
				}
				entry.utm_source = "(direct)";
				entry.utm_medium = "(none)";
			}

			// Start with the existing log from storage OR an empty array.
			var log = [];
			try {
				log = JSON.parse( localStorage.getItem( "swma_log" ) || "[]" );
			} catch (_e) {
				log = [];
			}

			// If the new entry has better attribution than the first one, prepend it.
			if (log.length > 0 && hasUtm && log[0].utm_source === "(direct)") {
				if (debug) {
					console.log( "SWMA: Upgrading (direct) first-touch to UTM." );
				}
				var oldFirstEntry = log.shift();
				log.unshift( entry );
				log.push( oldFirstEntry );
			} else {
				log.push( entry );
			}

			// Store entire resulting log in memory buffer.
			this._swma_memoryLog = log;

			// Check with the Privacy Engine. If consent is granted, commit data and handle forms.
			this.swma_enforcePrivacy();
		},

		swma_getAttributionLog: function () {
			if (this._swma_memoryLog !== null) {
				return this._swma_memoryLog;
			}

			try {
				return JSON.parse( localStorage.getItem( "swma_log" ) || "[]" );
			} catch (_e) {
				return [];
			}
		},

		swma_saveAttributionLog: function (log) {
			if ( ! log) {
				return;
			}
			localStorage.setItem( "swma_log", JSON.stringify( log ) );
		},

		/**
		 * Physically write the memory buffer to localStorage.
		 */
		swma_commitLogToStorage: function () {
			if (this._swma_memoryLog) {
				this.swma_saveAttributionLog( this._swma_memoryLog );
			}
		},

		/**
		 * Check with the Privacy Engine. If consent is granted, commit data and handle forms.
		 */
		swma_enforcePrivacy: function () {
			if (typeof window.swma_privacy !== 'undefined') {
				var settings = window.swma_settings || {};
				var debug    = settings.debug_mode === true || settings.debug_mode === "1" || settings.debug_mode === "true";
				if (window.swma_privacy.hasMarketingConsent()) {
					if (debug) {
						console.log( "SWMA: Marketing consent granted. Committing log to storage." );
					}
					this.swma_commitLogToStorage();
				} else {
					if (debug) {
						console.log( "SWMA: Marketing consent denied. Clearing attribution data." );
					}
					this.swma_clearStoredData();
					this.swma_clearVisibleAttributionFields();
				}
			} else {
				// Privacy engine not ready yet - do not clear data, just wait.
				settings = window.swma_settings || {};
				debug    = settings.debug_mode === true || settings.debug_mode === "1" || settings.debug_mode === "true";
				if (debug) {
					console.log( "SWMA: Privacy engine not yet initialized. Buffering in memory." );
				}
			}
		},

		/**
		 * Delete attribution data from localStorage.
		 */
		swma_clearStoredData: function () {
			localStorage.removeItem( 'swma_log' );
			// Also clear memory buffer if it exists.
			this._swma_memoryLog = [];
		},

			/**
			 * Clear all attribution hidden fields that might be on the page.
			 */
		swma_clearVisibleAttributionFields: function () {
			var attributionKeys = [
			'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
			'timestamp', 'url', 'title', 'all_detectable_attribution'
			];

			var self  = this;
			var forms = document.querySelectorAll( 'form' );
			for ( var f = 0, flen = forms.length; f < flen; f++ ) {
				var form = forms[ f ];
				if ( ! form || typeof form.querySelectorAll !== "function") {
					continue;
				}
				for ( var k = 0, klen = attributionKeys.length; k < klen; k++ ) {
					var key       = attributionKeys[ k ];
					var selectors = [
					'input[name="' + key + '"]',
					'textarea[name="' + key + '"]',
					'input[id="' + key + '"]',
					'textarea[id="' + key + '"]',
					'input.' + key,
					'.' + key + ' input'
					];
					var fields    = form.querySelectorAll( selectors.join( ',' ) );
					for ( var i = 0, len = fields.length; i < len; i++ ) {
						var field   = fields[ i ];
						field.value = '';
						if (typeof jQuery !== "undefined") {
							jQuery( field ).trigger( "change" );
						}
					}
				}
			}
		},

		swma_getAttributionData: function () {
			var log = this.swma_getAttributionLog();
			if (log.length === 0) {
				return {};
			}
			return log[0];
		},

		swma_populateFormFields: function (form) {
			if ( ! form) {
				return;
			}
			if (typeof jQuery !== "undefined" && form instanceof jQuery) {
				form = form[0];
			}
			if ( ! form || typeof form.querySelectorAll !== "function") {
				return;
			}

			var log = this.swma_getAttributionLog();

			if (log.length === 0) {
				return;
			}

			var firstEntry = log[0];

			// Populate First-Touch UTM data and general fields.
			for (var key in firstEntry) {
				if (Object.prototype.hasOwnProperty.call( firstEntry, key )) {
					this.swma_populateField( key, firstEntry[key], form );
				}
			}

			// Explicit touchpoint URLs.
			this.swma_populateField( 'first_touch_url', firstEntry.url, form );
			this.swma_populateField( 'last_touch_url', window.location.href, form );

			// Populate FULL log in the special field.
			this.swma_populateField( 'all_detectable_attribution', JSON.stringify( log ), form );
		},

		swma_findFieldByTextProximity: function (form, searchTerm) {

			var walker = document.createTreeWalker( form, NodeFilter.SHOW_TEXT, null, false );

			var node;
			while ((node = walker.nextNode())) {
				var text = node.textContent.toLowerCase();
				if (text.indexOf( searchTerm.toLowerCase() ) !== -1) {
					// Once we find the text, look ahead at the next elements in the DOM order.
					var allElements = Array.from( form.querySelectorAll( 'input, textarea' ) );
					for ( var i = 0, len = allElements.length; i < len; i++ ) {
						// If this element appears after our text node in the document.
						if (node.compareDocumentPosition( allElements[i] ) & Node.DOCUMENT_POSITION_FOLLOWING) {
							return allElements[i];
						}
					}
				}
			}
			return null;
		},

		swma_populateField: function (key, value, form) {
			var settings   = window.swma_settings || {};
			var debug      = settings.debug_mode === true || settings.debug_mode === "1" || settings.debug_mode === "true";
			var variations = [key];
			var i, len, field;

			// Add common CRM variations.
			if (key.indexOf( 'utm_' ) === 0 || key === 'all_detectable_attribution' || key === 'first_touch_url' || key === 'last_touch_url') {
				variations.push( 'swma_' + key );
				variations.push( key + '__c' );
				variations.push( 'swma_' + key + '__c' );
			}

			var selectors = [];
			variations.forEach(
				function (v) {
					selectors.push( 'input[name="' + v + '"]' );
					selectors.push( 'textarea[name="' + v + '"]' );
					selectors.push( 'input[id="' + v + '"]' );
					selectors.push( 'textarea[id="' + v + '"]' );
					selectors.push( 'input.' + v );
					selectors.push( 'textarea.' + v );
					selectors.push( '.' + v + ' input' );
					selectors.push( '.' + v + ' textarea' );
					// Gravity Forms specific: check parent containers for the class.
					selectors.push( '.gfield.' + v + ' input' );
					selectors.push( '.gfield.' + v + ' textarea' );
				}
			);

			var fields = form.querySelectorAll( selectors.join( ',' ) );

			// Smart Label Matching for Salesforce (if no direct field match found).
			if (fields.length === 0 && swma_isSalesforceForm( form )) {
				var labels        = form.querySelectorAll( 'label' );
				var targetFieldId = null;

				var searchTerms = [key, key.replace( /_/g, ' ' )];
				if (key.indexOf( 'utm_' ) === 0) {
					searchTerms.push( key.replace( 'utm_', 'utm ' ) );
					searchTerms.push( key.replace( 'utm_', '' ) );
				}
				if (key === 'all_detectable_attribution') {
					searchTerms.push( 'attribution log' );
					searchTerms.push( 'full attribution' );
				}
				if (key === 'first_touch_url' || key === 'swma_first_touch_url') {
					searchTerms.push( 'first touch' );
				}
				if (key === 'last_touch_url' || key === 'swma_last_touch_url') {
					searchTerms.push( 'last touch' );
				}
				if (key === 'swma_first_touch_url') {
					searchTerms.push( 'first touch url' );
				}
				if (key === 'swma_last_touch_url') {
					searchTerms.push( 'last touch url' );
				}

				for ( i = 0, len = labels.length; i < len; i++ ) {
					var labelText = labels[i].innerText.toLowerCase();
					var matched   = searchTerms.some(
						function (term) {
							return labelText.indexOf( term ) !== -1;
						}
					);

					if (matched) {
							targetFieldId = labels[i].getAttribute( 'for' );
						if (debug) {
							console.log( "SWMA: Found Salesforce field via label matching:", labelText, "->", targetFieldId );
						}
						break;
					}
				}

				if (targetFieldId) {
					field = form.querySelector( '#' + targetFieldId ) || form.querySelector( '[name="' + targetFieldId + '"]' );
					if (field) {
						fields = [field];
					}
				}

				// 2. Try Text Proximity (for raw text labels).
				if (fields.length === 0) {
					for ( var k = 0, klen = searchTerms.length; k < klen; k++ ) {
						var proximalField = this.swma_findFieldByTextProximity( form, searchTerms[k] );
						if (proximalField) {
							if (debug) {
								console.log( "SWMA: Found Salesforce field via text proximity:", searchTerms[k], "->", proximalField.name || proximalField.id );
							}
							fields = [proximalField];
							break;
						}
					}
				}
			}

			if (debug && fields.length > 0) {
				console.log( "SWMA: Populating", fields.length, "field(s) for key:", key, "with value:", value );
			}

			for ( i = 0, len = fields.length; i < len; i++ ) {
				field = fields[ i ];
				if (field.value !== value) {
					field.value = value;
					if (typeof jQuery !== "undefined") {
						jQuery( field ).trigger( "change" );
					}
				}
			}
		},

		swma_hubspot_form_submit: function (form) {
			if ( ! form) {
				return;
			}
			if (form.jquery) {
				form = form[0];
			}

			var self            = this;
			var attributionData = this.swma_getAttributionData();
			var formId          = form.getAttribute( "data-form-id" );
			var portalId        = form.getAttribute( "data-portal-id" );

			var finalMessage     = (typeof swma_ajax !== 'undefined' && swma_ajax.thank_you_message) || "Thanks for submitting the form.";
			var finalRedirectUrl = (typeof swma_ajax !== 'undefined' && swma_ajax.thank_you_url) || "";

			if (formId && typeof swma_ajax !== 'undefined' && swma_ajax.form_overrides && Array.isArray( swma_ajax.form_overrides )) {
				for ( var i = 0, len = swma_ajax.form_overrides.length; i < len; i++ ) {
					var override = swma_ajax.form_overrides[i];
					if (override.form_guid === formId) {
						if (override.thank_you_message) {
							finalMessage = override.thank_you_message;
						}
						if (override.redirect_url) {
							finalRedirectUrl = override.redirect_url;
						}
						break;
					}
				}
			}

			var formData = new URLSearchParams();
			for ( var j = 0, elen = form.elements.length; j < elen; j++ ) {
				var field = form.elements[j];
				if (field.name) {
					formData.append( field.name, field.value );
				}
			}

			var fullLog = this.swma_getAttributionLog();
			for (var key in attributionData) {
				if (Object.prototype.hasOwnProperty.call( attributionData, key )) {
					formData.append( key, attributionData[key] );
				}
			}
			formData.append( "all_detectable_attribution", JSON.stringify( fullLog ) );

			if (typeof swma_ajax === "undefined" || ! swma_ajax.ajax_url || ! swma_ajax.nonce) {
				return false;
			}

			fetch(
				swma_ajax.ajax_url,
				{
					method: "POST",
					headers: { "Content-Type": "application/x-www-form-urlencoded" },
					body: new URLSearchParams(
						{
							action: "swma_hubspot_proxy",
							security: swma_ajax.nonce,
							portal_id: portalId,
							form_guid: formId,
							fields: formData.toString(),
							context: JSON.stringify(
								{
									pageUri: window.location.href,
									pageName: document.title,
									}
							),
						}
					),
				}
			)
			.then(
				function (response) {
					return response.json(); }
			)
			.then(
				function (data) {
					if (data.success) {
						var responseData = data.data;
						if (responseData.redirectUri) {
							window.location.href = responseData.redirectUri;
							return;
						} else if (finalRedirectUrl) {
							window.location.href = finalRedirectUrl;
							return;
						}

						var successMessage             = document.createElement( "div" );
						successMessage.className       = "swm-success-message";
						successMessage.innerHTML       = responseData.inlineMessage || finalMessage;
						successMessage.style.textAlign = "center";

						var formTarget = form.closest( ".hs-form-target" );
						if (formTarget) {
							formTarget.innerHTML = "";
							formTarget.appendChild( successMessage );
						} else if (form.parentNode) {
							form.style.display = "none";
							form.parentNode.appendChild( successMessage );
						}
					}
				}
			)
			.catch( function () { } );

			return false;
		},

		swma_addProactiveListeners: function () {
			var self   = this;
			var events = ['focusin', 'click', 'touchstart'];

			var handleInteraction = function (e) {
				var form = e.target.closest( 'form' );
				if (form) {
					self.swma_populateFormFields( form );
				}
			};

				events.forEach(
					function (event) {
						document.addEventListener( event, handleInteraction, true );
					}
				);
		},

		swma_setupAjaxInterceptor: function () {
			var self = this;

			// Helper to avoid double-logging.
			var lastLogTime = 0;
			var logDebounce = 1000;

			var triggerConversion = function (payload, url) {
				var now = Date.now();
				if (now - lastLogTime < logDebounce) {
					return;
				}

				// Skip CF7 endpoints since we have a specific hook.
				if (url && (url.indexOf( 'contact-form-7' ) !== -1 || url.indexOf( 'wp-json/contact-form-7' ) !== -1)) {
					return;
				}

				// Basic heuristic: Is it a form submission?
				var isForm = false;
				if (typeof payload === 'string') {
					// EXCLUDE our own internal actions.
					if (payload.indexOf( 'action=swma_log_conversion' ) !== -1 || payload.indexOf( 'action=swma_hubspot_proxy' ) !== -1) {
						return;
					}
					isForm = /utm_source|email|name|form_id|action/i.test( payload );
				} else if (payload instanceof FormData) {
					// EXCLUDE our own internal actions in FormData.
					if (payload.get( 'action' ) === 'swma_log_conversion' || payload.get( 'action' ) === 'swma_hubspot_proxy') {
						return;
					}
					// Assume FormData is likely a form.
					isForm = true;
				}

				if (isForm) {
					lastLogTime = now;
					self.swma_logConversion( "ajax_submission" );
				}
			};

			// 1. Intercept XMLHttpRequest via Prototype.
			var oldXHROpen                           = window.XMLHttpRequest.prototype.open;
				window.XMLHttpRequest.prototype.open = function (method, url) {
					this._swma_url = url;
					return oldXHROpen.apply( this, arguments );
				};

				var oldXHRSend                       = window.XMLHttpRequest.prototype.send;
				window.XMLHttpRequest.prototype.send = function (data) {
					triggerConversion( data, this._swma_url );
					return oldXHRSend.apply( this, arguments );
				};

				// 2. Intercept Fetch.
			if (window.fetch) {
				var oldFetch = window.fetch;
				window.fetch = function () {
					var args        = arguments;
					var requestData = null;
					var url         = '';

					if (typeof args[0] === 'string') {
						url = args[0];
					} else if (args[0] && typeof args[0] === 'object' && args[0].url) {
						url = args[0].url;
					}

					if (args[1] && args[1].body) {
						requestData = args[1].body;
					} else if (args[0] && typeof args[0] === 'object' && args[0].body) {
						requestData = args[0].body;
					}

					if (requestData) {
						triggerConversion( requestData, url );
					}
					return oldFetch.apply( this, args );
				};
			}
		},

		swma_handleStandardForms: function () {
			var self     = this;
			var settings = window.swma_settings || {};
			var debug    = settings.debug_mode === true || settings.debug_mode === "1" || settings.debug_mode === "true";

			var forms = document.querySelectorAll( "form" );
			if (debug) {
				console.log( "SWMA: Found", forms.length, "form(s) on page." );
			}

			for ( var i = 0, len = forms.length; i < len; i++ ) {
				var form      = forms[ i ];
				var isSpecial = swma_isHubSpotForm( form ) || swma_isWpForm( form ) || swma_isNinjaForm( form ) || swma_isGravityForm( form );
				if (debug && swma_isSalesforceForm( form )) {
					console.log( "SWMA: Detected Salesforce Web-to-Lead form." );
				}

				if ( ! isSpecial) {
					self.swma_populateFormFields( form );
				}
			}
		},

		swma_handleHubSpotForms: function () {
			var self       = this;
			var hsInterval = setInterval(
				function () {
					var hsForms = document.querySelectorAll( ".hs-form, .hs-form-private" );
					if (hsForms.length > 0) {
						for ( var i = 0, len = hsForms.length; i < len; i++ ) {
							var form = hsForms[i];
							if ( ! form._swma_populated) {
									self.swma_populateFormFields( form );
									form._swma_populated = true;
							}
						}
					}
				},
				2000
			);

			setTimeout(
				function () {
					clearInterval( hsInterval );
				},
				10000
			);

			window.addEventListener(
				"message",
				function (event) {
					if (
					event.data.type === "hsFormCallback" &&
					event.data.eventName === "onFormReady"
					) {
						var formId = event.data.id;
						var hsForm = document.querySelector( '[data-form-id="' + formId + '"]' );
						if (hsForm) {
								self.swma_populateFormFields( hsForm );
						}
					}
				}
			);
		},

		swma_handleWpForms: function () {
			var self = this;
			if (typeof jQuery !== "undefined") {
				jQuery( document ).on(
					"wpformsReady",
					function () {
						jQuery( ".wpforms-form" ).each(
							function () {
								self.swma_populateFormFields( this );
							}
						);
					}
				);
			}
			setTimeout(
				function () {
					var wpForms = document.querySelectorAll( ".wpforms-form" );
					wpForms.forEach(
						function (form) {
							self.swma_populateFormFields( form );
						}
					);
				},
				2000
			);
		},

		swma_handleNinjaForms: function () {
			var self = this;
			if (typeof jQuery !== "undefined") {
				jQuery( document ).on(
					"nfFormReady",
					function (e, layoutView) {
						self.swma_populateFormFields( layoutView.el );
					}
				);
			}
			setTimeout(
				function () {
					var nfForms = document.querySelectorAll( ".nf-form-content" );
					nfForms.forEach(
						function (form) {
							self.swma_populateFormFields( form );
						}
					);
				},
				2000
			);
		},

		swma_handleGravityForms: function () {
			var self = this;

			// Proactive check for Gravity Forms confirmation message on page load.
			if ( document.querySelector( '.gform_confirmation_message' ) || document.querySelector( '.gform_confirmation_wrapper' ) ) {
				this.swma_logConversion( "gravity_form_success" );
			}

			var setupGForm = function ( formId ) {
				var form = document.getElementById( "gform_" + formId );
				if (form) {
					// 1. Proactive population.
					self.swma_populateFormFields( form );

					// 2. Aggressive re-population on change (MutationObserver).
					if ( ! form._swma_observer_registered && typeof MutationObserver !== 'undefined' ) {
						form._swma_observer_registered = true;
						var observer                   = new MutationObserver(
							function () {
								self.swma_populateFormFields( form );
							}
						);
						observer.observe( form, { childList: true, subtree: true } );
					}
				}
			};

			if (typeof jQuery !== "undefined") {
				// 1. Hook into Gravity Forms render event.
				jQuery( document ).on(
					"gform_post_render",
					function ( event, formId ) {
						setupGForm( formId );
					}
				);

				// 2. Success-only tracking on confirmation.
				jQuery( document ).on(
					"gform_confirmation_loaded",
					function ( event, formId ) {
						self.swma_logConversion( "gravity_form_success" );
					}
				);
			}
			setTimeout(
				function () {
					var gForms = document.querySelectorAll( ".gform_wrapper form" );
					gForms.forEach(
						function (form) {
							var idMatch = form.id.match( /gform_(\d+)/ );
							if (idMatch) {
								setupGForm( idMatch[1] );
							} else {
								self.swma_populateFormFields( form );
							}
						}
					);
				},
				2000
			);
		},

		swma_handleCf7: function () {
			var self = this;
			document.addEventListener(
				'wpcf7mailsent',
				function () {
					self.swma_logConversion( "cf7_submission" );
				},
				false
			);
		},

		swma_trackFormSubmissions: function () {
			var self                       = this;
			var populateAllNonHubSpotForms = function () {
				var allForms = document.querySelectorAll( "form" );
				for ( var i = 0, len = allForms.length; i < len; i++ ) {
					var form = allForms[i];
					if (
					! swma_isHubSpotForm( form ) &&
					! swma_isWpForm( form ) &&
					! swma_isNinjaForm( form ) &&
					! swma_isGravityForm( form )
					) {
						self.swma_populateFormFields( form );
					}
				}
			};

				populateAllNonHubSpotForms();

				var allForms = document.querySelectorAll( "form" );
			for ( var i = 0, len = allForms.length; i < len; i++ ) {
				var form = allForms[i];
				if (
				! swma_isHubSpotForm( form ) &&
				! swma_isWpForm( form ) &&
				! swma_isNinjaForm( form ) &&
				! swma_isGravityForm( form ) &&
				! form._swma_attribution_submit_listener_registered
				) {
					form._swma_attribution_submit_listener_registered = true;
					form.addEventListener(
						"submit",
						function () {
							self.swma_populateFormFields( form );
						}
					);
				}
			}
		},

		swma_trackPhoneClicks: function () {
			document.addEventListener(
				"click",
				function (e) {
					var link = e.target.closest( 'a[href^="tel:"]' );
					if (link) {
						this.swma_logConversion( "phone_click" );
					}
				}.bind( this ),
			);
		},

		swma_logFormConversions: function () {
			document.addEventListener(
				"submit",
				function (e) {
					var form = e.target;
					if (
					(typeof swma_isHubSpotForm === "function" && swma_isHubSpotForm( form )) ||
					(typeof swma_isCf7Form === "function" && swma_isCf7Form( form ))
					) {
						return;
					}
					this.swma_logConversion( "form_submission" );
				}.bind( this ),
			);
		},

		swma_logConversion: function (eventType) {
			if (typeof swma_ajax === "undefined") {
				return;
			}

			// Deduplication: Don't log same-type events too rapidly.
			var now = Date.now();
			if ( ! this._lastLogs) {
				this._lastLogs = {};
			}
			if (this._lastLogs[eventType] && (now - this._lastLogs[eventType] < 5000)) {
				return;
			}
			this._lastLogs[eventType] = now;

			var attribution = this.swma_getAttributionData();

			var payload = new FormData();
			payload.append( "action", "swma_log_conversion" );
			payload.append( "security", swma_ajax.nonce );
			payload.append( "event_type", eventType );
			payload.append( "page_url", window.location.href );

			if (attribution.utm_source) {
				payload.append( "utm_source", attribution.utm_source );
			}
			if (attribution.utm_medium) {
				payload.append( "utm_medium", attribution.utm_medium );
			}
			if (attribution.utm_campaign) {
				payload.append( "utm_campaign", attribution.utm_campaign );
			}
			if (attribution.utm_term) {
				payload.append( "utm_term", attribution.utm_term );
			}
			if (attribution.utm_content) {
				payload.append( "utm_content", attribution.utm_content );
			}

			// Prefer sendBeacon for unloads/redirects.
			if (navigator.sendBeacon) {
				var success = navigator.sendBeacon( swma_ajax.ajax_url, payload );
				if (success) {
					return;
				}
			}

			if (window.fetch) {
				fetch(
					swma_ajax.ajax_url,
					{
						method: "POST",
						body: payload,
						keepalive: true,
						}
				)
				.catch(
					function () { }
				);
			}
		},

		swma_init: function () {
			var self = this;

			// 1. Capture current visit data into memory buffer.
			this.swma_logAttributionVisit();

			// 2. Initial enforcement (checks if consent already exists).
			this.swma_enforcePrivacy();

			// 3. Listen for future consent updates (e.g. user clicks "Accept" on a CMP).
			document.addEventListener(
				'swma_consent_updated',
				function () {
					self.swma_enforcePrivacy();
					if (document.readyState === "complete" || document.readyState === "interactive") {
						self.swma_handleStandardForms();
					}
				}
			);

			this.swma_handleHubSpotForms();
			this.swma_handleWpForms();
			this.swma_handleNinjaForms();
			this.swma_handleGravityForms();
			this.swma_handleCf7();
			this.swma_addProactiveListeners();
			this.swma_setupAjaxInterceptor();

			var runOnReady = function () {
				self.swma_handleStandardForms();
				self.swma_trackPhoneClicks();
				self.swma_logFormConversions();
				self.swma_trackFormSubmissions();
			};

			if (
				document.readyState === "complete" ||
				document.readyState === "interactive"
			) {
				runOnReady();
			} else {
				document.addEventListener( "DOMContentLoaded", runOnReady );
			}
		},
	};

	function swma_isHubSpotForm(form) {
		if (
		form.classList.contains( "hs-form" ) ||
		form.classList.contains( "hs-form-private" ) ||
		Array.from( form.classList ).some(
			(cls) => cls.startsWith( "hsForm_" ) || cls.startsWith( "hs-form-" ),
		)
		) {
			return true;
		}
		if (
		form.hasAttribute( "data-form-id" ) &&
		form.hasAttribute( "data-portal-id" )
		) {
			return true;
		}
		if (form.action && form.action.includes( "hsforms.com" )) {
			return true;
		}
		return false;
	}

	function swma_isSalesforceForm(form) {
		return form.action && (form.action.includes( "salesforce.com/servlet/servlet.WebToLead" ) || form.action.includes( "force.com/servlet/servlet.WebToLead" ));
	}

	function swma_isWpForm(form) {
		return form.classList.contains( "wpforms-form" );
	}

	function swma_isCf7Form(form) {
		return form.classList.contains( "wpcf7-form" ) || form.closest( ".wpcf7" ) !== null;
	}

	function swma_isNinjaForm(form) {
		return form.closest( ".nf-form-cont" ) !== null;
	}

	function swma_isGravityForm(form) {
		return (form.id && form.id.indexOf( "gform_" ) === 0) || form.classList.contains( "gform_wrapper" ) || form.closest( ".gform_wrapper" ) !== null;
	}

		global.swma_capture             = AttributionCore;
		global.swma_hubspot_form_submit = AttributionCore.swma_hubspot_form_submit.bind( AttributionCore );
		global.swm_hubspot_form_submit  = global.swma_hubspot_form_submit;

		AttributionCore.swma_init();
})( window );