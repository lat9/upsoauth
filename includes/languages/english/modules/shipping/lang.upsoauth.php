<?php
// -----
// Language constants used by the upsoauth.php shipping method.
//
// Copyright 2023-2024, Vinos de Frutas Tropicales
//
// Last updated: v1.3.6 (created)
//
$define = [
    'MODULE_SHIPPING_UPSOAUTH_TEXT_TITLE' => 'United Parcel Service',
    'MODULE_SHIPPING_UPSOAUTH_TEXT_DESCRIPTION' => 'United Parcel Service',

    // -----
    // Admin messages.
    //
    'MODULE_SHIPPING_UPSOAUTH_NEED_CREDENTIALS' => 'This module cannot be enabled until you supply both the <em>Client ID</em> and <em>Client Secret</em>.',
    'MODULE_SHIPPING_UPSOAUTH_NEED_POSTCODE' => 'The <em>Origin Zip/Postcode</em> is required when your shipping &quot;Origin&quot; is US, Canada, Mexico or Puerto Rico; this module has been disabled.',
    'MODULE_SHIPPING_UPSOAUTH_UPDATED' => 'The UPS RESTful/OAuth shipping module was automatically updated to v%s.',

    // -----
    // Email subject and message when the OAuth token retrieval fails or if the UPS Api class configured doesn't exist.
    //
    'MODULE_SHIPPING_UPSOAUTH_EMAIL_SUBJECT' => 'The \'upsoauth\' shipping method has been automatically disabled',
    'MODULE_SHIPPING_UPSOAUTH_INVALID_CREDENTIALS' => 'The \'Client ID\' and \'Client Secret\' you supplied are not recognized by UPS; the \'upsoauth\' shipping module has been automatically disabled.',
    'MODULE_SHIPPING_UPSOAUTH_MISSING_API_CLASS' => 'The \'UPS Api Class\' you supplied (%s) does not exist; the \'upsoauth\' shipping module has been automatically disabled.',

    // -----
    // These constant definitions are used by the upsoauth.php shipping-module to assign human-readable
    // values to the service codes provided by UPS, based on the shipping origin.
    //
    // These values were last verified with the "UPS Rating Package RESTful Developer Guide" dated 2023-02-17.
    //
    'MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_01' => 'UPS Next Day Air',
    'MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_02' => 'UPS 2nd Day Air',
    'MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_03' => 'UPS Ground',
    'MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_07' => 'UPS Worldwide Express',
    'MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_08' => 'UPS Worldwide Expedited',
    'MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_11' => 'UPS Standard',
    'MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_12' => 'UPS 3 Day Select',
    'MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_13' => 'UPS Next Day Air Saver',
    'MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_14' => 'UPS Next Day Air Early',
    'MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_54' => 'UPS Worldwide Express Plus',
    'MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_59' => 'UPS 2nd Day Air A.M.',
    'MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_65' => 'UPS Worldwide Saver',
    'MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_75' => 'UPS Heavy Goods',    //- new to OAuth

    'MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_01' => 'UPS Express',
    'MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_02' => 'UPS Expedited',
    'MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_07' => 'UPS Worldwide Express',
    'MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_08' => 'UPS Worldwide Expedited',
    'MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_11' => 'UPS Standard',
    'MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_12' => 'UPS 3 Day Select',
    'MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_13' => 'UPS Express Saver',
    'MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_14' => 'UPS Express Early',
    'MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_54' => 'UPS Worldwide Express Plus',
    'MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_65' => 'UPS Express Saver',
    'MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_70' => 'UPS Access Point Economy',   //- new to OAuth

    'MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_07' => 'UPS Express',
    'MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_08' => 'UPS Expedited',
    'MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_11' => 'UPS Standard',
    'MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_54' => 'UPS Worldwide Express Plus',
    'MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_65' => 'UPS Worldwide Saver',
    'MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_70' => 'UPS Access Point Economy',   //- new to OAuth

    'MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_01' => 'UPS Next Day Air',
    'MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_02' => 'UPS 2nd Day Air',
    'MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_03' => 'UPS Ground',
    'MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_07' => 'UPS Worldwide Express',
    'MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_08' => 'UPS Worldwide Expedited',
    'MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_14' => 'UPS Next Day Air Early',
    'MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_54' => 'UPS Worldwide Express Plus',
    'MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_65' => 'UPS Worldwide Saver',

    'MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_07' => 'UPS Express',
    'MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_08' => 'UPS Expedited',
    'MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_11' => 'UPS Standard',
    'MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_54' => 'UPS Worldwide Express Plus',
    'MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_65' => 'UPS Worldwide Saver',

    'MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_07' => 'UPS Worldwide Express',
    'MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_08' => 'UPS Worldwide Expedited',
    'MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_11' => 'UPS Standard',
    'MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_54' => 'UPS Worldwide Express Plus',
    'MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_65' => 'UPS Worldwide Saver',

    'MODULE_SHIPPING_UPSOAUTH_ETA_TEXT' => ', ETA: %u Business Days',     //-Identifies the Estimated Time of Arrival, when transit-time is to be displayed.

    'MODULE_SHIPPING_UPSOAUTH_INVALID_CURRENCY_CODE' => 'Unknown currency code specified (%s), using store default (' . DEFAULT_CURRENCY . ').',

    //- %1$s = ENTRY_POST_CODE, %2$s = postcode, %3$s = state name, %4$s = country name
    'MODULE_SHIPPING_UPSOAUTH_INVALID_POSTCODE' => 'The %1$s (%2$s) is invalid for %3$s %4$s, please re-enter.',

    //- %1$s = ENTRY_POST_CODE, %2$s = state name, %3$s = country name
    'MODULE_SHIPPING_UPSOAUTH_POSTCODE_REQUIRED' => 'A %1$s is required for %2$s %3$s, please re-enter.',

    //- %1$s = state name, %2$s = country name
    'MODULE_SHIPPING_UPSOAUTH_INVALID_STATE' => '%1$s is not a valid state abbreviation for %2$s, please re-enter.',
    'MODULE_SHIPPING_UPSOAUTH_SERVICE_UNAVAILABLE' => 'No shipping is available to %1$s %2$s.',
        'MODULE_SHIPPING_UPSOAUTH_STATE_REQUIRED' => 'A %1$s is required for some countries.',    //- %1$s: ENTRY_STATE

    //- %1$s = country name, %2$s = ENTRY_COUNTRY
    'MODULE_SHIPPING_UPSOAUTH_INVALID_COUNTRY' => 'UPS does not ship to %1$s, please select a different %2$s.',

    'MODULE_SHIPPING_UPSOAUTH_ERROR' => 'UPS is currently unable to provide shipping quotes, error code [%s].',
];
return $define;
