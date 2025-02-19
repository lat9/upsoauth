<?php
// -----
// Processing file for the Zen Cart implementation of the UPS shipping module which uses the
// UPS RESTful API with OAuth authentication.
//
// Copyright 2023-2025, Vinos de Frutas Tropicales
//
// Last updated: v1.3.7
//
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

class upsoauth extends base
{
    // -----
    // Zen Cart "Plugin ID", used for version-update checks.
    //
    const ZEN_CART_PLUGIN_ID = 2374;

    public
        $code,
        $title,
        $description,
        $icon,
        $enabled,
        $sort_order,
        $quotes,
        $tax_class;

    protected
        $moduleVersion = '1.3.7',
        $upsApi,

        $_check,

        $debug,
        $logfile;

    public function __construct()
    {
        $this->code = 'upsoauth';
        $this->title = MODULE_SHIPPING_UPSOAUTH_TEXT_TITLE;
        if (IS_ADMIN_FLAG === true) {
            $this->title .= ' v' . $this->moduleVersion;
        }
        $this->description = MODULE_SHIPPING_UPSOAUTH_TEXT_DESCRIPTION;

        $this->sort_order = (defined('MODULE_SHIPPING_UPSOAUTH_SORT_ORDER')) ? (int)MODULE_SHIPPING_UPSOAUTH_SORT_ORDER : null;
        if ($this->sort_order === null) {
            return false;
        }

        $this->enabled = (MODULE_SHIPPING_UPSOAUTH_STATUS === 'True');
        $this->debug = (MODULE_SHIPPING_UPSOAUTH_DEBUG === 'true');
        $this->logfile = DIR_FS_LOGS . '/upsoauth-' . date('Ymd-His') . '.log';
        $this->tax_class = (int)MODULE_SHIPPING_UPSOAUTH_TAX_CLASS;

        if (IS_ADMIN_FLAG === true) {
            $this->adminInitializationChecks();
        } elseif ($this->enabled === true) {
            $this->storefrontInitialization();
        }
    }

    protected function adminInitializationChecks($is_installation = false)
    {
        global $db, $current_page, $messageStack;

        if ($current_page !== 'modules.php') {
            return;
        }

        // -----
        // If the store's version is different than the current version or if the number of configuration 'keys'
        // has changed, check first to see if automatic updates can be performed; if so do them!  Otherwise,
        // the site's admin will need to save the current settings and uninstall/reinstall the module.
        //
        $chk_sql = $db->Execute(
            'SELECT configuration_key
               FROM ' . TABLE_CONFIGURATION . "
              WHERE configuration_key like 'MODULE\_SHIPPING\_UPSOAUTH\_%'"
        );
        if (!defined('MODULE_SHIPPING_UPSOAUTH_VERSION') || MODULE_SHIPPING_UPSOAUTH_VERSION !== $this->moduleVersion || count($this->keys()) !== $chk_sql->RecordCount()) {
            if (!defined('MODULE_SHIPPING_UPSOAUTH_VERSION')) {
                define('MODULE_SHIPPING_UPSOAUTH_VERSION', '1.0.0');
            }
            switch (true) {
                case version_compare(MODULE_SHIPPING_UPSOAUTH_VERSION, '1.3.1', '<='):
                    $db->Execute(
                        'INSERT IGNORE INTO ' . TABLE_CONFIGURATION . "
                            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added)
                         VALUES
                            ('UPS OAuth Version', 'MODULE_SHIPPING_UPSOAUTH_VERSION', '" . $this->moduleVersion . "', 'You have installed:', 6, 0, NULL, 'zen_cfg_select_option([\'" . $this->moduleVersion . "\'], ', now()),

                            ('UPS Api Class', 'MODULE_SHIPPING_UPSOAUTH_API_CLASS', 'UpsOAuthApi', 'If your site has an class-override for the shipping module\'s default (<var>UpsOAuthApi</var>), enter it here.  If the class-file doesn\'t exist, this module will be automatically disabled!', 6, 2, NULL, NULL, now())"
                    );
                    $db->Execute(
                        'INSERT IGNORE INTO ' . TABLE_CONFIGURATION . "
                            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added)
                         VALUES
                            ('Fixed Handling Fee, Order or Box?', 'MODULE_SHIPPING_UPSOAUTH_HANDLING_APPLIES', 'Order', 'If the handling fee is a <em>fixed amount</em>, should it be applied once per order (the default) or for every box?', 6, 0, NULL, 'zen_cfg_select_option([\'Order\', \'Box\'], ', now())"
                    );

                    $ups_service_code_to_name = [
                        '01' => 'Next Day Air',
                        '02' => '2nd Day Air',
                        '03' => 'Ground',
                        '07' => 'Worldwide Express',
                        '08' => 'Worldwide Expedited',
                        '11' => 'Standard',
                        '12' => '3 Day Select',
                        '13' => 'Next Day Air Saver',
                        '14' => 'Next Day Air Early',
                        '54' => 'Worldwide Express Plus',
                        '59' => '2nd Day Air A.M.',
                        '65' => 'Express Saver',
                    ];
                    $default_fee = (defined('MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE')) ? MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE : '0';
                    foreach ($ups_service_code_to_name as $service_code => $service_name) {
                        $db->Execute(
                            'INSERT IGNORE INTO ' . TABLE_CONFIGURATION . "
                                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added)
                             VALUES
                                ('Handling Fee: $service_name', 'MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE_$service_code', '$default_fee', 'Handling fee for $service_name shipments.  The value you enter is either a fixed value or a percentage, e.g. 10%, of the UPS quote\'s value.', 6, 16, NULL, NULL, now())"
                        );
                    }
                    $db->Execute(
                        "DELETE FROM " . TABLE_CONFIGURATION . "
                          WHERE configuration_key = 'MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE'
                          LIMIT 1"
                    );
                    break;

                // -----
                // Otherwise, if no configuration keys were added or removed, the module just
                // auto-updates its version.
                //
                case (count($this->keys()) === $chk_sql->RecordCount()):
                    break;                  //- END OF AUTOMATIC UPDATE CHECKS!

                default:
                    $this->title .= '<span class="alert">' . ' - Missing Keys or Out of date you should reinstall!' . '</span>';
                    break;
            }

            $db->Execute(
                'UPDATE ' . TABLE_CONFIGURATION . "
                    SET configuration_value = '" . $this->moduleVersion. "',
                        set_function = 'zen_cfg_select_option([\'" . $this->moduleVersion . "\'],'
                  WHERE configuration_key = 'MODULE_SHIPPING_UPSOAUTH_VERSION'
                  LIMIT 1"
            );

            // -----
            // If this is an initial installation, the follow-on checks are neither needed nor wanted ...
            // ... and will result in a PHP Warning/Error due to those missing constants!
            //
            if ($is_installation === true) {
                return;
            }

            $messageStack->add(sprintf(MODULE_SHIPPING_UPSOAUTH_UPDATED, $this->moduleVersion), 'success');
        }

        // -----
        // Perform some 'sanity checks' on the configuration settings, resetting the shipping-module's enable setting
        // if the current settings will result in multiple, useless requests to UPS.
        //
        // 1. Both the Client ID and Client Secret must be supplied.
        // 2. The "Origin Zip/Postcode" is required when the "Origin" is  US, Canada, Mexico or Puerto Rico.
        // 3. The "UPS Api Class" file must exist in /includes/modules/shipping/upsoauth.
        //
        $configuration_error_message = '';
        if (MODULE_SHIPPING_UPSOAUTH_CLIENT_ID === '' || MODULE_SHIPPING_UPSOAUTH_CLIENT_SECRET === '') {
            $configuration_error_message = MODULE_SHIPPING_UPSOAUTH_NEED_CREDENTIALS;
        } elseif (MODULE_SHIPPING_UPSOAUTH_ORIGIN_POSTALCODE === '' && !in_array(MODULE_SHIPPING_UPSOAUTH_ORIGIN, ['European Union Origin', 'All other origins'])) {
            $configuration_error_message = MODULE_SHIPPING_UPSOAUTH_NEED_POSTCODE;
        } elseif ($this->upsOAuthApiExists() === false) {
            $configuration_error_message = sprintf(MODULE_SHIPPING_UPSOAUTH_MISSING_API_CLASS, MODULE_SHIPPING_UPSOAUTH_API_CLASS);
        }
        if ($configuration_error_message !== '') {
            $db->Execute(
                'UPDATE ' . TABLE_CONFIGURATION . '
                    SET configuration_value = \'False\'
                  WHERE configuration_key = \'MODULE_SHIPPING_UPSOAUTH_STATUS\'
                  LIMIT 1'
            );
            $this->title .= '<br><span class="alert">' . $configuration_error_message . '</span>';
            return;
        }

        // -----
        // If the shipping module's "Plugin ID" is set and the site has requested that a version-check update be
        // performed (either always or one time), check to see if a new version of the plugin is available.
        //
        if (self::ZEN_CART_PLUGIN_ID !== 0 && (MODULE_SHIPPING_UPSOAUTH_UPDATE_CHECK === 'Always' || MODULE_SHIPPING_UPSOAUTH_UPDATE_CHECK === 'On Demand')) {
            $new_version_details = plugin_version_check_for_updates(self::ZEN_CART_PLUGIN_ID, $this->moduleVersion);
            if ($new_version_details !== false) {
                $this->title .= '<span class="alert">' . ' - NOTE: A NEW VERSION OF THIS PLUGIN IS AVAILABLE. <a href="' . $new_version_details['link'] . '" target="_blank" rel="noreferrer noopener">[Details]</a></span>';
            }
            if (MODULE_SHIPPING_UPSOAUTH_UPDATE_CHECK === 'On Demand') {
                $db->Execute(
                    'UPDATE ' . TABLE_CONFIGURATION . '
                        SET configuration_value = \'Never\'
                     WHERE configuration_key = \'MODULE_SHIPPING_UPSOAUTH_UPDATE_CHECK\'
                     LIMIT 1'
                );
            }
        }
    }

    protected function upsOAuthApiExists()
    {
        return (MODULE_SHIPPING_UPSOAUTH_API_CLASS !== '' && file_exists(DIR_FS_CATALOG . DIR_WS_MODULES . '/shipping/upsoauth/' . MODULE_SHIPPING_UPSOAUTH_API_CLASS . '.php'));
    }

    protected function storefrontInitialization()
    {
        global $template, $current_page_base;

        $this->update_status();
        if ($this->enabled === true) {
            $this->initCurrencyCode();
            $this->icon = $template->get_template_dir('shipping_ups.gif', DIR_WS_TEMPLATE, $current_page_base, 'images/icons') . '/shipping_ups.gif';
        }
    }

    public function update_status()
    {
        global $order, $db, $spider_session;

        // -----
        // Disable when the cart's products resulted in free shipping or if this
        // is a spider session (for the shipping estimator).
        //
        if (!empty($spider_session) || !zen_get_shipping_enabled($this->code)) {
            $this->enabled = false;
        }

        // -----
        // Determine whether UPS shipping should be offered, based on the current order's
        // zone-id (storefront **only**).
        //
        if ($this->enabled === true && (int)MODULE_SHIPPING_UPSOAUTH_ZONE > 0 && isset($order)) {
            // -----
            // If only to be enabled for a shipping-zone and the country's not yet set,
            // nothing further to be done.
            //
            if (!isset($order->delivery['country']['id'])) {
                $this->enabled = false;
                return;
            }

            $check = $db->Execute(
                "SELECT zone_id
                   FROM " . TABLE_ZONES_TO_GEO_ZONES . " 
                  WHERE geo_zone_id = " . (int)MODULE_SHIPPING_UPSOAUTH_ZONE . "
                    AND zone_country_id = " . (int)$order->delivery['country']['id'] . "
                  ORDER BY zone_id"
            );
            $check_flag = false;
            foreach ($check as $next_zone) {
                if ($next_zone['zone_id'] < 1 || $next_zone['zone_id'] === (string)$order->delivery['zone_id']) {
                    $check_flag = true;
                    break;
                }
            }

            if ($check_flag === false) {
                $this->enabled = false;
            }
        }

        // -----
        // Give a watching observer the opportunity to disable the overall shipping module.
        //
        $this->notify('NOTIFY_SHIPPING_UPSOAUTH_UPDATE_STATUS', [], $this->enabled);

        // -----
        // If the configured OAuthApi class doesn't exist, the shipping module is always disabled.
        //
        if ($this->upsOAuthApiExists() === false) {
            $this->enabled = false;
        }

        // -----
        // If still enabled, grab/refresh the UPS OAuth token (saved in the customer's session).  If
        // the token creation fails, this module self-disables since it can't get any quotes from
        // UPS!
        //
        if ($this->enabled === true && $this->getOAuthToken() === false) {
            $this->enabled = false;
        }
    }

    // -----
    // Retrieves an OAuth token from UPS to use in follow-on requests.  If successfully retrieved,
    // the token and its expiration time are saved in the customer's session.
    //
    protected function getOAuthToken()
    {
        // -----
        // Load the UpsOAuth API class, if not already loaded and instantiate a
        // copy for local use.
        //
        $ups_api_class = MODULE_SHIPPING_UPSOAUTH_API_CLASS;
        if (!class_exists($ups_api_class)) {
            require DIR_WS_MODULES . "shipping/upsoauth/$ups_api_class.php";
        }
        $this->upsApi = new $ups_api_class(MODULE_SHIPPING_UPSOAUTH_MODE, $this->debug, $this->logfile);

        if (isset($_SESSION['upsoauth_token_expires']) && $_SESSION['upsoauth_token_expires'] > time()) {
            $this->debugLog('Existing OAuth token is present.');
            return true;
        }

        $token_retrieved = false;
        $oauth_token = $this->upsApi->getOAuthToken(MODULE_SHIPPING_UPSOAUTH_CLIENT_ID, MODULE_SHIPPING_UPSOAUTH_CLIENT_SECRET);
        if ($oauth_token !== false) {
            // -----
            // If the response from UPS for the OAuth-Token request indicates that the Client ID and/or
            // Client Secret are invalid, auto-disable this shipping method and send an email to let the
            // store owner know.
            //
            if (isset($oauth_token->response->errors)) {
                $log_message = 'UPS error returned when requesting OAuth token:' . PHP_EOL;
                foreach ($oauth_token->response->errors as $next_error) {
                    $log_message .= $next_error->code . ': ' . $next_error->message . PHP_EOL;
                    if ($next_error->code == 10401) {
                        global $db;
                        $db->Execute(
                            'UPDATE ' . TABLE_CONFIGURATION . '
                                SET configuration_value = \'False\'
                              WHERE configuration_key = \'MODULE_SHIPPING_UPSOAUTH_STATUS\'
                              LIMIT 1'
                        );
                        zen_mail(STORE_NAME, STORE_OWNER_EMAIL_ADDRESS, MODULE_SHIPPING_UPSOAUTH_EMAIL_SUBJECT, MODULE_SHIPPING_UPSOAUTH_INVALID_CREDENTIALS, STORE_NAME, EMAIL_FROM);
                    }
                }
                $this->debugLog($log_message, true);
            } else {
                $token_retrieved = true;
                $this->debugLog('OAuth Token successfully retrieved, expires in ' . ($oauth_token->expires_in - 3) . ' seconds.');
                if (IS_ADMIN_FLAG === false) {
                    $_SESSION['upsoauth_token'] = $oauth_token->access_token;
                    $_SESSION['upsoauth_token_expires'] = time() + $oauth_token->expires_in - 3;
                }
            }
        }
        return $token_retrieved;
    }

    public function quote($method = '')
    {
        global $order;

        // -----
        // Retrieve *all* the UPS quotes for the current shipment, noting that there might be
        // shipping methods not requested by the site via configuration.  If an error (either CURL or
        // UPS) occurs in this retrieval, report that no quotes are available from this shipping module.
        //
        $all_ups_quotes = $this->upsApi->getAllUpsQuotes($_SESSION['upsoauth_token']);
        if (empty($all_ups_quotes->RateResponse->RatedShipment)) {
            if (!isset($all_ups_quotes->response->errors)) {
                return [];
            }

            // -----
            // Some UPS-returned errors are 'recognized':
            //
            // - 110208: Missing or Invalid DestinationCountry. For example, Iran.
            // - 111210: The requested service is unavailable between the selected locations. For countries
            //           with defined states/provinces, returned if no state is currently selected.
            // - 111285: The postal code %postal% is invalid for %state% %country%.
            // - 111286: %state% is not a valid state abbreviation for %country%.
            //
            // Any other errors result in a generic message that includes the
            // code returned.
            //
            $state_name = $order->delivery['state'] ?? zen_get_zone_name((int)$order->delivery['country']['id'], (int)$order->delivery['zone_id'], '');
            $country_name = $order->delivery['country']['title'];
            $ups_error_code = $all_ups_quotes->response->errors[0]->code;
            if ($ups_error_code === '110208') {
                $error_message = sprintf(
                    MODULE_SHIPPING_UPSOAUTH_INVALID_COUNTRY,
                    $country_name,
                    rtrim(ENTRY_COUNTRY, ': ')
                );
            } elseif ($ups_error_code === '111210') {
                $error_message = sprintf(
                    MODULE_SHIPPING_UPSOAUTH_SERVICE_UNAVAILABLE,
                    $state_name,
                    $country_name
                );
                if (empty($state_name)) {
                    $error_message .= ' ' . sprintf(
                        MODULE_SHIPPING_UPSOAUTH_STATE_REQUIRED,
                        rtrim(ENTRY_STATE, ': ')
                    );
                }
            } elseif ($ups_error_code === '111285') {
                $entry_post_code = rtrim(ENTRY_POST_CODE, ': ');
                if (empty($order->delivery['postcode'])) {
                    $error_message = sprintf(
                        MODULE_SHIPPING_UPSOAUTH_POSTCODE_REQUIRED,
                        $entry_post_code,
                        $state_name,
                        $country_name
                    );
                } else {
                    $error_message = sprintf(
                        MODULE_SHIPPING_UPSOAUTH_INVALID_POSTCODE,
                        $entry_post_code,
                        $order->delivery['postcode'],
                        $state_name,
                        $country_name
                    );
                }
            } elseif ($ups_error_code === '111286') {
                $error_message = sprintf(
                    MODULE_SHIPPING_UPSOAUTH_INVALID_STATE,
                    zen_get_zone_code((int)$order->delivery['country']['id'], (int)$order->delivery['zone_id'], 'n/a'),
                    $country_name
                );
            } else {
                $error_message = sprintf(MODULE_SHIPPING_UPSOAUTH_ERROR, $ups_error_code);
            }
            $this->quotes = [
                'code' => $this->code,
                'module' => $this->title,
                'error' => $error_message,
                'methods' => [],
            ];
            if (!empty($this->icon)) {
                $this->quotes['icon'] = zen_image($this->icon, $this->title);
            }
            return $this->quotes;
        }

        // -----
        // Determine which, if any, of the quotes returned are applicable for the current store.  If none are,
        // report that no quotes are available from this shipping module.
        //
        $ups_quotes = $this->upsApi->getConfiguredUpsQuotes($all_ups_quotes);
        if ($ups_quotes === false) {
            return [];
        }

        $methods = $this->upsApi->getShippingMethodsFromQuotes($method, $ups_quotes);
        if (count($methods) === 0) {
            $this->debugLog("No available methods matching required '$method'; no UPS quotes available.");
            return [];
        }

        // -----
        // Sort the shipping methods to be returned in ascending order of cost.
        //
        usort($methods, function($a, $b) {
            if ($a['cost'] === $b['cost']) {
                return 0;
            }
            return ($a['cost'] < $b['cost']) ? -1 : 1;
        });

        $this->quotes = [
            'id' => $this->code,
            'module' => $this->title . $this->upsApi->getWeightInfo(),
        ];

        if ((int)MODULE_SHIPPING_UPSOAUTH_TAX_CLASS > 0) {
            $this->quotes['tax'] = zen_get_tax_rate((int)MODULE_SHIPPING_UPSOAUTH_TAX_CLASS, $order->delivery['country']['id'], $order->delivery['zone_id']);
        }

        if (!empty($this->icon)) {
            $this->quotes['icon'] = zen_image($this->icon, $this->title);
        }
        $this->quotes['methods'] = $methods;
        $this->debugLog('Returning quote:' . PHP_EOL . var_export($this->quotes, true), true);

        return $this->quotes;
    }

    // ----
    // Make sure that the currency-code specified is within the range of those currently enabled for the
    // store, defaulting to the store's default currency (with a log generated) if not.
    //
    private function initCurrencyCode()
    {
        $currency_code = DEFAULT_CURRENCY;
        if (!class_exists('currencies')) {
            require DIR_WS_CLASSES . 'currencies.php';
        }
        $currencies = new currencies();
        if (isset($currencies->currencies[MODULE_SHIPPING_UPSOAUTH_CURRENCY_CODE])) {
            $currency_code = MODULE_SHIPPING_UPSOAUTH_CURRENCY_CODE;
        } else {
            $this->debugLog(sprintf(MODULE_SHIPPING_UPSOAUTH_INVALID_CURRENCY_CODE, MODULE_SHIPPING_UPSOAUTH_CURRENCY_CODE));
        }
        $this->upsApi->setCurrencyCode($currency_code);
    }

    protected function debugLog($message, $include_spacer = false)
    {
        if ($this->debug === true) {
            $spacer = ($include_spacer === false) ? '' : "------------------------------------------\n";
            error_log($spacer . date('Y-m-d H:i:s') . ': ' . $message . PHP_EOL, 3, $this->logfile);
        }
    }

    // -----
    // Public functions used during admin configuration.
    //
    public function check()
    {
        global $db;

        if (!isset($this->_check)) {
            $check_query = $db->Execute(
                "SELECT configuration_value
                   FROM " . TABLE_CONFIGURATION . " 
                  WHERE configuration_key = 'MODULE_SHIPPING_UPSOAUTH_STATUS'
                  LIMIT 1"
            );
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    public function install()
    {
        global $db;
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added)
             VALUES
                ('UPS OAuth Version', 'MODULE_SHIPPING_UPSOAUTH_VERSION', '" . $this->moduleVersion . "', 'You have installed:', 6, 0, NULL, 'zen_cfg_select_option([\'" . $this->moduleVersion . "\'], ', now()),

                ('Enable UPS Shipping', 'MODULE_SHIPPING_UPSOAUTH_STATUS', 'False', 'Do you want to offer UPS shipping?', 6, 0, NULL, 'zen_cfg_select_option([\'True\', \'False\'], ', now()),

                ('Sort order of display.', 'MODULE_SHIPPING_UPSOAUTH_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', 6, 19, NULL, NULL, now()),

                ('Tax Class', 'MODULE_SHIPPING_UPSOAUTH_TAX_CLASS', '0', 'Use the following tax class on the shipping fee.', 6, 17, 'zen_get_tax_class_title', 'zen_cfg_pull_down_tax_classes(', now()),

                ('UPS Api Class', 'MODULE_SHIPPING_UPSOAUTH_API_CLASS', 'UpsOAuthApi', 'If your site has an class-override for the shipping module\'s default (<var>UpsOAuthApi</var>), enter it here.  If the class-file doesn\'t exist, this module will be automatically disabled!', 6, 2, NULL, NULL, now()),

                ('Shipping Zone', 'MODULE_SHIPPING_UPSOAUTH_ZONE', '0', 'If a zone is selected, only enable this shipping method for that zone.', 6, 18, 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now()),

                ('UPS Rates Client ID', 'MODULE_SHIPPING_UPSOAUTH_CLIENT_ID', '', 'Enter the OAuth <code>Client ID</code> assigned to you by UPS; see <a href=\"https://developer.ups.com/get-started?loc=en_US\" target=\"_blank\" rel=\"noreferrer noopener\">this</a> UPS link for more information.', 6, 1, NULL, NULL, now()),

                ('UPS Rates Client Secret', 'MODULE_SHIPPING_UPSOAUTH_CLIENT_SECRET', '', 'Enter your OAuth <code>Client Secret</code> assigned to you by UPS.', 6, 2, NULL, NULL, now()),

                ('Test or Production Mode', 'MODULE_SHIPPING_UPSOAUTH_MODE', 'Test', 'Use this module in Test or Production mode?', 6, 12, NULL, 'zen_cfg_select_option([\'Test\', \'Production\'], ', now()),

                ('UPS Rates <em>Shipper Number</em>', 'MODULE_SHIPPING_UPSOAUTH_SHIPPER_NUMBER', '', 'Enter your UPS Services <em>Shipper Number</em>, if you want to receive your account\'s negotiated rates!', 6, 3, NULL, NULL, now()),

                ('Shipping Origin', 'MODULE_SHIPPING_UPSOAUTH_ORIGIN', 'US Origin', 'What origin point should be used (this setting affects only what UPS product names are shown to the customer).', 6, 7, NULL, 'zen_cfg_select_option([\'US Origin\', \'Canada Origin\', \'European Union Origin\', \'Puerto Rico Origin\', \'Mexico Origin\', \'All other origins\'], ', now()),

                ('Origin Country', 'MODULE_SHIPPING_UPSOAUTH_ORIGIN_COUNTRY', 'US', 'Enter the two-letter code for your origin country.', 6, 10, NULL, NULL, now()),

                ('Origin State/Province', 'MODULE_SHIPPING_UPSOAUTH_ORIGIN_STATEPROV', '', 'Enter the two-letter code for your origin state/province.', 6, 9, NULL, NULL, now()),

                ('Origin City', 'MODULE_SHIPPING_UPSOAUTH_ORIGIN_CITY', '', 'Enter the name of the origin city.', 6, 8, NULL, NULL, now()),

                ('Origin Zip/Postal Code', 'MODULE_SHIPPING_UPSOAUTH_ORIGIN_POSTALCODE', '', 'Enter your origin zip/postalcode.', 6, 11, NULL, NULL, now()),

                ('Pickup Method', 'MODULE_SHIPPING_UPSOAUTH_PICKUP_METHOD', 'Daily Pickup', 'How do you give packages to UPS?', 6, 4, NULL, 'zen_cfg_select_option([\'Daily Pickup\', \'Customer Counter\', \'One Time Pickup\', \'On Call Air Pickup\', \'Letter Center\', \'Air Service Center\'], ', now()),

                ('Packaging Type', 'MODULE_SHIPPING_UPSOAUTH_PACKAGE_TYPE', 'Customer Package', 'What kind of packaging do you use?', 6, 5, NULL, 'zen_cfg_select_option([\'Customer Package\', \'UPS Letter\', \'UPS Tube\', \'UPS Pak\', \'UPS Express Box\', \'UPS 25kg Box\', \'UPS 10kg box\'], ', now()),

                ('Customer Classification Code', 'MODULE_SHIPPING_UPSOAUTH_CUSTOMER_CLASSIFICATION_CODE', '04', '<br>Choose the type of rates to be returned:<ul><li><b>00</b>: Rates associated with your <em>Shipper Number</em></li><li><b>01</b>: Daily Rates</li><li><b>04</b>: Retail Rates (default)</li><li><b>05</b>: Regional Rates</li><li><b>06</b>: General List Rates</li><li><b>53</b>: Standard List Rates</li></ul>', 6, 6, NULL, 'zen_cfg_select_option([\'00\', \'01\', \'04\', \'05\', \'06\', \'53\'], ', now()),

                ('UPS Display Options', 'MODULE_SHIPPING_UPSOAUTH_OPTIONS', '--none--', 'Select from the following the UPS options.', 6, 16, NULL, 'zen_cfg_select_multioption([\'Display weight\', \'Display transit time\'], ',  now()),

                ('Shipping Delay', 'MODULE_SHIPPING_UPSOAUTH_SHIPPING_DAYS_DELAY', '0', 'How many business days after an order is placed is the order shipped? This value is added to the number of business days that UPS indicates in its rate quote.', 6, 7, NULL, NULL, now()),

                ('Unit Weight', 'MODULE_SHIPPING_UPSOAUTH_UNIT_WEIGHT', 'LBS', 'By what unit are your packages weighed?', 6, 13, NULL, 'zen_cfg_select_option([\'LBS\', \'KGS\'], ', now()),

                ('Quote Type', 'MODULE_SHIPPING_UPSOAUTH_QUOTE_TYPE', 'Commercial', 'Quote for Residential or Commercial Delivery', 6, 15, NULL, 'zen_cfg_select_option([\'Commercial\', \'Residential\'], ', now()),

                ('UPS Currency Code', 'MODULE_SHIPPING_UPSOAUTH_CURRENCY_CODE', '" . DEFAULT_CURRENCY . "', 'Enter the 3 letter currency code for your country of origin. United States (USD)', 6, 2, NULL, NULL, now()),

                ('Enable Insurance', 'MODULE_SHIPPING_UPSOAUTH_INSURE', 'True', 'Do you want to insure packages shipped by UPS?', 6, 0, NULL, 'zen_cfg_select_option([\'True\', \'False\'], ', now()),

                ('Shipping Methods', 'MODULE_SHIPPING_UPSOAUTH_TYPES', 'Next Day Air [01], 2nd Day Air [02], Ground [03], Worldwide Express [07], Standard [11], 3 Day Select [12]', 'Select the UPS services to be offered.', 6, 20, NULL, 'zen_cfg_select_multioption([\'Next Day Air [01]\', \'2nd Day Air [02]\', \'Ground [03]\', \'Worldwide Express [07]\', \'Worldwide Expedited [08]\', \'Standard [11]\', \'3 Day Select [12]\', \'Next Day Air Saver [13]\', \'Next Day Air Early [14]\', \'Worldwide Express Plus [54]\', \'2nd Day Air A.M. [59]\', \'Express Saver [65]\'], ', now()),

                ('Check for Updates?', 'MODULE_SHIPPING_UPSOAUTH_UPDATE_CHECK', 'Always', 'Do you want this shipping module to check for Zen Cart plugin updates?  Choose \'Always\' to check each time you visit the <em>Modules :: Shipping</em> page (the default), \'Never\' to never check or \'On Demand\' to check one time when you update this setting.  If you choose \'On Demand\', the setting will be reset the \'Never\' after the check is complete.', 6, 0, NULL, 'zen_cfg_select_option([\'Always\', \'Never\', \'On Demand\'], ', now()),

                ('Enable debug?', 'MODULE_SHIPPING_UPSOAUTH_DEBUG', 'false', 'Enable the shipping-module\'s debug and a debug-log will be created each time a UPS rate is requested', 6, 16, NULL, 'zen_cfg_select_option([\'true\', \'false\'], ',  now())"
        );

        $this->adminInitializationChecks(true);

        // -----
        // Give an observer the opportunity to install additional keys.
        //
        $this->notify('NOTIFY_SHIPPING_UPSOAUTH_INSTALLED');
    }

    public function remove()
    {
        global $db;
        $db->Execute(
            "DELETE FROM " . TABLE_CONFIGURATION . " 
              WHERE configuration_key LIKE 'MODULE_SHIPPING_UPSOAUTH_%'"
        );

        // -----
        // Give an observer the opportunity to uninstall additional keys.
        //
        $this->notify('NOTIFY_SHIPPING_UPSOAUTH_UNINSTALLED');
    }

    public function keys()
    {
        $keys_list = [
            'MODULE_SHIPPING_UPSOAUTH_VERSION',
            'MODULE_SHIPPING_UPSOAUTH_STATUS',
            'MODULE_SHIPPING_UPSOAUTH_SORT_ORDER',
            'MODULE_SHIPPING_UPSOAUTH_TAX_CLASS',
            'MODULE_SHIPPING_UPSOAUTH_API_CLASS',
            'MODULE_SHIPPING_UPSOAUTH_ZONE',
            'MODULE_SHIPPING_UPSOAUTH_CLIENT_ID',
            'MODULE_SHIPPING_UPSOAUTH_CLIENT_SECRET',
            'MODULE_SHIPPING_UPSOAUTH_MODE',
            'MODULE_SHIPPING_UPSOAUTH_SHIPPER_NUMBER',
            'MODULE_SHIPPING_UPSOAUTH_ORIGIN',
            'MODULE_SHIPPING_UPSOAUTH_ORIGIN_COUNTRY',
            'MODULE_SHIPPING_UPSOAUTH_ORIGIN_STATEPROV',
            'MODULE_SHIPPING_UPSOAUTH_ORIGIN_CITY',
            'MODULE_SHIPPING_UPSOAUTH_ORIGIN_POSTALCODE',
            'MODULE_SHIPPING_UPSOAUTH_PICKUP_METHOD',
            'MODULE_SHIPPING_UPSOAUTH_PACKAGE_TYPE',
            'MODULE_SHIPPING_UPSOAUTH_CUSTOMER_CLASSIFICATION_CODE',
            'MODULE_SHIPPING_UPSOAUTH_OPTIONS',
            'MODULE_SHIPPING_UPSOAUTH_SHIPPING_DAYS_DELAY',
            'MODULE_SHIPPING_UPSOAUTH_UNIT_WEIGHT',
            'MODULE_SHIPPING_UPSOAUTH_QUOTE_TYPE',
            'MODULE_SHIPPING_UPSOAUTH_CURRENCY_CODE',
            'MODULE_SHIPPING_UPSOAUTH_INSURE',
            'MODULE_SHIPPING_UPSOAUTH_TYPES',
            'MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE_01',
            'MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE_02',
            'MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE_03',
            'MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE_07',
            'MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE_08',
            'MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE_11',
            'MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE_12',
            'MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE_13',
            'MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE_14',
            'MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE_54',
            'MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE_59',
            'MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE_65',
            'MODULE_SHIPPING_UPSOAUTH_HANDLING_APPLIES',
            'MODULE_SHIPPING_UPSOAUTH_UPDATE_CHECK',
            'MODULE_SHIPPING_UPSOAUTH_DEBUG',
        ];

        // -----
        // Give an observer the opportunity add its additional keys to the display.
        //
        $this->notify('NOTIFY_SHIPPING_UPSOAUTH_KEYS', '', $keys_list);

        return $keys_list;
    }
}
