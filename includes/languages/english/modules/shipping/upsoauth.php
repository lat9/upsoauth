<?php
// -----
// Language constants used by the upsoauth.php shipping method.
//
// Copyright 2023-2024, Vinos de Frutas Tropicales
//
// Last updated: v1.3.6
//
define('MODULE_SHIPPING_UPSOAUTH_TEXT_TITLE', 'United Parcel Service');
define('MODULE_SHIPPING_UPSOAUTH_TEXT_DESCRIPTION', 'United Parcel Service');

// -----
// Admin messages.
//
define('MODULE_SHIPPING_UPSOAUTH_NEED_CREDENTIALS', 'This module cannot be enabled until you supply both the <em>Client ID</em> and <em>Client Secret</em>.');
define('MODULE_SHIPPING_UPSOAUTH_NEED_POSTCODE', 'The <em>Origin Zip/Postcode</em> is required when your shipping &quot;Origin&quot; is US, Canada, Mexico or Puerto Rico; this module has been disabled.');
define('MODULE_SHIPPING_UPSOAUTH_UPDATED', 'The UPS RESTful/OAuth shipping module was automatically updated to v%s.');

// -----
// Email subject and message when the OAuth token retrieval fails or if the UPS Api class configured doesn't exist.
//
define('MODULE_SHIPPING_UPSOAUTH_EMAIL_SUBJECT', 'The \'upsoauth\' shipping method has been automatically disabled');
define('MODULE_SHIPPING_UPSOAUTH_INVALID_CREDENTIALS', 'The \'Client ID\' and \'Client Secret\' you supplied are not recognized by UPS; the \'upsoauth\' shipping module has been automatically disabled.');
define('MODULE_SHIPPING_UPSOAUTH_MISSING_API_CLASS', 'The \'UPS Api Class\' you supplied (%s) does not exist; the \'upsoauth\' shipping module has been automatically disabled.');

// -----
// These constant definitions are used by the upsoauth.php shipping-module to assign human-readable
// values to the service codes provided by UPS, based on the shipping origin.
//
// These values were last verified with the "UPS Rating Package RESTful Developer Guide" dated 2023-02-17.
//
define('MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_01', 'UPS Next Day Air');
define('MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_02', 'UPS 2nd Day Air');
define('MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_03', 'UPS Ground');
define('MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_07', 'UPS Worldwide Express');
define('MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_08', 'UPS Worldwide Expedited');
define('MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_11', 'UPS Standard');
define('MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_12', 'UPS 3 Day Select');
define('MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_13', 'UPS Next Day Air Saver');
define('MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_14', 'UPS Next Day Air Early');
define('MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_54', 'UPS Worldwide Express Plus');
define('MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_59', 'UPS 2nd Day Air A.M.');
define('MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_65', 'UPS Worldwide Saver');
define('MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_75', 'UPS Heavy Goods');    //- new to OAuth

define('MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_01', 'UPS Express');
define('MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_02', 'UPS Expedited');
define('MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_07', 'UPS Worldwide Express');
define('MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_08', 'UPS Worldwide Expedited');
define('MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_11', 'UPS Standard');
define('MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_12', 'UPS 3 Day Select');
define('MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_13', 'UPS Express Saver');
define('MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_14', 'UPS Express Early');
define('MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_54', 'UPS Worldwide Express Plus');
define('MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_65', 'UPS Express Saver');
define('MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_70', 'UPS Access Point Economy');   //- new to OAuth

define('MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_07', 'UPS Express');
define('MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_08', 'UPS Expedited');
define('MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_11', 'UPS Standard');
define('MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_54', 'UPS Worldwide Express Plus');
define('MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_65', 'UPS Worldwide Saver');
define('MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_70', 'UPS Access Point Economy');   //- new to OAuth

define('MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_01', 'UPS Next Day Air');
define('MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_02', 'UPS 2nd Day Air');
define('MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_03', 'UPS Ground');
define('MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_07', 'UPS Worldwide Express');
define('MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_08', 'UPS Worldwide Expedited');
define('MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_14', 'UPS Next Day Air Early');
define('MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_54', 'UPS Worldwide Express Plus');
define('MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_65', 'UPS Worldwide Saver');

define('MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_07', 'UPS Express');
define('MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_08', 'UPS Expedited');
define('MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_11', 'UPS Standard');
define('MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_54', 'UPS Worldwide Express Plus');
define('MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_65', 'UPS Worldwide Saver');

define('MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_07', 'UPS Worldwide Express');
define('MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_08', 'UPS Worldwide Expedited');
define('MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_11', 'UPS Standard');
define('MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_54', 'UPS Worldwide Express Plus');
define('MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_65', 'UPS Worldwide Saver');

define('MODULE_SHIPPING_UPSOAUTH_ETA_TEXT', ', ETA: %u Business Days');     //-Identifies the Estimated Time of Arrival, when transit-time is to be displayed.

define('MODULE_SHIPPING_UPSOAUTH_INVALID_CURRENCY_CODE', 'Unknown currency code specified (%s), using store default (' . DEFAULT_CURRENCY . ').');

define('MODULE_SHIPPING_UPSOAUTH_INVALID_POSTCODE', 'The <b>Post/Zip Code</b> (%1$s) is invalid for %2$s %3$s, please re-enter.');  //- %2$s = state, %3$s = country
define('MODULE_SHIPPING_UPSOAUTH_INVALID_STATE', '%1$s is not a valid state abbreviation for %%2$s, please re-enter.');  //- %1$s = state, %2$s = country
define('MODULE_SHIPPING_UPSOAUTH_ERROR', 'UPS is currently unable to provide shipping quotes, error code [%s].');
