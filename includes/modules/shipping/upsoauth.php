<?php
// -----
// Processing file for the Zen Cart implementation of the UPS shipping module which uses the
// UPS RESTful API with OAuth authentication.
//
// Copyright 2023, Vinos de Frutas Tropicales
//
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

class upsoauth extends base
{
    // -----
    // Constants that define the test and production endpoints for the API requests.
    //
    const ENDPOINT_TEST = 'https://wwwcie.ups.com/';
    const ENDPOINT_PRODUCTION = 'https://onlinetools.ups.com/';

    // -----
    // Constants used when making the various API requests to UPS; appended to the currently
    // configured endpoint.
    //
    const API_OAUTH_TOKEN = 'security/v1/oauth/token';
    const API_RATING = 'api/rating/v1/Shop';    //- Gives *all* UPS shipping methods for a given From->To address.

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
        $moduleVersion = '1.0.0',
        $endpoint,

        $_check,

        $packagingTypes,
        $pickupMethods,
        $serviceCodes,

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
        if ($this->enabled === true) {
            $this->tax_class = (int)MODULE_SHIPPING_UPSOAUTH_TAX_CLASS;
            $this->endpoint = (MODULE_SHIPPING_UPSOAUTH_MODE === 'Test') ? self::ENDPOINT_TEST : self::ENDPOINT_PRODUCTION;

            $this->debug = (MODULE_SHIPPING_UPSOAUTH_DEBUG === 'true');
            $this->logfile = DIR_FS_LOGS . '/upsoauth-' . date('Ymd') . '.log';

            if (IS_ADMIN_FLAG === true) {
                $this->adminInitializationChecks();
            } else {
                $this->storefrontInitialization();
            }
        }
    }

    protected function adminInitializationChecks()
    {
        global $db, $current_page, $messageStack;

        if ($current_page !== 'modules.php') {
            return;
        }

        // -----
        // Perform some 'sanity checks' on the configuration settings, resetting the shipping-module's enable setting
        // if the current settings will result in multiple, useless requests to UPS.
        //
        // 1. Both the Client ID and Client Secret must be supplied.
        // 2. The "Origin Zip/Postcode" is required when the "Origin" is  US, Canada, Mexico or Puerto Rico.
        // 3. An OAuth token must be issued by UPS for the supplied Client ID/Client Secret pair.
        //
        $configuration_error_message = '';
        if (MODULE_SHIPPING_UPSOAUTH_CLIENT_ID === '' || MODULE_SHIPPING_UPSOAUTH_CLIENT_SECRET === '') {
            $configuration_message = MODULE_SHIPPING_UPSOAUTH_NEED_CREDENTIALS;
        } elseif (MODULE_SHIPPING_UPSOAUTH_ORIGIN_POSTALCODE === '' && !in_array(MODULE_SHIPPING_UPSOAUTH_ORIGIN, ['European Union Origin', 'All other origins'])) {
            $configuration_error_message = MODULE_SHIPPING_UPSOAUTH_NEED_POSTCODE;
        }
        if ($configuration_error_message !== '') {
            $db->Execute(
                "UPDATE " . TABLE_CONFIGURATION . "
                    SET configuration_value = 'False'
                  WHERE configuration_key = 'MODULE_SHIPPING_UPSOAUTH_STATUS'
                  LIMIT 1"
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
                    "UPDATE " . TABLE_CONFIGURATION . "
                        SET configuration_value = 'Never'
                     WHERE configuration_key = 'MODULE_SHIPPING_UPSOAUTH_UPDATE_CHECK'
                     LIMIT 1"
                );
            }
        }
    }

    protected function storefrontInitialization()
    {
        $this->update_status();
        if ($this->enabled === true) {
            $this->initializeValueMappings();
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
        if ($this->enabled === true && isset($order) && (int)MODULE_SHIPPING_UPSOAUTH_ZONE > 0) {
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
        // If still enabled, grab/refresh the UPS OAuth token (saved in the customer's session).  If
        // the token creation fails, this module self-disables since it can't get any quotes from
        // UPS!
        //
        if ($this->enabled === true && $this->getOAuthToken() === false) {
            $this->enabled = false;
        }
    }

    protected function initializeValueMappings()
    {
        // -----
        // UPS "Pickup Methods", mapped from the MODULE_SHIPPING_UPSOAUTH_PICKUP_METHOD configuration
        // setting.
        //
        $this->pickupMethods = [
            'Daily Pickup' => '01',
            'Customer Counter' => '03',
            'One Time Pickup' => '06',
            'On Call Air Pickup' => '07',
            'Letter Center' => '19',
            'Air Service Center' => '20'
        ];

        // -----
        // UPS "Packaging Types", mapped from the MODULE_SHIPPING_UPSOAUTH_PACKAGE_TYPE configuration
        // setting.
        //
        $this->packagingTypes = [
            'Unknown' => '00',
            'UPS Letter' => '01',
            'Customer Package' => '02',
            'UPS Tube' => '03',
            'UPS Pak' => '04',
            'UPS Express Box' => '21',
            'UPS 25kg Box' => '24',
            'UPS 10kg Box' => '25'
        ];

        // -----
        // Human-readable Service Code lookup table. The values returned by the Rates and Service "shop" method are numeric.
        // Using these codes, and the administratively defined Origin, the proper human-readable service name is returned.
        //
        // Notes:
        // 1) The origin specified in the admin configuration affects only the product name as displayed to the user.
        // 2) These code-to-name correlations were last verified with the "UPS Rating Package RESTful Developer Guide" dated 2023-02-17.
        //
        $this->serviceCodes = [
            // US Origin
            'US Origin' => [
                '01' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_01,
                '02' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_02,
                '03' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_03,
                '07' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_07,
                '08' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_08,
                '11' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_11,
                '12' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_12,
                '13' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_13,
                '14' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_14,
                '54' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_54,
                '59' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_59,
                '65' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_65
            ],
            // Canada Origin
            'Canada Origin' => [
                '01' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_01,
                '02' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_02,
                '07' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_07,
                '08' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_08,
                '11' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_11,
                '12' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_12,
                '13' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_13,
                '14' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_14,
                '54' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_54,
                '65' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_65
            ],
            // European Union Origin
            'European Union Origin' => [
                '07' => MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_07,
                '08' => MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_08,
                '11' => MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_11,
                '54' => MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_54,
                '65' => MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_65
            ],
            // Puerto Rico Origin
            'Puerto Rico Origin' => [
                '01' => MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_01,
                '02' => MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_02,
                '03' => MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_03,
                '07' => MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_07,
                '08' => MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_08,
                '14' => MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_14,
                '54' => MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_54,
                '65' => MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_65
            ],
            // Mexico Origin
            'Mexico Origin' => [
                '07' => MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_07,
                '08' => MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_08,
                '11' => MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_11,
                '54' => MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_54,
                '65' => MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_65
            ],
            // All other origins
            'All other origins' => [
                '07' => MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_07,
                '08' => MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_08,
                '11' => MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_11,
                '54' => MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_54,
                '65' => MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_65
            ],
        ];
    }

    // -----
    // Retrieves an OAuth token from UPS to use in follow-on requests.  If successfully retrieved,
    // the token and its expiration time are saved in the customer's session.
    //
    protected function getOAuthToken()
    {
        if (isset($_SESSION['upsoauth_token_expires']) && $_SESSION['upsoauth_token_expires'] > time()) {
            $this->debugLog('Existing OAuth token is present.');
            return true;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'x-merchant-id: ' . MODULE_SHIPPING_UPSOAUTH_CLIENT_ID,
                'Authorization: Basic ' . base64_encode(MODULE_SHIPPING_UPSOAUTH_CLIENT_ID . ':' . MODULE_SHIPPING_UPSOAUTH_CLIENT_SECRET)
            ],
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_URL => $this->endpoint . self::API_OAUTH_TOKEN,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ]);

        $response = curl_exec($ch);
        $token_retrieved = false;
        if ($response === false) {
            $this->debugLog('CURL error requesting Token (' . curl_errno($ch) . ', ' . curl_error($ch) . ')');
        } else {
            $response_details = json_decode($response);

            // -----
            // If the response from UPS for the OAuth-Token request indicates that the Client ID and/or
            // Client Secret are invalid, auto-disable this shipping method and send an email to let the
            // store owner know.
            //
            if (isset($response_details->response->errors)) {
                $log_message = 'UPS error returned when requesting OAuth token:' . PHP_EOL;
                foreach ($response_details->response->errors as $next_error) {
                    $log_message .= $next_error->code . ': ' . $next_error->message . PHP_EOL;
                    if ($next_error->code == 10401) {
                        global $db;
                        $db->Execute(
                            "UPDATE " . TABLE_CONFIGURATION . "
                                SET configuration_value = 'False'
                              WHERE configuration_key = 'MODULE_SHIPPING_UPSOAUTH_STATUS'
                              LIMIT 1"
                        );
                        zen_mail(STORE_NAME, STORE_OWNER_EMAIL_ADDRESS, MODULE_SHIPPING_UPSOAUTH_EMAIL_SUBJECT, MODULE_SHIPPING_UPSOAUTH_INVALID_CREDENTIALS, STORE_NAME, EMAIL_FROM);
                    }
                }
                $this->debugLog($log_message, true);
            } else {
                $token_retrieved = true;
                $this->debugLog('OAuth Token successfully retrieved, expires in ' . ($response_details->expires_in - 3) . ' seconds.');
                if (IS_ADMIN_FLAG === false) {
                    $_SESSION['upsoauth_token'] = $response_details->access_token;
                    $_SESSION['upsoauth_token_expires'] = time() + $response_details->expires_in - 3;
                }
            }
        }

        curl_close($ch);

        return $token_retrieved;
    }

    public function quote($method = '')
    {
        global $order, $shipping_num_boxes, $shipping_weight, $template, $current_page_base;

        // -----
        // Retrieve *all* the UPS quotes for the current shipment, noting that there might be
        // shipping methods not requested by the site via configuration.  If an error (either CURL or
        // UPS) occurs in this retrieval, report that no quotes are available from this shipping module.
        //
        $all_ups_quotes = $this->getAllUpsQuotes();
        if ($all_ups_quotes === false) {
            return false;
        }

        // -----
        // Determine which, if any, of the quotes returned are applicable for the current store.  If none are,
        // report that no quotes are available from this shipping module.
        //
        $ups_quotes = $this->getConfiguredUpsQuotes($all_ups_quotes);
        if ($ups_quotes === false) {
            return false;
        }

        // -----
        // Any handling-fee can be represented as either a fixed or a percentage.  Determine which
        // and set the fee's adder/multiplier value for use in the quote-generation loop below.
        //
        // Note that no checking of malformed values is performed; PHP Warnings and Notices will be
        // issued if the value's not numeric or a percentage value doesn't end in %.
        //
        if (strpos(MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE, '%') === false) {
            $handling_fee_adder = MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE;
            $handling_fee_multiplier = 1;
        } else {
            $handling_fee_adder = 0;
            $handling_fee_multiplier = 1 + (rtrim(MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE, '%') / 100);
        }

        // -----
        // Create the array that maps the UPS service codes to their names.
        //
        $methods = [];
        foreach ($ups_quotes as $service_code => $quote_info) {
            $type = $quote_info['title'];
            $cost = $quote_info['cost'];

            if ($method === '' || $method === $type) {
                $title = $type;
                if (strpos(MODULE_SHIPPING_UPSOAUTH_OPTIONS, 'transit') !== false && $quote_info['business_days_in_transit'] !== false) {
                    $title .= ' ' . sprintf(MODULE_SHIPPING_UPSOAUTH_ETA_TEXT, (int)$quote_info['business_days_in_transit']);
                }

                $methods[] = [
                    'id' => $type,
                    'title' => $title,
                    'cost' => ($handling_fee_multiplier * $cost) + $handling_fee_adder,
                ];
            }
        }
        if (count($methods) === 0) {
            $this->debugLog("No available methods matching required '$method'; no UPS quotes available.");
            return false;
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

        $weight_info = '';
        if ((strpos(MODULE_SHIPPING_UPSOAUTH_OPTIONS, 'weight') !== false)) {
            $weight_info = ' (' . $shipping_num_boxes . ($shipping_num_boxes > 1 ? ' pkg(s) x ' : ' pkg x ') . number_format($shipping_weight, 2) . ' ' . strtolower(MODULE_SHIPPING_UPSOAUTH_UNIT_WEIGHT) . ' total)';
        }
        $this->quotes = [
            'id' => $this->code,
            'module' => $this->title . $weight_info,
        ];

        if ((int)MODULE_SHIPPING_UPSOAUTH_TAX_CLASS > 0) {
            $this->quotes['tax'] = zen_get_tax_rate((int)MODULE_SHIPPING_UPSOAUTH_TAX_CLASS, $order->delivery['country']['id'], $order->delivery['zone_id']);
        }

        $this->icon = $template->get_template_dir('shipping_ups.gif', DIR_WS_TEMPLATE, $current_page_base, 'images/icons') . '/shipping_ups.gif';
        if (!empty($this->icon)) {
            $this->quotes['icon'] = zen_image($this->icon, $this->title);
        }
        $this->quotes['methods'] = $methods;
        $this->debugLog('Returning quote:' . PHP_EOL . var_export($this->quotes, true), true);

        return $this->quotes;
    }

    // -----
    // Retrieve the requested UPS quote.  This method will return either a JSON-decoded
    // object that represents the received quote information or (bool)false if an error, either
    // CURL or UPS, is indicated.
    //
    protected function getAllUPSQuotes()
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $_SESSION['upsoauth_token'],
                'Content-Type: application/json',
                'transId: string',
                'transactionSrc: testing',
            ],
            CURLOPT_POSTFIELDS => $this->buildRateRequest(),
            //  CURLOPT_URL => "https://wwwcie.ups.com/api/rating/" . $version . "/" . $requestoption . "?" . http_build_query($query),
            CURLOPT_URL => $this->endpoint . self::API_RATING,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $this->debugLog('CURL error requesting Rates (' . curl_errno($ch) . ', ' . curl_error($ch) . ')');
        } else {
            $response_details = json_decode($response);
            $this->debugLog(json_encode($response_details, JSON_PRETTY_PRINT), true);
            if (isset($response_details->response->errors)) {
                $response_details = false;
            }
        }
        curl_close($ch);

        return $response_details;
    }

    // -----
    // This function builds an array containing the to-be-issued Rate Request, returning
    // that array in a JSON-encoded format.
    //
    protected function buildRateRequest()
    {
        global $order, $shipping_num_boxes, $shipping_weight;

        $rate_request = [
            'RateRequest' => [
                'Request' => [
                    'TransactionReference' => [
                        'CustomerContext' => 'CustomerContext',
                        'TransactionIdentifier' => 'TransactionIdentifier'
                    ],
                ],
                'PickupType' => [
                    'Code' => $this->pickupMethods[MODULE_SHIPPING_UPSOAUTH_PICKUP_METHOD],
                ],
                'CustomerClassification' => [
                    'Code' => MODULE_SHIPPING_UPSOAUTH_CUSTOMER_CLASSIFICATION_CODE,
                ],
                'Shipment' => [
                    'Shipper' => [
                        'Address' => [
                            'City' => MODULE_SHIPPING_UPSOAUTH_ORIGIN_CITY,
                            'StateProvinceCode' => MODULE_SHIPPING_UPSOAUTH_ORIGIN_STATEPROV,
                            'PostalCode' => MODULE_SHIPPING_UPSOAUTH_ORIGIN_POSTALCODE,
                            'CountryCode' => MODULE_SHIPPING_UPSOAUTH_ORIGIN_COUNTRY,
                        ]
                    ],
                    // -----
                    // When rates are requested from the shipping-estimator, the city isn't set and the postcode might not be.  Provide
                    // defaults for the request.
                    //
                    'ShipTo' => [
                        'Address' => [
                            'City' => (!empty($order->delivery['city'])) ? $order->delivery['city'] : '',
                            'StateProvinceCode' => zen_get_zone_code((int)$order->delivery['country']['id'], (int)$order->delivery['zone_id'], ''),
                            'PostalCode' => (!empty($order->delivery['postcode'])) ? $order->delivery['postcode'] : '',
                            'CountryCode' => $order->delivery['country']['iso_code_2'],
                        ]
                    ],
                   'DeliveryTimeInformation' => [
                        'PackageBillType' => $this->packagingTypes[MODULE_SHIPPING_UPSOAUTH_PACKAGE_TYPE],
                    ],
                ]
            ]
        ];

        if (MODULE_SHIPPING_UPSOAUTH_SHIPPER_NUMBER !== '') {
            $rate_request['RateRequest']['Shipment']['Shipper']['ShipperNumber'] = MODULE_SHIPPING_UPSOAUTH_SHIPPER_NUMBER;
            $rate_request['RateRequest']['Shipment']['ShipmentRatingOptions']['NegotiatedRatesIndicator'] = 'Y';
        }

        // -----
        // Determine the package 'value'.  It'll be 0 (uninsured) if the module's configuration
        // indicates that packages are not to be insured.
        //
        $package_value = 0.0;
        if (MODULE_SHIPPING_UPSOAUTH_INSURE === 'True') {
            if (isset($order->info['subtotal'])) {
                $package_value = ceil($order->info['subtotal']);
            } elseif (isset($_SESSION['cart']->total)) {
                $package_value = ceil($_SESSION['cart']->total);
            }
        }
        $package_value = number_format(ceil($package_value / $shipping_num_boxes), 0, '.', '');

        // -----
        // Build the 'base' Package information.  It's the same for each of the shipping boxes.
        //
        $package_info = [
            'PackagingType' => [
                'Code' => $this->packagingTypes[MODULE_SHIPPING_UPSOAUTH_PACKAGE_TYPE],
            ],
            'PackageWeight' => [
                'UnitOfMeasurement' => [
                    'Code' => MODULE_SHIPPING_UPSOAUTH_UNIT_WEIGHT,
                ],
                'Weight' => number_format($shipping_weight, 1),
            ],
            'PackageServiceOptions' => [
                'DeclaredValue' => [
                    'CurrencyCode' => $this->initCurrencyCode(),
                    'MonetaryValue' => $package_value,
                ],
            ],
        ];

        // -----
        // Now, add the package(s) to the request (one for each shipping-box).
        //
        $rate_request['RateRequest']['Shipment']['Package'] = [];
        for ($i = 0; $i < $shipping_num_boxes; $i++) {
            $rate_request['RateRequest']['Shipment']['Package'][] = $package_info;
        }

        // -----
        // Give a watching observer the opportunity to make changes to the request, prior to its JSON-encoding.
        //
        $this->notify('NOTIFY_SHIPPING_UPSOAUTH_RATE_REQUEST', $order, $rate_request);

        // -----
        // Write the to-be-issued request for debug.
        //
        $this->debugLog('RAW Rate Request' . PHP_EOL . json_encode($rate_request, JSON_PRETTY_PRINT), true);

        return json_encode($rate_request);
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
        $currency_code = MODULE_SHIPPING_UPSOAUTH_CURRENCY_CODE;
        if (!isset($currencies->currencies[MODULE_SHIPPING_UPSOAUTH_CURRENCY_CODE])) {
            $error_message = sprintf(MODULE_SHIPPING_UPSOAUTH_INVALID_CURRENCY_CODE, MODULE_SHIPPING_UPSOAUTH_CURRENCY_CODE);
            trigger_error($error_message, E_USER_WARNING);

            if (IS_ADMIN_FLAG === true) {
                global $messageStack;
                $messageStack->add_session('<b>upsoauth</b>: ' . $error_message, 'error');
            }
        }
        return $currency_code;
    }

    // -----
    // From *all* UPS quotes returned, grab only those that the store owner is interested in.  Returns
    // an array of the 'interesting' quotes or (bool)false if none of the returned quotes were
    // 'interesting'.
    //
    protected function getConfiguredUpsQuotes($all_ups_quotes)
    {
        $quotes = [];
        foreach ($all_ups_quotes->RateResponse->RatedShipment as $next_shipment) {
            $service_code = $next_shipment->Service->Code;
            if (strpos(MODULE_SHIPPING_UPSOAUTH_TYPES, "[$service_code]") === false) {
                continue;
            }
            $days_in_transit = isset($next_shipment->GuaranteedDelivery->BusinessDaysInTransit) ? $next_shipment->GuaranteedDelivery->BusinessDaysInTransit : false;
            if ($days_in_transit !== false) {
                $days_in_transit += ceil((float)MODULE_SHIPPING_UPSOAUTH_SHIPPING_DAYS_DELAY);
            }
            if (isset($next_shipment->NegotiatedRateCharges->TotalCharge->MonetaryValue)) {
                $cost = $next_shipment->NegotiatedRateCharges->TotalCharge->MonetaryValue;
            } else {
                $cost = $next_shipment->TotalCharges->MonetaryValue;
            }
            $quotes[$service_code] = [
                'cost' => $cost,
                'business_days_in_transit' => $days_in_transit,
                'title' => $this->serviceCodes[MODULE_SHIPPING_UPSOAUTH_ORIGIN][$service_code],
            ];
        }

        $this->debugLog('getConfiguredUpsQuotes, returning: ' . PHP_EOL . var_export($quotes, true));
        return (count($quotes) === 0) ? false : $quotes;
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
                ('Enable UPS Shipping', 'MODULE_SHIPPING_UPSOAUTH_STATUS', 'False', 'Do you want to offer UPS shipping?', 6, 0, NULL, 'zen_cfg_select_option([\'True\', \'False\'], ', now()),

                ('Sort order of display.', 'MODULE_SHIPPING_UPSOAUTH_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', 6, 19, NULL, NULL, now()),

                ('Tax Class', 'MODULE_SHIPPING_UPSOAUTH_TAX_CLASS', '0', 'Use the following tax class on the shipping fee.', 6, 17, 'zen_get_tax_class_title', 'zen_cfg_pull_down_tax_classes(', now()),

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

                ('Handling Fee', 'MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE', '0', 'Handling fee for this shipping method.  The value you enter is either a fixed value for all shipping quotes or a percentage, e.g. 10%, of each UPS quote\'s value.', 6, 16, NULL, NULL, now()),

                ('UPS Currency Code', 'MODULE_SHIPPING_UPSOAUTH_CURRENCY_CODE', '" . DEFAULT_CURRENCY . "', 'Enter the 3 letter currency code for your country of origin. United States (USD)', 6, 2, NULL, NULL, now()),

                ('Enable Insurance', 'MODULE_SHIPPING_UPSOAUTH_INSURE', 'True', 'Do you want to insure packages shipped by UPS?', 6, 0, NULL, 'zen_cfg_select_option([\'True\', \'False\'], ', now()),

                ('Shipping Methods', 'MODULE_SHIPPING_UPSOAUTH_TYPES', 'Next Day Air [01], 2nd Day Air [02], Ground [03], Worldwide Express [07], Standard [11], 3 Day Select [12]', 'Select the UPS services to be offered.', 6, 20, NULL, 'zen_cfg_select_multioption([\'Next Day Air [01]\', \'2nd Day Air [02]\', \'Ground [03]\', \'Worldwide Express [07]\', \'Worldwide Expedited [08]\', \'Standard [11]\', \'3 Day Select [12]\', \'Next Day Air Saver [13]\', \'Next Day Air Early [14]\', \'Worldwide Express Plus [54]\', \'2nd Day Air A.M. [59]\', \'Express Saver [65]\'], ', now()),

                ('Check for Updates?', 'MODULE_SHIPPING_UPSOAUTH_UPDATE_CHECK', 'Always', 'Do you want this shipping module to check for Zen Cart plugin updates?  Choose \'Always\' to check each time you visit the <em>Modules :: Shipping</em> page (the default), \'Never\' to never check or \'On Demand\' to check one time when you update this setting.  If you choose \'On Demand\', the setting will be reset the \'Never\' after the check is complete.', 6, 0, NULL, 'zen_cfg_select_option([\'Always\', \'Never\', \'On Demand\'], ', now()),

                ('Enable debug?', 'MODULE_SHIPPING_UPSOAUTH_DEBUG', 'false', 'Enable the shipping-module\'s debug and a debug-log will be created each time a UPS rate is requested', 6, 16, NULL, 'zen_cfg_select_option([\'true\', \'false\'], ',  now())"
        );
    }

    public function remove()
    {
        global $db;
        $db->Execute(
            "DELETE FROM " . TABLE_CONFIGURATION . " 
              WHERE configuration_key IN ('" . implode("', '", $this->keys()) . "')"
        );
    }

    public function keys()
    {
        return [
            'MODULE_SHIPPING_UPSOAUTH_STATUS',
            'MODULE_SHIPPING_UPSOAUTH_SORT_ORDER',
            'MODULE_SHIPPING_UPSOAUTH_TAX_CLASS',
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
            'MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE',
            'MODULE_SHIPPING_UPSOAUTH_CURRENCY_CODE',
            'MODULE_SHIPPING_UPSOAUTH_INSURE',
            'MODULE_SHIPPING_UPSOAUTH_TYPES',
            'MODULE_SHIPPING_UPSOAUTH_UPDATE_CHECK',
            'MODULE_SHIPPING_UPSOAUTH_DEBUG',
        ];
    }
}
